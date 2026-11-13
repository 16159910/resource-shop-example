<?php
namespace app\common\service;

use app\common\model\Order;
use app\common\model\User;

/**
 * 企业微信群机器人通知。
 *
 * 订单动静实时推到群里，不用盯后台。通知只是锦上添花，
 * 任何失败都不能影响下单和支付主流程 —— 所以这里全程不抛异常，
 * 出错只写日志。
 */
class WecomNotifyService
{
    /** 企业微信机器人固定域名，只认它，避免后台被填成别的地址 */
    private const ALLOW_HOST = 'qyapi.weixin.qq.com';

    public static function webhook(): string
    {
        return trim((string) SystemConfigService::get('wecom_webhook', ''));
    }

    public static function enabled(string $event): bool
    {
        if (self::webhook() === '') {
            return false;
        }
        $key = $event === 'paid' ? 'wecom_notify_paid' : 'wecom_notify_pending';
        return (string) SystemConfigService::get($key, '1') === '1';
    }

    /** 新订单（待付款） */
    public static function orderCreated(Order $order): void
    {
        if (!self::enabled('pending')) {
            return;
        }
        // 免费资料下单即完成，没有「待付款」这回事
        if ((float) $order->amount <= 0) {
            return;
        }
        self::send("### 🛒 新订单（待付款）\n" . self::orderLines($order));
    }

    /** 订单已付款 */
    public static function orderPaid(Order $order): void
    {
        if (!self::enabled('paid')) {
            return;
        }
        self::send("### ✅ 订单已付款\n" . self::orderLines($order, true));
    }



    /**
     * 发一条 markdown 消息。
     *
     * @return bool 是否发送成功，调用方通常不用关心
     */
    public static function send(string $markdown): bool
    {
        $url = self::webhook();
        if ($url === '') {
            return false;
        }
        if (!self::isValidWebhook($url)) {
            LogService::record('通知', '配置异常', '企业微信 webhook 地址不合法，已跳过推送');
            return false;
        }

        $body = json_encode([
            'msgtype'  => 'markdown',
            'markdown' => ['content' => $markdown],
        ], JSON_UNESCAPED_UNICODE);

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                // 通知不能拖慢下单，超时压到几秒
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false, // 跟跳转会绕开前面的地址校验
            ]);
            $response = curl_exec($ch);
            $error    = curl_error($ch);

            if ($response === false) {
                LogService::record('通知', '推送失败', '企业微信：' . $error);
                return false;
            }
            $data = json_decode((string) $response, true) ?: [];
            if ((int) ($data['errcode'] ?? -1) !== 0) {
                LogService::record('通知', '推送失败', '企业微信：' . ($data['errmsg'] ?? $response));
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            // 通知失败绝不能把下单/支付带崩
            LogService::record('通知', '推送异常', '企业微信：' . $e->getMessage());
            return false;
        }
    }

    /** 必须是公网 https，且只认企业微信机器人自己的域名 */
    public static function isValidWebhook(string $url): bool
    {
        if (!UrlSecurityService::isPublicHttpUrl($url)) {
            return false;
        }
        $parts = parse_url($url);
        return strtolower($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === self::ALLOW_HOST;
    }

    private static function orderLines(Order $order, bool $paid = false): string
    {
        return implode("\n", [
            '> 名称：' . $order->title,
            '> 金额：¥' . number_format((float) $order->amount, 2, '.', ''),
            '> 单号：' . $order->order_no,
            '> 时间：' . date('Y-m-d H:i:s'),
        ]);
    }
}
