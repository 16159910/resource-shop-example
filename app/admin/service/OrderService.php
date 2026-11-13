<?php
namespace app\admin\service;

use app\common\model\Order;
use app\common\model\OrderItem;
use app\common\service\BaseCrudService;
use app\home\service\OrderService as HomeOrderService;
use think\db\BaseQuery;

class OrderService extends BaseCrudService
{
    protected function modelClass(): string
    {
        return Order::class;
    }

    protected function allowFields(): array
    {
        return ['pay_status', 'status'];
    }

    /**
     * 覆盖 list：先把全站超时未支付的订单关掉，
     * 否则后台看到的状态会和前台不一致（前台是访问时惰性关闭的）
     */
    public function list(array $params = [], int $page = 1, int $pageSize = 20): array
    {
        HomeOrderService::closeExpired();
        $result = parent::list($params, $page, $pageSize);

        // 合并订单一单多份，把明细带上供列表展开查看
        $ids = array_column($result['list'], 'id');
        if ($ids) {
            $grouped = [];
            foreach (OrderItem::whereIn('order_id', $ids)->order('id', 'asc')->select() as $it) {
                $grouped[$it->order_id][] = [
                    'material_id' => (int) $it->material_id,
                    'title'       => $it->title,
                    'price'       => (float) $it->price,
                ];
            }
            foreach ($result['list'] as &$row) {
                $row['items'] = $grouped[$row['id']] ?? [];
            }
            unset($row);
        }
        return $result;
    }

    protected function buildListQuery(array $params): BaseQuery
    {
        $query = Order::with(['user', 'material'])->order('id', 'desc');
        if (!empty($params['keyword'])) {
            // 闭包分组，避免 OR 条件冲掉外层的 pay_status 筛选
            $kw = '%' . $params['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->whereLike('order_no', $kw)->whereOr('title', 'like', $kw);
            });
        }
        if (isset($params['pay_status']) && $params['pay_status'] !== '') {
            if ($params['pay_status'] === 'closed') {
                $query->where('status', 2);
            } else {
                // 待支付要排掉已关闭的，否则两个筛选项会重叠
                $query->where('pay_status', (int) $params['pay_status']);
                if ((int) $params['pay_status'] === 0) {
                    $query->where('status', '<>', 2);
                }
            }
        }
        if (!empty($params['material_id'])) {
            $query->where('material_id', (int) $params['material_id']);
        }
        // create_time 存的是时间戳，日期筛选按当天 00:00:00 ~ 23:59:59 取闭区间
        if (!empty($params['start_date'])) {
            $start = strtotime($params['start_date'] . ' 00:00:00');
            if ($start) {
                $query->where('create_time', '>=', $start);
            }
        }
        if (!empty($params['end_date'])) {
            $end = strtotime($params['end_date'] . ' 23:59:59');
            if ($end) {
                $query->where('create_time', '<=', $end);
            }
        }
        return $query;
    }
}
