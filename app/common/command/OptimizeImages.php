<?php
namespace app\common\command;

use app\common\model\Admin;
use app\common\model\Material;
use app\common\model\MaterialPreview;
use app\common\model\Order;
use app\common\model\SystemConfig;
use app\common\model\User;
use app\common\service\ImageOptimizeService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class OptimizeImages extends Command
{
    /** 数据库里存放图片路径的字段：模型类 => 字段名 */
    private const PATH_COLUMNS = [
        Material::class        => 'cover',
        Order::class           => 'cover',
        MaterialPreview::class => 'image_path',
        Admin::class           => 'avatar',
        User::class            => 'avatar',
    ];

    /** system_config 表中存放图片路径的配置项 */
    private const CONFIG_KEYS = ['site_logo', 'site_favicon'];

    /**
     * 各子目录的图片规格：最长边，以及是否需要列表缩略图。
     * logo 在页头只显示 36px，头像同理，都不需要大图和缩略图。
     */
    private const SPECS = [
        'cover'   => ['edge' => 1200, 'thumb' => true],
        'preview' => ['edge' => 1200, 'thumb' => true],
        'config'  => ['edge' => 400,  'thumb' => false],
        'avatar'  => ['edge' => 300,  'thumb' => false],
    ];

    protected function configure(): void
    {
        $this->setName('image:optimize')
             ->addOption('apply', null, Option::VALUE_NONE, '真正执行，不加此项只做试运行')
             ->addOption('dir', null, Option::VALUE_REQUIRED, '只处理指定子目录，如 cover')
             ->setDescription('压缩 public/uploads 下的图片并生成列表缩略图');
    }

    protected function execute(Input $input, Output $output): void
    {
        $apply  = (bool) $input->getOption('apply');
        $subDir = trim((string) $input->getOption('dir'), '/');

        $root = public_path() . 'uploads' . ($subDir !== '' ? '/' . $subDir : '');
        if (!is_dir($root)) {
            $output->error('目录不存在：' . $root);
            return;
        }

        if (!$apply) {
            $output->writeln('<comment>试运行模式，不会修改任何文件。确认无误后加 --apply 执行。</comment>');
        }

        $backupDir = runtime_path() . 'image_backup/' . date('YmdHis') . '/';
        $renames   = [];   // 旧的 URL 路径 => 新的 URL 路径
        $totalOld  = 0;
        $totalNew  = 0;
        $done      = 0;
        $skipped   = 0;
        $found     = 0;

        foreach ($this->scan($root) as $absPath) {
            $oldSize = filesize($absPath);
            $oldUrl  = $this->toUrl($absPath);

            if (!$apply) {
                $output->writeln(sprintf('  [试运行] %s (%s)', $oldUrl, $this->fmt($oldSize)));
                $totalOld += $oldSize;
                $found++;
                continue;
            }

            $this->backup($absPath, $backupDir);

            $spec = $this->specFor($oldUrl);

            $newPath = ImageOptimizeService::optimize($absPath, $spec['edge']);
            if ($newPath === null) {
                $skipped++;
                $output->writeln(sprintf('  <comment>跳过</comment> %s（无需压缩或格式不支持）', $oldUrl));
                continue;
            }

            if ($spec['thumb']) {
                ImageOptimizeService::makeThumb($newPath);
            }

            $newSize   = filesize($newPath);
            $totalOld += $oldSize;
            $totalNew += $newSize;
            $done++;

            $newUrl = $this->toUrl($newPath);
            if ($newUrl !== $oldUrl) {
                $renames[$oldUrl] = $newUrl;
            }

            $output->writeln(sprintf(
                '  %s  %s -> %s  (-%d%%)',
                $newUrl, $this->fmt($oldSize), $this->fmt($newSize),
                (int) (($oldSize - $newSize) * 100 / $oldSize)
            ));
        }

        if (!$apply) {
            $output->writeln('');
            $output->writeln(sprintf('共发现 %d 个文件，合计 %s。', $found, $this->fmt($totalOld)));
            return;
        }

        $updated = $renames ? $this->syncDatabase($renames, $output) : 0;

        $output->writeln('');
        $output->writeln(sprintf('压缩 %d 个文件，跳过 %d 个。', $done, $skipped));
        if ($totalOld > 0) {
            $output->writeln(sprintf(
                '体积 %s -> %s，节省 %s（-%d%%）。',
                $this->fmt($totalOld), $this->fmt($totalNew),
                $this->fmt($totalOld - $totalNew),
                (int) (($totalOld - $totalNew) * 100 / $totalOld)
            ));
        }
        $output->writeln(sprintf('数据库路径更新 %d 处。', $updated));
        $output->writeln('原图已备份至：' . $backupDir);
    }

    /** 递归扫描目录下的图片文件，跳过缩略图和无水印原图 */
    private function scan(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;
            $base = $file->getBasename('.' . $ext);
            if (str_ends_with($base, ImageOptimizeService::THUMB_SUFFIX)) continue;
            // orig_ 是遗留在 public 下的无水印原图，是重新生成水印的源文件，不能动
            if (str_starts_with($base, 'orig_')) continue;
            yield $file->getPathname();
        }
    }

    /** 按 uploads 下的子目录取图片规格，未知目录用 cover 的规格 */
    private function specFor(string $url): array
    {
        $segment = explode('/', trim($url, '/'))[1] ?? '';
        return self::SPECS[$segment] ?? self::SPECS['cover'];
    }

    /** 绝对路径转成数据库里存的 URL 路径 */
    private function toUrl(string $absPath): string
    {
        return '/' . ltrim(str_replace(public_path(), '', $absPath), '/');
    }

    private function backup(string $absPath, string $backupDir): void
    {
        $target = $backupDir . ltrim(str_replace(public_path(), '', $absPath), '/');
        $dir    = dirname($target);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        copy($absPath, $target);
    }

    /** 把改了扩展名的图片路径同步到各张表 */
    private function syncDatabase(array $renames, Output $output): int
    {
        $count = 0;

        foreach (self::PATH_COLUMNS as $modelClass => $column) {
            foreach ($renames as $oldUrl => $newUrl) {
                $count += $modelClass::where($column, $oldUrl)->update([$column => $newUrl]);
            }
        }

        foreach (self::CONFIG_KEYS as $key) {
            $config = SystemConfig::where('key', $key)->find();
            if ($config && isset($renames[$config->value])) {
                $config->save(['value' => $renames[$config->value]]);
                $count++;
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%d 个文件扩展名发生变化，已同步数据库。</info>', count($renames)));
        return $count;
    }

    private function fmt(int $bytes): string
    {
        return $bytes >= 1048576
            ? sprintf('%.1fMB', $bytes / 1048576)
            : sprintf('%.0fKB', $bytes / 1024);
    }
}
