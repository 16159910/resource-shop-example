<?php
namespace app\middleware;

use think\Request;
use think\Response;

/**
 * CSRF 校验：写操作请求必须携带与 session 一致的 token
 * （Header X-CSRF-TOKEN 或参数 __token__），token 由页面渲染时注入。
 * 不用框架自带 checkToken：它验证后即销毁 token，同一页面多次 Ajax 会失败。
 */
class CsrfCheck
{
    /** 外部服务器回调，无 session，跳过校验 */
    protected array $except = [
        'pay/notify',
        'pay/notify/alipay',
        'pay/notify/wechat',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $path = trim($request->pathinfo(), '/');
        // 小程序 API 用 Bearer Token 鉴权、不带 Cookie，CSRF 不适用
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])
            && !in_array($path, $this->except)
        ) {
            $sessionToken = (string) session('__token__');
            $given = (string) ($request->header('X-CSRF-TOKEN') ?: $request->param('__token__', ''));
            if ($sessionToken === '' || $given === '' || !hash_equals($sessionToken, $given)) {
                return json(['code' => 419, 'msg' => '页面已过期，请刷新后重试', 'data' => null]);
            }
        }
        return $next($request);
    }
}
