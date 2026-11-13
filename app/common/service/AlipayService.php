<?php
namespace app\common\service;

class AlipayService
{
    private array  $config;
    private string $gateway;

    public function __construct(array $config)
    {
        $this->config  = $config;
        $this->gateway = !empty($config['sandbox'])
            ? 'https://openapi-sandbox.dl.alipaydev.com/gateway.do'
            : 'https://openapi.alipay.com/gateway.do';
    }

    /** 构建 PC 网站支付表单 HTML（提交后跳转支付宝） */
    public function buildPcPayForm(string $orderNo, string $subject, string $amount, string $returnUrl = ''): string
    {
        $params = $this->buildParams('alipay.trade.page.pay', [
            'out_trade_no' => $orderNo,
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'total_amount' => $amount,
            'subject'      => $subject,
        ], $returnUrl);

        return $this->toFormHtml($params);
    }


    /** 构建 H5 WAP 支付表单 HTML，避免查询参数被内置浏览器截断或丢失 */
    public function buildH5PayForm(string $orderNo, string $subject, string $amount, string $returnUrl = ''): string
    {
        $params = $this->buildParams('alipay.trade.wap.pay', [
            'out_trade_no' => $orderNo,
            'product_code' => 'QUICK_WAP_WAY',
            'total_amount' => $amount,
            'subject'      => $subject,
        ], $returnUrl);

        return $this->toFormHtml($params);
    }

    /**
     * 调用服务端 API（非页面跳转类接口）
     * 也用于配置自检：查一个不存在的订单，返回「交易不存在」就说明签名是对的
     */
    public function apiCall(string $method, array $bizContent): array
    {
        $params = $this->buildParams($method, $bizContent, '');

        $ch = curl_init($this->gateway . '?charset=utf-8');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);

        if ($response === false) {
            throw new \RuntimeException('请求支付宝网关失败：' . $error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('支付宝返回内容无法解析：' . substr((string) $response, 0, 200));
        }
        // 响应体键名形如 alipay_trade_query_response
        $key = str_replace('.', '_', $method) . '_response';
        return $decoded[$key] ?? $decoded;
    }

    /** 从配置的应用私钥推导出应用公钥，用于和开放平台上传的公钥比对 */
    public function derivePublicKey(): string
    {
        $key = openssl_pkey_get_private($this->formatKey($this->config['private_key'], 'private'));
        if (!$key) {
            throw new \RuntimeException('应用私钥无法解析');
        }
        $pem = openssl_pkey_get_details($key)['key'] ?? '';
        return trim(str_replace(
            ['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----', "\n", "\r"],
            '',
            $pem
        ));
    }

    /** 验证支付宝异步通知签名 */
    public function verifyNotify(array $params): bool
    {
        if (empty($params['sign'])) {
            return false;
        }
        $sign = base64_decode($params['sign']);
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $str = $this->paramsToString($params);

        $pubKey = $this->formatKey($this->config['alipay_public_key'], 'public');
        $res    = openssl_get_publickey($pubKey);
        return $res && openssl_verify($str, $sign, $res, OPENSSL_ALGO_SHA256) === 1;
    }

    // ── 私有方法 ─────────────────────────────────────────────────────────

    private function buildParams(string $method, array $bizContent, string $returnUrl): array
    {
        $params = [
            'app_id'      => $this->config['app_id'],
            'method'      => $method,
            'format'      => 'JSON',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'notify_url'  => $this->config['notify_url'] ?? '',
            'return_url'  => $returnUrl ?: ($this->config['return_url'] ?? ''),
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];
        ksort($params);
        $params['sign'] = $this->sign($this->paramsToString($params));
        return $params;
    }

    private function sign(string $str): string
    {
        $privateKey = $this->formatKey($this->config['private_key'], 'private');
        $res        = openssl_get_privatekey($privateKey);
        if (!$res) {
            throw new \RuntimeException('支付宝私钥格式错误');
        }
        openssl_sign($str, $sign, $res, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    private function formatKey(string $key, string $type): string
    {
        // 已是完整 PEM 格式（带 BEGIN/END 头）时原样使用，不能清洗（会破坏头部）
        if (str_contains($key, '-----')) {
            return trim($key);
        }
        $key = trim(str_replace(["\r", "\n", ' '], '', $key));
        $wrapped = wordwrap($key, 64, "\n", true);
        if ($type === 'private') {
            // 支付宝新版密钥为 PKCS8
            return "-----BEGIN PRIVATE KEY-----\n{$wrapped}\n-----END PRIVATE KEY-----";
        }
        return "-----BEGIN PUBLIC KEY-----\n{$wrapped}\n-----END PUBLIC KEY-----";
    }

    private function paramsToString(array $params): string
    {
        $parts = [];
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null) {
                $parts[] = $k . '=' . $v;
            }
        }
        return implode('&', $parts);
    }

    private function toFormHtml(array $params): string
    {
        if (empty($params['method'])) {
            throw new \RuntimeException('支付宝请求缺少 method 参数');
        }
        $inputs = '';
        foreach ($params as $k => $v) {
            $inputs .= '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8')
                     . '" value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '">';
        }

        // charset 必须在页面里显式声明：只靠 HTTP 响应头的话，
        // 一旦被网关改写，浏览器可能用 GBK 提交表单，中文参数编码与签名不符，
        // 支付宝就会报 invalid-signature。form 上的 accept-charset 是第二道保险。
        // 网关地址必须带上 ?charset=utf-8：
        // 支付宝先从 URL 查询串读 charset，再用它解码 POST 过来的表单，
        // 读不到就按 GBK 解，中文商品名会变成乱码并导致验签失败。
        $action = $this->gateway . '?charset=utf-8';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'
            . '<form id="f" accept-charset="utf-8" method="POST" action="'
            . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">' . $inputs . '</form>'
            . '<script>document.getElementById("f").submit();</script>'
            . '</body></html>';
    }
}
