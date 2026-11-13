<?php
namespace app\common\model;

use think\Model;

class OrderItem extends Model
{
    protected $name = 'order_item';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'price' => 'float',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
