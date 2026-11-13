<?php
namespace app\common\model;

use think\Model;

class CartItem extends Model
{
    protected $name             = 'cart';
    protected $autoWriteTimestamp = false;

    public function material(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
