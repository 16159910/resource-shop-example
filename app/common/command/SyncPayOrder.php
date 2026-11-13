<?php
namespace app\common\command;

use app\common\model\Order;
use app\home\service\PayService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

/**
 * 手动补单：拿订单号去支付宝查真实交易状态，付过就把订单改成已支付。
 * 用于异步通知丢失后，把用户已经付过钱的单子捞回来。
 */
class SyncPayOrder extends Command
{
    protected function configure(): void
    {
        $this->setName('pay:sync')
             ->addArgument('order_no', Argument::OPTIONAL, '订单号，不传则处理最近 3 天全部未支付订单')
             ->setDescription('主动查询支付宝并补单');
    }

    protected function execute(Input $input, Output $output): void
    {
        $orderNo = (string) $input->getArgument('order_no');

        $list = $orderNo !== ''
            ? Order::where('order_no', $orderNo)->column('order_no')
            : Order::where('pay_status', 0)
                ->where('create_time', '>=', time() - 3 * 86400)
                ->column('order_no');

        if (!$list) {
            $output->writeln('<comment>没有需要处理的订单</comment>');
            return;
        }

        $paid = 0;
        foreach ($list as $no) {
            // syncAlipay 内部有 5 秒节流，逐单处理时要先清掉，否则会被自己挡住
            \think\facade\Cache::delete('pay_sync_' . $no);
            if (PayService::syncAlipay($no)) {
                $paid++;
                $output->writeln("  <info>{$no} 已补单</info>");
            } else {
                $output->writeln("  {$no} 未支付或无需处理");
            }
        }

        $output->writeln(sprintf('共检查 %d 笔，补单 %d 笔', count($list), $paid));
    }
}
