<?php
namespace app\home\controller;

use app\home\service\CartService;

class Cart extends Base
{
    /** GET /cart/items  顶部购物车下拉用 */
    public function items(): \think\Response
    {
        $this->requireLogin();
        $service = new CartService();
        $items   = $service->getItems($this->userId);
        $total   = 0.0;
        foreach ($items as $it) {
            $total += (float) $it['price'];
        }
        return $this->success([
            'items' => $items,
            'count' => count($items),
            'total' => number_format($total, 2, '.', ''),
        ]);
    }

    public function add(): \think\Response
    {
        $this->requireLogin();
        $materialId = (int) input('post.material_id');
        if (!$materialId) return $this->error('参数错误');

        try {
            (new CartService())->addItem($this->userId, $materialId);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        $count = (new CartService())->getCount($this->userId);
        return $this->success(['count' => $count]);
    }

    public function remove(): \think\Response
    {
        $this->requireLogin();
        $materialId = (int) input('post.material_id');
        if (!$materialId) return $this->error('参数错误');

        (new CartService())->removeItem($this->userId, $materialId);
        $count = (new CartService())->getCount($this->userId);
        return $this->success(['count' => $count]);
    }
}
