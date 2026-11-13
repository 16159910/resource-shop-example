<?php
namespace app\common\response;

use think\Response;

/**
 * 文件下载响应。
 *
 * 不用框架自带的 download()，它有两个坑：
 *   1. 用 finfo_file() 探测 MIME，服务器没装 fileinfo 扩展就是一个 Error；
 *   2. 用 file_get_contents() 把整个文件读进内存，大文件直接撑爆 memory_limit。
 * 而 Response::send() 外面包了 try/catch，异常只写日志不往外抛，
 * 于是用户那头看到的是一片白屏，什么提示都没有。
 *
 * 这里改成 readfile 流式吐给浏览器，MIME 一律按二进制流给——
 * 下载场景本来就不需要精确类型。
 */
class StreamFile extends Response
{
    protected string $filePath;

    public function __construct(string $filePath, string $name = '')
    {
        $this->filePath = $filePath;
        $this->init('', 200);

        $name    = $name !== '' ? $name : basename($filePath);
        $encoded = rawurlencode($name);

        // filename 给老浏览器兜底，filename* 才是带中文名的那份
        $this->header = [
            'Content-Type'              => 'application/octet-stream',
            'Content-Length'            => (string) filesize($filePath),
            'Content-Disposition'       => 'attachment; filename="' . $encoded . '"; filename*=UTF-8\'\'' . $encoded,
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control'             => 'private, max-age=0, must-revalidate',
            'Pragma'                    => 'public',
        ];
    }

    protected function sendData(string $data): void
    {
        // 缓冲不关掉的话，readfile 吐出来的内容照样会在内存里堆起来
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($this->filePath);
    }
}
