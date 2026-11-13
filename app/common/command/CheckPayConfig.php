<?php
namespace app\common\command;

use app\common\model\PaymentConfig;
use app\common\service\AlipayService;
use app\common\service\WechatPayService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 支付配置自检：填完后台之后跑一次，别等真实用户付款时才发现配错了
 */
class CheckPayConfig extends Command
{
    protected function configure(): void
    {
        $this->setName('pay:check')
             ->setDescription('检查支付宝/微信支付配置是否填写正确');
    }

    protected function execute(Input $input, Output $output): void
    {
        $this->checkAlipay($output);
        $output->writeln('');
        $this->checkWechat($output);
    }

    private function checkAlipay(Output $output): void
    {
        $output->writeln('<info>【支付宝】</info>');

        $record = PaymentConfig::where('type', 1)->find();
        if (!$record) {
            $output->writeln('  未配置');
            return;
        }
        $output->writeln('  状态：' . ((int) $record->status === 1 ? '已启用' : '<comment>已禁用</comment>'));

        $config = json_decode($record->config, true) ?: [];
        foreach (['app_id' => 'APPID', 'private_key' => '应用私钥', 'alipay_public_key' => '支付宝公钥'] as $k => $label) {
            $filled = trim((string) ($config[$k] ?? '')) !== '';
            $output->writeln(sprintf('  %-14s %s', $label, $filled ? '已填' : '<error>未填</error>'));
        }

        // 私钥能不能被 openssl 解析
        if (trim((string) ($config['private_key'] ?? '')) !== '') {
            // 直接用 openssl 验私钥，不必反射调用私有方法
            $pem = trim((string) $config['private_key']);
            if (!str_contains($pem, '-----')) {
                $pem = "-----BEGIN PRIVATE KEY-----\n"
                     . wordwrap(str_replace(["\r", "\n", ' '], '', $pem), 64, "\n", true)
                     . "\n-----END PRIVATE KEY-----";
            }
            $output->writeln('  私钥格式       ' . (openssl_pkey_get_private($pem)
                ? '<info>可用</info>'
                : '<error>无法解析，请确认是应用私钥（PKCS8）</error>'));
        }

        $notify = (string) ($config['notify_url'] ?? '');
        $output->writeln('  回调地址       ' . ($notify ?: '<error>未填</error>'));
        if ($notify !== '' && !str_starts_with($notify, 'https://')) {
            $output->writeln('    <comment>建议使用 https</comment>');
        }

        if (trim((string) ($config['app_id'] ?? '')) === '' || trim((string) ($config['private_key'] ?? '')) === '') {
            $output->writeln('  <comment>字段不全，跳过联网校验</comment>');
            return;
        }

        // 查一个不存在的订单：签名对了会说「交易不存在」，签名错了会说 invalid-signature
        $output->writeln('  正在联网校验签名…');
        try {
            $svc = new AlipayService($config);
            $res = $svc->apiCall('alipay.trade.query', ['out_trade_no' => 'PAYCHECK' . time()]);
            $sub = (string) ($res['sub_code'] ?? '');
            $msg = trim(($res['msg'] ?? '') . ' ' . ($res['sub_msg'] ?? ''));

            if ($sub === 'ACQ.TRADE_NOT_EXIST') {
                $output->writeln('  <info>联网校验：通过（APPID 与应用私钥匹配，签名正确）</info>');
            } elseif (str_contains($sub, 'invalid-signature')) {
                $output->writeln('  <error>联网校验：验签失败</error>');
                $this->explainSignatureFailure($svc, $config, $output);
            } elseif ($sub !== '') {
                $output->writeln('  <error>联网校验：' . $sub . ' ' . $msg . '</error>');
            } else {
                $output->writeln('  <info>联网校验：通过</info>');
            }
        } catch (\Throwable $e) {
            $output->writeln('  <error>联网校验：' . $e->getMessage() . '</error>');
        }
    }

