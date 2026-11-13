<?php
namespace app;

// 应用请求对象类
class Request extends \think\Request
{
    /** 是否微信内置浏览器：微信会拦截跳往支付宝的请求，只能走微信支付 */
    public function isWechat(): bool
    {
        return (bool) preg_match('/MicroMessenger/i', $this->header('user-agent', ''));
    }

    /** 是否移动端：决定调用支付宝的手机网站支付还是电脑网站支付 */
    public function isMobileDevice(): bool
    {
        return (bool) preg_match(
            '/Android|iPhone|iPad|iPod|Mobile|HarmonyOS|Windows Phone/i',
            $this->header('user-agent', '')
        );
    }
}
