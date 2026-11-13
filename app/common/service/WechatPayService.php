<?php
namespace app\common\service;

/**
 * 微信支付 V3
 *   H5     —— 手机浏览器，直接跳转收银台
 *   JSAPI  —— 微信内网页，需要 openid，前端由 WeixinJSBridge 调起
 *   Native —— 电脑端，返回 code_url 由前端渲染成二维码供手机扫
 *
 * 请求侧：用商户私钥做 SHA256-RSA 签名，放进 Authorization 头。
 * 回调侧：报文用 APIv3 密钥做 AES-256-GCM 解密；GCM 自带认证标签，
 *        解密成功即说明报文未被篡改且对方持有 APIv3 密钥。
 */
class WechatPayService
{
    private const HOST = 'https://api.mch.weixin.qq.com';

    private array $config;

    /** @param array $config mchid / appid / key / serial_no / private_key_path / notify_url */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 配置是否填齐，缺哪项返回哪项
     * @param bool $forJsapi JSAPI 还需要 app_secret 去换 openid
     */
    public static function missingFields(array $config, bool $forJsapi = false): array
    {
        $required = ['mchid', 'appid', 'key', 'serial_no', 'private_key_path', 'notify_url'];
        if ($forJsapi) {
            $required[] = 'app_secret';
        }
        $missing = [];
        foreach ($required as $k) {
            if (trim((string) ($config[$k] ?? '')) === '') {
                $missing[] = $k;
            }
        }
        return $missing;
    }

