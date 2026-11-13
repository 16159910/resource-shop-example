<?php
namespace app\admin\service;

use app\common\model\CardKey;
use app\common\model\Material;
use app\common\model\Order;

/**
 * 后台卡密仓库：按商品看库存、列表筛选、删除未售、导出
 */
class CardKeyAdminService
{
    /** 卡密商品及各自库存 */
    public function products(): array
    {
        $rows = Material::where('goods_type', \app\common\service\CardKeyService::GOODS_CARD)
            ->order('id', 'desc')->field('id, title, cover, price, status')->select()->toArray();
        $counts = CardKey::group('material_id, status')->field('material_id, status, COUNT(*) AS n')->select()->toArray();
        $map = [];
        foreach ($counts as $c) {
            $map[$c['material_id']][(int) $c['status']] = (int) $c['n'];
        }
        foreach ($rows as &$r) {
            $r['unsold'] = $map[$r['id']][0] ?? 0;
            $r['sold']   = $map[$r['id']][1] ?? 0;
        }
        unset($r);
        return $rows;
    }

    public function list(array $params, int $page, int $pageSize): array
    {
        $query = CardKey::order('id', 'desc');
        if (!empty($params['material_id'])) {
            $query->where('material_id', (int) $params['material_id']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('card_no', '%' . $params['keyword'] . '%');
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $pageSize)->select()->toArray();

        // 已售的带上订单号，方便对账
        $orderNos = Order::whereIn('id', array_filter(array_column($list, 'order_id')))->column('order_no', 'id');
        foreach ($list as &$row) {
            $row['order_no']  = $orderNos[$row['order_id']] ?? '';
            $row['sold_time'] = $row['sold_time'] ? date('Y-m-d H:i', (int) $row['sold_time']) : '';
        }
        unset($row);
        return compact('list', 'total', 'page', 'pageSize');
    }

    /** 只删未售的；已售的是发货凭证，不能删 */
    public function deleteUnsold(array $ids): int
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) return 0;
        return CardKey::whereIn('id', $ids)->where('status', 0)->delete();
    }

    /** @return array<int, array> 导出用的行 */
    public function exportRows(int $materialId, string $status): array
    {
        $query = CardKey::where('material_id', $materialId)->order('id', 'asc');
        if ($status !== '') {
            $query->where('status', (int) $status);
        }
        return $query->field('card_no, card_secret, status, order_id, sold_time, batch_no')->select()->toArray();
    }
}
