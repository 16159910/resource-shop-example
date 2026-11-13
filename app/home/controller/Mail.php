<?php
namespace app\home\controller;

use app\common\response\StreamFile;
use app\common\service\DeliveryService;
use app\common\service\MaterialMailService;

class Mail extends Base
{
    /** GET /mail/file?t=  邮件里的大文件下载链接 */
    public function file(): \think\Response
    {
        try {
            $material = (new MaterialMailService())->resolveLink((string) input('get.t', ''));
            $path     = DeliveryService::resolveFile($material);
        } catch (\RuntimeException $e) {
            // 从邮箱里点开的是浏览器，给一页能看懂的文字而不是 JSON
            return response('<meta name="viewport" content="width=device-width,initial-scale=1"><div style="padding:48px 24px;text-align:center;font-family:sans-serif;color:#4b5568;">'
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>', 403);
        }
        return new StreamFile($path, (string) ($material->file_name ?: ''));
    }
}
