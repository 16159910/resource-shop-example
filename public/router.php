<?php
// PHP 内置服务器路由文件
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

if (is_file($file)) {
    // 内置服务器不发缓存头，切页时 CSS/图片会全部重新拉一遍，造成本地才有的闪烁。
    // 直接由路由文件带缓存头输出（return false 交还给内置服务器会丢掉这些头），
    // 缓存策略对齐 deploy/nginx.conf.example。
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = [
        'css'  => 'text/css',                  'js'   => 'application/javascript',
        'jpg'  => 'image/jpeg',                'jpeg' => 'image/jpeg',
        'png'  => 'image/png',                 'gif'  => 'image/gif',
        'webp' => 'image/webp',                'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',              'woff' => 'font/woff',
        'woff2' => 'font/woff2',               'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
    ][$ext] ?? null;

    if ($mime !== null) {
        $maxAge = in_array($ext, ['woff', 'woff2', 'ttf', 'eot'], true) ? 31536000 : 2592000;
        header('Content-Type: ' . $mime . (str_starts_with($mime, 'text/') ? '; charset=UTF-8' : ''));
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        return true;
    }
    return false;
}

require __DIR__ . '/index.php';
