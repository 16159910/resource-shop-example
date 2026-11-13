<?php
namespace app\home\controller;

use app\common\model\Order;
use app\common\model\PaymentConfig;
use app\common\service\AlipayService;
use app\common\service\LogService;
use app\common\service\WechatPayService;
use app\home\service\OrderService;
use app\home\service\PayService;

class Pay extends Base
{
    /**
     * 渠道在当前环境是否可用。
     *   微信内    —— 只有微信 JSAPI；微信会拦截跳往支付宝的请求
     *   手机浏览器 —— 支付宝 wap + 微信 H5
     *   电脑      —— 支付宝 page + 微信 Native 扫码（需商户已开通 Native）
     */
    private function channelAvailable(int $type, bool $inWechat, bool $isMobile, array $wechatCap): bool
    {
        if ($type === 1) {
            return !$inWechat; // 支付宝：微信内跳不出去
        }
        if ($type !== 2) {
            return false; // 小程序虚拟支付等渠道不在网页收银台露出
        }
        if ($inWechat) {
            return $wechatCap['jsapi'];
        }
        return $isMobile ? $wechatCap['h5'] : $wechatCap['native'];
    }

    /**
     * 微信支付三种场景各自是否具备可用条件。
     * 配置没填齐就不在支付页暴露入口，避免用户点进去撞到配置类报错。
     */
    private function wechatCapability(): array
    {
        $record = PaymentConfig::where('type', 2)->where('status', 1)->find();
        if (!$record) {
            return ['h5' => false, 'native' => false, 'jsapi' => false];
        }
        $config  = json_decode($record->config, true) ?: [];
        $baseOk  = WechatPayService::missingFields($config, false) === [];
        return [
            'h5'     => $baseOk,
            'native' => $baseOk && !empty($config['native_enabled']),
            'jsapi'  => WechatPayService::missingFields($config, true) === [],
        ];
    }

    /** POST /pay/submit  发起支付，返回 JSON */
    public function submit(): \think\Response
    {
        $this->requireLogin();
        $orderNo = input('post.order_no', '');
        $channel = (int) input('post.channel', 1); // 1=支付宝 2=微信
        // 前端会按 UA 传 pc|h5，没传或传了非法值时以服务端 UA 判断为准
        $device  = input('post.device', '');
        if (!in_array($device, ['pc', 'h5'], true)) {
            $device = request()->isMobileDevice() ? 'h5' : 'pc';
        }

        OrderService::closeExpired($this->userId); // 惰性关闭，不依赖定时任务
        $order = Order::where('order_no', $orderNo)->where('user_id', $this->userId)->find();
        if (!$order) {
            return $this->error('订单不存在');
        }
        if ($order->pay_status == 1) {
            return $this->error('订单已支付');
        }
        if ($order->status == 2) {
            return $this->error('订单已关闭，请重新下单');
        }

        if ($channel === 1 && request()->isWechat()) {
            return $this->error('微信内无法跳转支付宝，请点右上角「···」选择在浏览器中打开');
        }
        if ($channel === 2 && !$this->channelAvailable(2, request()->isWechat(), request()->isMobileDevice(), $this->wechatCapability())) {
            return $this->error('当前环境暂不支持微信支付，请选择其它方式');
        }

        $payConfig = PaymentConfig::where('type', $channel)->where('status', 1)->find();
        if (!$payConfig) {
            return $this->error('该支付渠道未启用');
        }

        $config  = json_decode($payConfig->config, true);
        $amount  = number_format((float) $order->amount, 2, '.', '');
        $subject = mb_substr($order->title, 0, 50);

        // 同步回跳地址：带上订单号，方便页面展示
        $returnUrl = (string) url('/pay/return', [], false, true);

        try {
            if ($channel === 1) { // 支付宝
                // 电脑端和手机端统一由本站独立页面直接提交签名表单，避免支付参数
                // 经 URL、JSON 或内置浏览器传输时丢失，导致支付宝提示 missing-method。
                return $this->success([
                    'type' => 'redirect',
                    'url'  => '/pay/alipay?order_no=' . rawurlencode($orderNo)
                        . '&device=' . ($device === 'h5' ? 'h5' : 'pc'),
                ]);
            }
            if ($channel === 2) { // 微信 H5
                return $this->success([
                    'type' => 'redirect',
                    'url'  => '/pay/wechat?order_no=' . rawurlencode($orderNo),
                ]);
            }
            return $this->error('暂不支持该支付方式');
        } catch (\Throwable $e) {
            return $this->error('支付初始化失败：' . $e->getMessage());
        }
    }

