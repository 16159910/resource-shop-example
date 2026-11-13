<?php
namespace app\common\command;

use app\home\service\OrderService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class CloseExpiredOrders extends Command
{
    protected function configure(): void
    {
        $this->setName('order:close')
             ->setDescription('关闭超过10分钟未支付的订单');
    }

    protected function execute(Input $input, Output $output): void
    {
        // 前台在用户查看订单/发起支付时已经会顺手关闭自己的超时订单，
        // 这条命令是可选的全局兜底，配了 crontab 更及时，不配也不影响下单支付。
        $count = OrderService::closeExpired();

        $output->writeln(date('Y-m-d H:i:s') . " 关闭过期订单：{$count} 条");
    }
}
