<?php
namespace app\home\service;

use app\common\model\CartItem;
use app\common\model\Material;

class CartService
{
    public function addItem(int $userId, int $materialId): void
    {
        $material = Material::where('id', $materialId)->where('status', 1)->find();
        if (!$material) {
            throw new \RuntimeException('资料不存在或已下架');
        }

        // 幂等插入：已存在则忽略
        $exists = CartItem::where('user_id', $userId)->where('material_id', $materialId)->find();
        if (!$exists) {
            $item              = new CartItem();
            $item->user_id     = $userId;
            $item->material_id = $materialId;
            $item->create_time = time();
            $item->save();
        }
    }

    public function removeItem(int $userId, int $materialId): void
    {
        CartItem::where('user_id', $userId)->where('material_id', $materialId)->delete();
    }

    public function getCount(int $userId): int
    {
        return CartItem::where('user_id', $userId)->count();
    }

    /**
     * 返回用于前端展示的购物车数据（只含上架资料）
     */
    public function getItems(int $userId): array
    {
        $rows = CartItem::where('user_id', $userId)
            ->with(['material'])
            ->order('id', 'desc')
            ->select();

        $result = [];
        foreach ($rows as $row) {
            $m = $row->material;
            if (!$m || $m->status != 1) continue;
            $result[] = [
                'id'    => (int) $m->id,
                'title' => $m->title,
                'price' => (float) $m->price,
                'cover' => $m->cover ? thumb($m->cover) : '',
            ];
        }
        return $result;
    }
}