    /**
     * GET /pay/wechat?order_no=xxx  发起微信支付
     * 微信内走 JSAPI（需要 openid），手机浏览器走 H5（直接跳收银台）
     */
    public function wechat(): \think\Response
    {
        $this->requireLogin();
        $orderNo = input('get.order_no', '');
        OrderService::closeExpired($this->userId);
        $order   = Order::where('order_no', $orderNo)->where('user_id', $this->userId)->find();
        if (!$order || (int) $order->pay_status === 1 || (int) $order->status === 2) {
            return redirect('/user/orders');
        }

        $inWechat = request()->isWechat();
        [$config, $error] = $this->wechatConfig($inWechat);
        if ($error !== null) {
            return $error;
        }

        $amountFen = (int) round((float) $order->amount * 100); // 微信金额单位是分
        $subject   = mb_substr($order->title, 0, 50);
        $service   = new WechatPayService($config);

        try {
            if (!$inWechat && !request()->isMobileDevice()) {
                // 电脑端：拿 code_url 渲染成二维码让用户扫
                return $this->view('pay/wechat_native', [
                    'order'   => $order->toArray(),
                    'codeUrl' => $service->nativePay($orderNo, $subject, $amountFen),
                ]);
            }
            if (!$inWechat) {
                return redirect($service->h5Pay($orderNo, $subject, $amountFen, request()->ip()));
            }

            // JSAPI 必须带 openid，没有就先做一次静默网页授权
            $openid = (string) session('wx_openid');
            if ($openid === '') {
                $redirectUri = (string) url('/pay/wechat/oauth', [], true, true);
                return redirect($service->oauthUrl($redirectUri, $orderNo));
            }

            $prepayId = $service->jsapiPay($orderNo, $subject, $amountFen, $openid);
            return $this->view('pay/wechat_jsapi', [
                'order'     => $order->toArray(),
                'payParams' => $service->jsapiInvokeParams($prepayId),
            ]);
        } catch (\Throwable $e) {
            LogService::record('订单', '微信下单失败', '订单号：' . $orderNo . '，原因：' . $e->getMessage());
            return $this->error('支付初始化失败：' . $e->getMessage());
        }
    }

    /**
     * GET /pay/channels?order_no=xxx
     * 支付弹窗用：返回订单摘要和当前环境可用的支付方式
     */
    public function channels(): \think\Response
    {
        $this->requireLogin();
        OrderService::closeExpired($this->userId);
        $order = Order::where('order_no', input('get.order_no', ''))
            ->where('user_id', $this->userId)
            ->find();
        if (!$order) {
            return $this->error('订单不存在');
        }
        if ((int) $order->pay_status === 1) {
            return $this->error('订单已支付');
        }
        if ((int) $order->status === 2) {
            return $this->error('订单已关闭，请重新下单');
        }

        $inWechat  = request()->isWechat();
        $isMobile  = request()->isMobileDevice();
        $wechatCap = $this->wechatCapability();

        $list = [];
        foreach (PaymentConfig::where('status', 1)->whereIn('type', [1, 2])->select() as $c) {
            $type = (int) $c->type;
            if (!$this->channelAvailable($type, $inWechat, $isMobile, $wechatCap)) {
                continue;
            }
            $list[] = [
                'type' => $type,
                'name' => $type === 1 ? '支付宝' : '微信支付',
                'icon' => $type === 1 ? 'fa-brands fa-alipay' : 'fa-brands fa-weixin',
            ];
        }

        return $this->success([
            'order' => [
                'order_no' => $order->order_no,
                'title'    => $order->title,
                'amount'   => number_format((float) $order->amount, 2, '.', ''),
                'cover'    => $order->cover ?: '',
            ],
            'channels' => $list,
            'in_wechat' => $inWechat,
        ]);
    }

    /** GET /pay/status?order_no=xxx  供扫码页轮询支付结果 */
    public function status(): \think\Response
    {
        $this->requireLogin();
        $orderNo = input('get.order_no', '');
        // 轮询时顺带补单，异步通知丢了也能在这里把订单捞回来（内部有节流）
        PayService::syncAlipay($orderNo);
        OrderService::closeExpired($this->userId);
        $order = Order::where('order_no', $orderNo)
            ->where('user_id', $this->userId)
            ->find();
        if (!$order) {
            return $this->error('订单不存在');
        }
        return $this->success([
            'pay_status' => (int) $order->pay_status,
            'closed'     => (int) $order->status === 2,
        ]);
    }

    /** GET /pay/wechat/oauth  网页授权回跳，用 code 换 openid 后回到支付流程 */
    public function wechatOauth(): \think\Response
    {
        $this->requireLogin();
        $code    = input('get.code', '');
        $orderNo = input('get.state', '');

        if ($code === '' || $orderNo === '') {
            return $this->error('微信授权失败，请重新下单');
        }

        [$config, $error] = $this->wechatConfig(true);
        if ($error !== null) {
            return $error;
        }

        try {
            session('wx_openid', (new WechatPayService($config))->openidByCode($code));
        } catch (\Throwable $e) {
            LogService::record('订单', '微信授权失败', '订单号：' . $orderNo . '，原因：' . $e->getMessage());
            return $this->error('微信授权失败：' . $e->getMessage());
        }

        return redirect('/pay/wechat?order_no=' . rawurlencode($orderNo));
    }

