<?php
// +----------------------------------------------------------------------
// | Cookie设置
// +----------------------------------------------------------------------
return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => '',
    //  cookie 启用安全传输：线上 HTTPS 开启，本地 http 调试会导致 Cookie 发不出去，
    //  所以走环境变量控制（生产 .env 里设 COOKIE_SECURE = true）
    'secure'    => (bool) env('COOKIE_SECURE', false),
    // httponly：禁止 JS 读取会话 Cookie，万一有 XSS 也偷不走登录态
    'httponly'  => true,
    // 是否使用 setcookie
    'setcookie' => true,
    // samesite：跨站请求不带 Cookie，配合 CSRF token 双重防护。
    // 用 lax 而不是 strict，保证从公众号文章、外链跳进来时登录态还在
    'samesite'  => 'lax',
];