    /** 验签失败时，把可能的原因和自查方法一次说清楚 */
    private function explainSignatureFailure(AlipayService $svc, array $config, Output $output): void
    {
        $output->writeln('');
        $output->writeln('  <comment>可能原因（按概率排序）：</comment>');
        $output->writeln('  1. 后台填的应用私钥，与开放平台上传的应用公钥不是同一对');
        $output->writeln('  2. 应用在开放平台用的是「公钥证书」模式，而不是「公钥」模式');
        $output->writeln('  3. APPID 填错，或填成了其他应用的 APPID');
        $output->writeln('');

        try {
            $pub = $svc->derivePublicKey();
            $output->writeln('  <comment>拿下面这段去比对开放平台「接口加签方式 → 应用公钥」：</comment>');
            foreach (str_split($pub, 64) as $line) {
                $output->writeln('    ' . $line);
            }
            $output->writeln('');
            $output->writeln('  两者不一致就是原因 1，把这段公钥重新传到开放平台即可。');
        } catch (\Throwable $e) {
            $output->writeln('  <error>无法从私钥推导公钥：' . $e->getMessage() . '</error>');
        }
    }

    private function checkWechat(Output $output): void
    {
        $output->writeln('<info>【微信支付】</info>');

        $record = PaymentConfig::where('type', 2)->find();
        if (!$record) {
            $output->writeln('  未配置');
            return;
        }
        $output->writeln('  状态：' . ((int) $record->status === 1 ? '已启用' : '<comment>已禁用</comment>'));

        $config = json_decode($record->config, true) ?: [];
        $labels = [
            'mchid'            => '商户号',
            'appid'            => 'APPID',
            'key'              => 'APIv3密钥',
            'serial_no'        => '证书序列号',
            'private_key_path' => '私钥路径',
            'notify_url'       => '回调地址',
        ];
        foreach ($labels as $k => $label) {
            $v = trim((string) ($config[$k] ?? ''));
            $output->writeln(sprintf('  %-14s %s', $label, $v !== '' ? '已填' : '<error>未填</error>'));
        }
        $output->writeln(sprintf(
            '  %-14s %s',
            'AppSecret',
            trim((string) ($config['app_secret'] ?? '')) !== '' ? '已填' : '未填（只有微信内 JSAPI 需要）'
        ));

        // 格式校验
        if (($v = trim((string) ($config['key'] ?? ''))) !== '' && strlen($v) !== 32) {
            $output->writeln('    <error>APIv3 密钥应为 32 位，当前 ' . strlen($v) . ' 位</error>');
        }
        if (($v = trim((string) ($config['serial_no'] ?? ''))) !== '' && !preg_match('/^[0-9A-Fa-f]{40}$/', $v)) {
            $output->writeln('    <comment>证书序列号通常是 40 位十六进制，请确认没填成商户号</comment>');
        }

        // 私钥文件
        $keyPath = (string) ($config['private_key_path'] ?? '');
        if ($keyPath !== '') {
            if (!is_file($keyPath)) {
                $output->writeln('    <error>私钥文件不存在：' . $keyPath . '</error>');
            } elseif (!is_readable($keyPath)) {
                $output->writeln('    <error>私钥文件不可读，检查权限</error>');
            } elseif (!openssl_pkey_get_private(file_get_contents($keyPath))) {
                $output->writeln('    <error>私钥格式错误，应为 apiclient_key.pem</error>');
            } else {
                $output->writeln('  私钥文件       <info>可读且格式正确</info>');
                if (str_starts_with(realpath($keyPath) ?: '', rtrim(public_path(), '/'))) {
                    $output->writeln('    <error>私钥放在了 public 目录下，可能被公网下载，请移到 protected/</error>');
                }
            }
        }

        // 真刀真枪调一次微信接口，验证商户号+序列号+私钥是否匹配
        if (WechatPayService::missingFields($config) !== []) {
            $output->writeln('  <comment>字段不全，跳过联网校验</comment>');
            return;
        }
        $output->writeln('  正在联网校验签名…');
        try {
            (new WechatPayService($config))->queryOrder('PAYCHECK' . time());
            $output->writeln('  <info>联网校验：通过</info>');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'ORDER_NOT_EXIST') || str_contains($msg, '订单不存在')) {
                // 正是期望的结果：签名通过了，只是订单确实不存在
                $output->writeln('  <info>联网校验：通过（商户号、证书序列号、私钥三者匹配）</info>');
            } elseif (str_contains($msg, '401')) {
                $output->writeln('  <error>联网校验：签名失败 —— 证书序列号或私钥与商户号不匹配</error>');
            } else {
                $output->writeln('  <error>联网校验：' . $msg . '</error>');
            }
        }
    }
}