    /**
     * 取微信支付配置，顺带校验完整性
     * @return array{0: array, 1: ?\think\Response}
     */
    private function wechatConfig(bool $forJsapi): array
    {
        $payConfig = PaymentConfig::where('type', 2)->where('status', 1)->find();
        if (!$payConfig) {
            return [[], $this->error('微信支付未启用')];
        }
        $config  = json_decode($payConfig->config, true) ?: [];
        $missing = WechatPayService::missingFields($config, $forJsapi);
        if ($missing) {
            return [[], $this->error('微信支付配置不完整：缺少 ' . implode('、', $missing))];
        }
        return [$config, null];
    }

    /** GET /pay/alipay?order_no=xxx  直接输出支付宝 PC 支付表单 */
    public function alipay(): \think\Response
    {
        $this->requireLogin();
        $orderNo = input('get.order_no', '');
        OrderService::closeExpired($this->userId);
        $order = Order::where('order_no', $orderNo)->where('user_id', $this->userId)->find();
        if (!$order || (int)$order->pay_status === 1 || (int)$order->status === 2) {
            return redirect('/user/orders');
        }

        if (request()->isWechat()) {
            return $this->view('pay/wechat_tip', ['order' => $order->toArray()]);
        }

        $payConfig = PaymentConfig::where('type', 1)->where('status', 1)->find();
        if (!$payConfig) {
            return $this->error('支付宝支付未启用');
        }

        $config = json_decode($payConfig->config, true) ?: [];
        $missing = [];
        foreach (['app_id', 'private_key', 'alipay_public_key'] as $key) {
            if (empty(trim((string)($config[$key] ?? '')))) {
                $missing[] = $key;
            }
        }
        if ($missing) {
            return $this->error('支付宝配置不完整：缺少 ' . implode('、', $missing));
        }

        try {
            $amount = number_format((float) $order->amount, 2, '.', '');
            $subject = mb_substr($order->title, 0, 50);
            $returnUrl = (string) url('/pay/return', [], false, true);
            $alipay = new AlipayService($config);
            // URL 上显式带了 device 就用它，否则按 UA 判断，
            // 避免从收藏夹/历史记录直接打开这个地址时手机走成电脑网站支付
            $device = input('get.device', '');
            if (!in_array($device, ['pc', 'h5'], true)) {
                $device = request()->isMobileDevice() ? 'h5' : 'pc';
            }
            $html = $device === 'h5'
                ? $alipay->buildH5PayForm($orderNo, $subject, $amount, $returnUrl)
                : $alipay->buildPcPayForm($orderNo, $subject, $amount, $returnUrl);
            return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        } catch (\Throwable $e) {
            LogService::record('订单', '支付宝发起失败', '订单号：' . $orderNo . '，原因：' . $e->getMessage());
            return $this->error('支付初始化失败：' . $e->getMessage());
        }
    }

    /** GET /pay/return  支付宝同步回跳（用户看到的成功/失败页） */
    public function return(): \think\Response
    {
        $orderNo = input('get.out_trade_no', '');

        // 异步通知可能还没到、甚至根本没到，这里主动查一次支付宝补单，
        // 否则用户明明付了钱，回跳过来看到的却是「支付处理中」
        PayService::syncAlipay($orderNo);

        $order   = $orderNo ? Order::where('order_no', $orderNo)->find() : null;

        // 支付结果以数据库 pay_status 为准，不信任 GET 参数
        $isPaid = $order && (int)$order->pay_status === 1;

        // 网盘链接和提取码只发给下单本人：这个页面没有登录门槛，
        // 否则拿到订单号的任何人都能直接取走付费资料
        $isOwner = $order && $this->userId && (int)$order->user_id === $this->userId;

        // 合并订单一单多份，逐条列出各自的获取方式
        $deliveries = [];
        if ($isPaid && $isOwner) {
            $items = \app\common\model\OrderItem::where('order_id', $order->id)->select();
            $ids   = $items->column('material_id');
            $map   = $ids ? \app\common\model\Material::whereIn('id', $ids)->select()->column(null, 'id') : [];
            foreach ($items as $it) {
                $m = $map[$it->material_id] ?? null;
                if (!$m) {
                    continue;
                }
                $isPan = (int) $m['delivery_type'] === 1;
                $deliveries[] = [
                    'material_id' => (int) $m['id'],
                    'title'       => $m['title'],
                    'type'        => $isPan ? 'pan' : 'file',
                    'url'         => $isPan ? $m['pan_url'] : '/user/download?material_id=' . $m['id'],
                    'code'        => $isPan ? $m['pan_code'] : '',
                ];
            }
        }

        return $this->view('pay/result', [
            'order'      => $order ? $order->toArray() : [],
            'success'    => $isPaid,
            'deliveries' => $deliveries,
            'isOwner'    => $isOwner,
        ]);
    }

