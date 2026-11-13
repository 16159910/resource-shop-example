<?php
namespace app\common\service;

use app\common\model\Material;

/**
 * 资料发货方式。
 *
 * 网盘链接和直接下载可以同时配置，用户自己挑顺手的用——
 * 所以能给哪几种以「实际填了什么」为准，不看 delivery_type。
 * delivery_type 只留作列表标签和订单快照，由后台保存时自动推导。
 */
class DeliveryService
{
    public const WAY_PAN  = 'pan';
    public const WAY_FILE = 'file';
    /** 卡密商品：付款后发卡，卡密内容按用户另取（CardKeyService::delivery） */
    public const WAY_CARD = 'card';

    /** delivery_type：1=仅网盘 2=仅直链 3=两种都有 */
    public const TYPE_PAN  = 1;
    public const TYPE_FILE = 2;
    public const TYPE_BOTH = 3;

    /**
     * 这份资料实际能提供哪些获取方式
     *
     * @return string[] 可能包含 'pan' / 'file' / 'card'，都没配则为空数组
     */
    public static function ways(Material $material): array
    {
        if (CardKeyService::isCard($material)) {
            return [self::WAY_CARD];
        }
        $ways = [];
        if (self::hasPan($material)) {
            $ways[] = self::WAY_PAN;
        }
        if (self::hasFile($material)) {
            $ways[] = self::WAY_FILE;
        }
        return $ways;
    }

    public static function hasPan(Material $material): bool
    {
        return trim((string) $material->pan_url) !== '';
    }

    public static function hasFile(Material $material): bool
    {
        return trim((string) $material->file_path) !== '';
    }

    /** 按实际配置推导 delivery_type，后台保存时调用，不用运营手动选 */
    public static function typeOf(array $data): int
    {
        $hasPan  = trim((string) ($data['pan_url'] ?? '')) !== '';
        $hasFile = trim((string) ($data['file_path'] ?? '')) !== '';
        if ($hasPan && $hasFile) {
            return self::TYPE_BOTH;
        }
        if ($hasFile) {
            return self::TYPE_FILE;
        }
        return self::TYPE_PAN;
    }

    /**
     * 把 file_path 解析成可读取的绝对路径。
     *
     * protected/... 放在项目根目录下但在 web 根之外，/uploads/... 在 public 里；
     * 两种都要挡住路径穿越，所以集中在这里做，调用方不要再各写一份。
     *
     * @return string 通过校验的绝对路径
     * @throws \RuntimeException 路径非法或文件不存在
     */
    public static function resolveFile(Material $material): string
    {
        return self::resolvePath((string) $material->file_path);
    }

    /**
     * 同 resolveFile，直接给路径。免费资料等不是 Material 的也走这里，
     * 穿越校验只维护这一份。
     */
    public static function resolvePath(string $path): string
    {
        $p = trim($path);
        if ($p === '') {
            throw new \RuntimeException('文件暂未上传');
        }

        if (str_starts_with($p, 'protected/')) {
            $filePath  = root_path() . $p;
            $allowRoot = realpath(root_path() . 'protected/');
        } else {
            $filePath  = public_path() . ltrim($p, '/');
            $allowRoot = realpath(public_path() . 'uploads/');
        }

        // 补目录分隔符再比前缀，防 protected_x 这类同前缀目录混淆
        $realFile = realpath($filePath);
        if (!$realFile || !$allowRoot || !str_starts_with($realFile, $allowRoot . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('文件路径异常，请联系客服');
        }
        if (!is_file($realFile)) {
            throw new \RuntimeException('文件不存在，请联系客服');
        }
        return $realFile;
    }
}