    /**
     * H5 下单，返回微信收银台地址（有效期 5 分钟）
     *
     * @param int $amountFen 金额，单位分
     */
    public function h5Pay(string $orderNo, string $subject, int $amountFen, string $clientIp): string
    {
        if ($amountFen < 1) {
            throw new \RuntimeException('支付金额必须大于 0');
        }

        $body = [
            'appid'        => $this->config['appid'],
            'mchid'        => $this->config['mchid'],
            'description'  => mb_substr($subject, 0, 127),
            'out_trade_no' => $orderNo,
            'notify_url'   => $this->config['notify_url'],
            'amount'       => ['total' => $amountFen, 'currency' => 'CNY'],
            'scene_info'   => [
                'payer_client_ip' => $clientIp,
                'h5_info'         => ['type' => 'Wap'],
            ],
        ];

        $result = $this->request('POST', '/v3/pay/transactions/h5', $body);
        if (empty($result['h5_url'])) {
            throw new \RuntimeException('微信下单未返回支付链接：' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $result['h5_url'];
    }

    /**
     * Native 下单（电脑端扫码），返回 code_url
     * 这是一个 weixin:// 开头的字符串，需要前端渲染成二维码
     *
     * @param int $amountFen 金额，单位分
     */
    public function nativePay(string $orderNo, string $subject, int $amountFen): string
    {
        if ($amountFen < 1) {
            throw new \RuntimeException('支付金额必须大于 0');
        }

        $result = $this->request('POST', '/v3/pay/transactions/native', [
            'appid'        => $this->config['appid'],
            'mchid'        => $this->config['mchid'],
            'description'  => mb_substr($subject, 0, 127),
            'out_trade_no' => $orderNo,
            'notify_url'   => $this->config['notify_url'],
            'amount'       => ['total' => $amountFen, 'currency' => 'CNY'],
        ]);

        if (empty($result['code_url'])) {
            throw new \RuntimeException('微信下单未返回二维码链接：' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $result['code_url'];
    }

    /**
     * JSAPI 下单（微信内网页/小程序），返回 prepay_id
     *
     * @param int $amountFen 金额，单位分
     */
    public function jsapiPay(string $orderNo, string $subject, int $amountFen, string $openid): string
    {
        if ($amountFen < 1) {
            throw new \RuntimeException('支付金额必须大于 0');
        }

        $result = $this->request('POST', '/v3/pay/transactions/jsapi', [
            'appid'        => $this->config['appid'],
            'mchid'        => $this->config['mchid'],
            'description'  => mb_substr($subject, 0, 127),
            'out_trade_no' => $orderNo,
            'notify_url'   => $this->config['notify_url'],
            'amount'       => ['total' => $amountFen, 'currency' => 'CNY'],
            'payer'        => ['openid' => $openid],
        ]);

        if (empty($result['prepay_id'])) {
            throw new \RuntimeException('微信下单未返回 prepay_id：' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        return $result['prepay_id'];
    }

    /**
     * 生成前端调起支付所需的参数（对 prepay_id 二次签名）
     * 签名串：appId\n时间戳\n随机串\nprepay_id 包\n
     */
    public function jsapiInvokeParams(string $prepayId): array
    {
        $appId     = (string) $this->config['appid'];
        $timeStamp = (string) time();
        $nonceStr  = strtoupper(bin2hex(random_bytes(16)));
        $package   = 'prepay_id=' . $prepayId;

        $message = $appId . "\n" . $timeStamp . "\n" . $nonceStr . "\n" . $package . "\n";
        openssl_sign($message, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256);

        return [
            'appId'     => $appId,
            'timeStamp' => $timeStamp,
            'nonceStr'  => $nonceStr,
            'package'   => $package,
            'signType'  => 'RSA',
            'paySign'   => base64_encode($signature),
        ];
    }

    /**
     * 用网页授权的 code 换 openid
     * 走的是公众号接口，不是商户接口，不需要商户签名
     */
    public function openidByCode(string $code): string
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?' . http_build_query([
            'appid'      => $this->config['appid'],
            'secret'     => $this->config['app_secret'] ?? '',
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);

        if ($response === false) {
            throw new \RuntimeException('获取微信身份失败：' . $error);
        }
        $data = json_decode((string) $response, true) ?: [];
        if (empty($data['openid'])) {
            throw new \RuntimeException('获取微信身份失败：' . ($data['errmsg'] ?? (string) $response));
        }
        return (string) $data['openid'];
    }

    /** 静默网页授权地址，用户无感知，只拿 openid */
    public function oauthUrl(string $redirectUri, string $state): string
    {
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query([
            'appid'         => $this->config['appid'],
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'snsapi_base',
            'state'         => $state,
        ]) . '#wechat_redirect';
    }

    /**
     * 查询订单（按商户订单号）。
     * 也用于配置自检：拿一个不存在的订单号去查，
     * 返回 ORDER_NOT_EXIST 就说明商户号、证书序列号、私钥三者是匹配的。
     */
    public function queryOrder(string $orderNo): array
    {
        $path = '/v3/pay/transactions/out-trade-no/' . rawurlencode($orderNo)
              . '?mchid=' . rawurlencode((string) $this->config['mchid']);
        return $this->request('GET', $path, []);
    }

    /**
     * 解密回调报文，返回交易数据；解密失败说明报文伪造或密钥不对
     */
    public function decryptNotify(array $notify): array
    {
        $resource = $notify['resource'] ?? [];
        if (($resource['algorithm'] ?? '') !== 'AEAD_AES_256_GCM') {
            throw new \RuntimeException('不支持的回调加密算法');
        }

        $ciphertext = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
        if ($ciphertext === false || strlen($ciphertext) <= 16) {
            throw new \RuntimeException('回调密文格式错误');
        }

        // GCM 认证标签固定为末尾 16 字节
        $tag  = substr($ciphertext, -16);
        $data = substr($ciphertext, 0, -16);

        $plain = openssl_decrypt(
            $data,
            'aes-256-gcm',
            (string) $this->config['key'],
            OPENSSL_RAW_DATA,
            (string) ($resource['nonce'] ?? ''),
            $tag,
            (string) ($resource['associated_data'] ?? '')
        );
        if ($plain === false) {
            throw new \RuntimeException('回调解密失败，APIv3 密钥不匹配或报文被篡改');
        }

        $decoded = json_decode($plain, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('回调内容不是合法 JSON');
        }
        return $decoded;
    }

    // ── 私有方法 ─────────────────────────────────────────────────────────

    /** 目标地址是硬编码的微信网关，不接受外部传入，不存在 SSRF */
    private function request(string $method, string $path, array $body): array
    {
        $json = $body ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

        $ch = curl_init(self::HOST . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: banbanle-php',
                'Authorization: ' . $this->authorization($method, $path, $json),
            ],
        ]);
        if ($json !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($response === false) {
            throw new \RuntimeException('请求微信支付失败：' . $error);
        }

        $decoded = json_decode((string) $response, true) ?: [];
        if ($status < 200 || $status >= 300) {
            $msg = $decoded['message'] ?? (string) $response;
            throw new \RuntimeException("微信支付返回错误（HTTP {$status}）：{$msg}");
        }
        return $decoded;
    }

    /** 组装 Authorization 头 */
    private function authorization(string $method, string $path, string $body): string
    {
        $nonce     = strtoupper(bin2hex(random_bytes(16)));
        $timestamp = (string) time();

        // 签名串各字段以 \n 分隔，末尾也要有一个 \n
        $message = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n";

        openssl_sign($message, $signature, $this->privateKey(), OPENSSL_ALGO_SHA256);

        return sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            $this->config['mchid'],
            $nonce,
            base64_encode($signature),
            $timestamp,
            $this->config['serial_no']
        );
    }

    /** 读取商户私钥文件（apiclient_key.pem） */
    private function privateKey(): \OpenSSLAsymmetricKey
    {
        $path = (string) ($this->config['private_key_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new \RuntimeException('商户私钥文件不存在：' . $path);
        }
        $key = openssl_pkey_get_private(file_get_contents($path));
        if (!$key) {
            throw new \RuntimeException('商户私钥格式错误，请确认是 apiclient_key.pem');
        }
        return $key;
    }
}