    /** POST /pay/notify  支付宝异步通知（服务器间调用，无 Session） */
    public function notify(): \think\Response
    {
        $params  = input('post.');
        $channel = 1; // 目前只有支付宝

        $payConfig = PaymentConfig::where('type', $channel)->where('status', 1)->find();
        if (!$payConfig) {
            return response('fail');
        }

        $config = json_decode($payConfig->config, true);
        $alipay = new AlipayService($config);

        if (!$alipay->verifyNotify($params)) {
            LogService::record('订单', '回调异常', '支付宝通知验签失败，订单号：' . ($params['out_trade_no'] ?? '未知'));
            return response('fail');
        }

        // 校验通知归属：app_id 必须与自己的配置一致，防止用他人商户的合法通知伪造
        if (($params['app_id'] ?? '') !== ($config['app_id'] ?? '')) {
            LogService::record('订单', '回调异常', 'app_id 不匹配：' . ($params['app_id'] ?? ''));
            return response('fail');
        }

        if (($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return response('success'); // 其他状态不处理但返回 success 避免重复通知
        }

        $orderNo = $params['out_trade_no'] ?? '';
        $order   = Order::where('order_no', $orderNo)->where('pay_status', 0)->find();
        if ($order) {
            // 校验金额，防止低价攻击
            $notifyAmount = number_format((float)($params['total_amount'] ?? 0), 2, '.', '');
            $orderAmount  = number_format((float)$order->amount, 2, '.', '');
            if ($notifyAmount !== $orderAmount) {
                LogService::record('订单', '金额异常', "订单号：{$orderNo} 应付：{$orderAmount} 实付：{$notifyAmount}");
                return response('fail');
            }

            PayService::markPaid($order, $channel, (string) ($params['trade_no'] ?? ''));
        }

        return response('success');
    }

    /** POST /pay/notify/wechat  微信支付异步通知（服务器间调用，无 Session） */
    public function wechatNotify(): \think\Response
    {
        $payConfig = PaymentConfig::where('type', 2)->where('status', 1)->find();
        if (!$payConfig) {
            return $this->wechatNotifyFail('微信支付未启用');
        }
        $config = json_decode($payConfig->config, true) ?: [];

        $notify = json_decode(request()->getContent(), true);
        if (!is_array($notify)) {
            return $this->wechatNotifyFail('报文格式错误');
        }

        try {
            // 解密即验真：GCM 认证标签保证报文未被篡改，且对方持有 APIv3 密钥
            $data = (new WechatPayService($config))->decryptNotify($notify);
        } catch (\Throwable $e) {
            LogService::record('订单', '微信回调异常', $e->getMessage());
            return $this->wechatNotifyFail('解密失败');
        }

        // 校验通知归属，防止拿别人商户的合法通知来伪造
        if (($data['mchid'] ?? '') !== ($config['mchid'] ?? '')) {
            LogService::record('订单', '微信回调异常', 'mchid 不匹配：' . ($data['mchid'] ?? ''));
            return $this->wechatNotifyFail('商户号不匹配');
        }

        if (($data['trade_state'] ?? '') !== 'SUCCESS') {
            return $this->wechatNotifySuccess(); // 其他状态不处理，但要应答避免重复推送
        }

        $orderNo = (string) ($data['out_trade_no'] ?? '');
        $order   = Order::where('order_no', $orderNo)->where('pay_status', 0)->find();
        if ($order) {
            // 校验金额，防止低价攻击。微信单位是分
            $notifyFen = (int) ($data['amount']['total'] ?? 0);
            $orderFen  = (int) round((float) $order->amount * 100);
            if ($notifyFen !== $orderFen) {
                LogService::record('订单', '金额异常', "订单号：{$orderNo} 应付：{$orderFen}分 实付：{$notifyFen}分");
                return $this->wechatNotifyFail('金额不一致');
            }
            PayService::markPaid($order, 2, (string) ($data['transaction_id'] ?? ''));
        }

        return $this->wechatNotifySuccess();
    }











    private function wechatNotifySuccess(): \think\Response
    {
        return json(['code' => 'SUCCESS', 'message' => '成功']);
    }

    private function wechatNotifyFail(string $msg): \think\Response
    {
        return json(['code' => 'FAIL', 'message' => $msg])->code(400);
    }
}
