<?php
namespace app\admin\controller;

use app\common\model\Material;
use app\common\model\Order;
use app\common\model\Question;
use app\common\model\User;

class Index extends Base
{
    public function index(): \think\Response
    {
        $this->checkLogin();

        return $this->view('index/index', [
            'today'        => $this->today(),
            'trend'        => $this->trend(),
            'todo'         => $this->todo(),
            'overview'     => $this->overview(),
            'recentOrders' => $this->recentOrders(),
            'pendingQa'    => $this->pendingQuestions(),
            'currentMenu'  => 'dashboard',
            'pageTitle'    => '仪表板',
        ]);
    }

    /** 今天的经营情况，带上和昨天的对比 */
    private function today(): array
    {
        $paid = fn () => Order::where('pay_status', 1);

        $todayIncome = (float) $paid()->whereTime('pay_time', 'today')->sum('amount');
        $todayOrders = $paid()->whereTime('pay_time', 'today')->count();
        $yesterday   = (float) $paid()->whereTime('pay_time', 'yesterday')->sum('amount');

        // 昨天是 0 就算不出百分比，给 null，前端显示成「无同比」
        $delta = $yesterday > 0
            ? round(($todayIncome - $yesterday) / $yesterday * 100, 1)
            : null;

        return [
            'income'     => number_format($todayIncome, 2, '.', ''),
            'orders'     => $todayOrders,
            'yesterday'  => number_format($yesterday, 2, '.', ''),
            'delta'      => $delta,
            'week'       => number_format((float) $paid()->whereTime('pay_time', 'week')->sum('amount'), 2, '.', ''),
            'month'      => number_format((float) $paid()->whereTime('pay_time', 'month')->sum('amount'), 2, '.', ''),
        ];
    }

    /**
     * 近 7 天每日收入，用来画柱状图。
     * 一条 SQL 按天分组，别在循环里查 7 次。
     */
    private function trend(): array
    {
        $start = strtotime('-6 days 00:00:00');

        $rows = Order::where('pay_status', 1)
            ->where('pay_time', '>=', $start)
            ->field("FROM_UNIXTIME(pay_time, '%Y-%m-%d') AS day, SUM(amount) AS total, COUNT(*) AS cnt")
            ->group('day')
            ->select();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->day] = ['total' => (float) $r->total, 'cnt' => (int) $r->cnt];
        }

        $list = [];
        $max  = 0.0;
        for ($i = 6; $i >= 0; $i--) {
            $ts    = strtotime("-{$i} days");
            $day   = date('Y-m-d', $ts);
            $total = $map[$day]['total'] ?? 0.0;
            $max   = max($max, $total);
            $list[] = [
                'day'    => $day,
                'label'  => $i === 0 ? '今天' : date('m/d', $ts),
                'total'  => number_format($total, 2, '.', ''),
                'cnt'    => $map[$day]['cnt'] ?? 0,
                'raw'    => $total,
            ];
        }

        // 柱高按当期最大值归一化，全 0 时给个底，避免除零
        foreach ($list as &$d) {
            $d['percent'] = $max > 0 ? max(3, (int) round($d['raw'] / $max * 100)) : 3;
            unset($d['raw']);
        }
        unset($d);

        return ['list' => $list, 'max' => number_format($max, 2, '.', '')];
    }

    /** 需要人去处理的事，数字为 0 的前端不展示 */
    private function todo(): array
    {
        return [
            'question'   => Question::where('status', 0)->count(),
            'unpaid'     => Order::where('pay_status', 0)->where('status', '<>', 2)->count(),
        ];
    }



    private function recentOrders(): array
    {
        $orders = Order::where('pay_status', 1)->order('pay_time', 'desc')->limit(20)->select()->toArray();
        if (!$orders) {
            return [];
        }

        $users = User::whereIn('id', array_filter(array_unique(array_column($orders, 'user_id'))))
            ->column('nickname,phone,email', 'id');

        foreach ($orders as &$o) {
            $u = $users[$o['user_id']] ?? null;
            $o['user_label'] = $u ? ($u['nickname'] ?: ($u['phone'] ?: ($u['email'] ?: '-'))) : '-';
            $o['time_label'] = $o['pay_time'] ? time_label($o['pay_time'], 'm-d H:i') : '-';
        }
        return $orders;
    }

    private function pendingQuestions(): array
    {
        $list = Question::with(['user'])->where('status', 0)->order('id', 'asc')->limit(6)->select()->toArray();
        foreach ($list as &$q) {
            $u = $q['user'] ?? null;
            $q['user_label'] = $u ? ($u['nickname'] ?: ($u['phone'] ?: ($u['email'] ?: '-'))) : ('ID:' . $q['user_id']);
            $q['time_label'] = $q['create_time'] ? time_label($q['create_time'], 'm-d H:i') : '-';
            $q['summary']    = mb_substr((string) $q['content'], 0, 50);
            $q['truncated']  = mb_strlen((string) $q['content']) > 50;
        }
        return $list;
    }

    private function overview(): array
    {
        return [
            'material' => Material::where('status', 1)->count(),
            'user' => User::count(),
            'order' => Order::where('pay_status', 1)->count(),
            'income' => number_format((float) Order::where('pay_status', 1)->sum('amount'), 2, '.', ''),
        ];
    }
}
