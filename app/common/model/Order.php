<?php
namespace app\common\model;

use think\Model;

class Order extends Model
{
    protected $name = 'order';
    protected $autoWriteTimestamp = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }



    /** 订单明细：合并下单时一个订单包含多份资料 */
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
