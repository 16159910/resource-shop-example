<?php
namespace app\common\service;

use app\common\model\DownloadLog;
use app\common\model\Material;
use app\home\service\OrderService;
use think\facade\Cache;

/**
 * 把已购资料发到用户邮箱。
 *
 * 网盘发货的发链接和提取码；文件发货的，小文件直接当附件，
 * 大文件（多数邮箱附件上限 20～50MB）发一个限时签名下载链接。
 */
class MaterialMailService
{
    /** 每人每天最多发几封，防止拿来刷邮件 */
    private const DAILY_LIMIT = 10;
    /** 超过这个大小不放附件，改发下载链接 */
    private const ATTACH_MAX = 20 * 1024 * 1024;
    /** 邮件里下载链接的有效期 */
    private const LINK_TTL = 7 * 86400;

    /** @throws \RuntimeException 消息可直接给用户看 */
    public function send(int $userId, int $materialId, string $email): void
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            throw new \RuntimeException('邮箱格式不正确');
        }
        if (!OrderService::hasPurchased($userId, $materialId)) {
            throw new \RuntimeException('购买后才能发送到邮箱');
        }
        $material = Material::find($materialId);
        if (!$material) {
            throw new \RuntimeException('资料不存在');
        }
        $ways = DeliveryService::ways($material);
        if (!$ways) {
            throw new \RuntimeException('资料暂未上传，请联系客服');
        }

        $key   = 'mail_material:' . $userId . ':' . date('Ymd');
        $count = (int) Cache::get($key, 0);
        if ($count >= self::DAILY_LIMIT) {
            throw new \RuntimeException('今天发送次数已达上限，明天再试');
        }

        [$html, $attachments] = $this->buildBody($material, $userId);
        MailService::send($email, '【' . SystemConfigService::get('site_name', '资料小铺') . '】' . $material->title, $html, $attachments);

        Cache::set($key, $count + 1, 86400);
    }

    private function buildBody(Material $material, int $userId): array
    {
        $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $blocks = [];
        $attachments = [];

        if (CardKeyService::isCard($material)) {
            $d = CardKeyService::delivery($material, $userId);
            if (!$d['cards']) {
                throw new \RuntimeException('卡密补货中，到账后再发送');
            }
            $card = '';
            foreach ($d['cards'] as $c) {
                $card .= '<p style="margin:0 0 8px;font-family:Menlo,Consolas,monospace;font-size:16px;color:#172443;">'
                    . '卡号：<b>' . $h($c['no']) . '</b>' . ($c['secret'] !== '' ? '<br>密码：<b>' . $h($c['secret']) . '</b>' : '') . '</p>';
            }
            if ($d['use_url'] !== '') {
                $card .= '<p style="margin:12px 0 0;color:#8a94a8;font-size:13px;">使用地址</p><p style="margin:4px 0 0;"><a href="' . $h($d['use_url']) . '" style="color:#3b7ef6;word-break:break-all;">' . $h($d['use_url']) . '</a></p>';
            }
            if ($d['use_guide'] !== '') {
                $card .= '<p style="margin:12px 0 0;color:#8a94a8;font-size:13px;">使用说明</p><p style="margin:4px 0 0;color:#4b5568;white-space:pre-line;">' . $h($d['use_guide']) . '</p>';
            }
            $blocks[] = $card;
        }

        if (DeliveryService::hasPan($material)) {
            // 后台常直接粘整段分享文案（「通过网盘分享的文件…链接: https://… 提取码…」），链接只取里面的网址
            $panText = (string) $material->pan_url;
            $panHref = preg_match('#https?://[^\s，,]+#u', $panText, $m) ? $m[0] : $panText;
            $pan = '<p style="margin:0 0 6px;color:#8a94a8;font-size:13px;">网盘链接</p>'
                . '<p style="margin:0 0 12px;"><a href="' . $h($panHref) . '" style="color:#3b7ef6;word-break:break-all;">' . $h($panText) . '</a></p>';
            if ($material->pan_code) {
                $pan .= '<p style="margin:0;color:#8a94a8;font-size:13px;">提取码 <b style="color:#172443;font-size:18px;letter-spacing:2px;">' . $h($material->pan_code) . '</b></p>';
            }
            $blocks[] = $pan;
        }

        if (DeliveryService::hasFile($material)) {
            $path = DeliveryService::resolveFile($material);
            $name = $material->file_name ?: basename($path);
            if (filesize($path) <= self::ATTACH_MAX) {
                $attachments[] = ['path' => $path, 'name' => $name];
                $blocks[] = '<p style="margin:0;color:#4b5568;">资料文件<b>' . $h($name) . '</b>已作为附件随本邮件发送。</p>';
            } else {
                $url = request()->domain() . '/mail/file?t=' . self::signLink($material->id, $userId);
                $blocks[] = '<p style="margin:0 0 10px;color:#4b5568;">文件较大，请点击下方按钮下载（7 天内有效）：</p>'
                    . '<p style="margin:0;"><a href="' . $h($url) . '" style="display:inline-block;padding:10px 22px;border-radius:8px;background:#3b7ef6;color:#fff;text-decoration:none;font-weight:bold;">下载 ' . $h($name) . '</a></p>';
            }
        }

        $site = $h(SystemConfigService::get('site_name', '资料小铺'));
        $html = '<div style="max-width:560px;margin:0 auto;padding:24px;font-family:-apple-system,\'PingFang SC\',\'Microsoft YaHei\',sans-serif;color:#1f2a44;">'
            . '<h2 style="margin:0 0 6px;font-size:20px;">' . $h($material->title) . '</h2>'
            . '<p style="margin:0 0 20px;color:#8a94a8;font-size:13px;">感谢你在' . $site . '购买资料，以下是获取方式。</p>'
            . '<div style="padding:18px;border-radius:12px;background:#f4f7fd;">' . implode('<hr style="border:0;border-top:1px dashed #d9e4f5;margin:16px 0;">', $blocks) . '</div>'
            . '<p style="margin:20px 0 0;color:#a3acbd;font-size:12px;">资料仅限本人使用，请勿外传。也可以随时在网站「我的资料」里重新获取。</p>'
            . '</div>';
        return [$html, $attachments];
    }

    /** 大文件下载链接的签名：资料 + 用户 + 过期时间 */
    public static function signLink(int $materialId, int $userId): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['m' => $materialId, 'u' => $userId, 'e' => time() + self::LINK_TTL])), '+/', '-_'), '=');
        return $payload . '.' . hash_hmac('sha256', 'mail|' . $payload, self::secret());
    }

    /** @return array{m: int, u: int}|null 签名无效或过期返回 null */
    public static function parseLink(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', 'mail|' . $parts[0], self::secret()), $parts[1])) {
            return null;
        }
        $data = json_decode(base64_decode(strtr($parts[0], '-_', '+/')) ?: '', true);
        if (!is_array($data) || ($data['e'] ?? 0) < time()) {
            return null;
        }
        return ['m' => (int) $data['m'], 'u' => (int) $data['u']];
    }

    /** 邮件下载链接被点开：校验签名和购买关系，返回要下载的资料 */
    public function resolveLink(string $token): Material
    {
        $data = self::parseLink($token);
        if (!$data) {
            throw new \RuntimeException('下载链接无效或已过期，请到网站「我的资料」重新获取');
        }
        // 发出后退款了就不能再下
        if (!OrderService::hasPurchased($data['u'], $data['m'])) {
            throw new \RuntimeException('订单状态已变更，无法下载');
        }
        $material = Material::find($data['m']);
        if (!$material || !DeliveryService::hasFile($material)) {
            throw new \RuntimeException('资料文件不存在，请联系客服');
        }
        DownloadLog::create([
            'user_id'     => $data['u'],
            'order_id'    => 0,
            'material_id' => $material->id,
            'ip'          => request()->ip(),
        ]);
        return $material;
    }

    private static function secret(): string
    {
        $secret = (string) env('API_TOKEN_SECRET', '');
        if ($secret === '') {
            throw new \RuntimeException('API_TOKEN_SECRET 未配置');
        }
        return $secret;
    }
}
