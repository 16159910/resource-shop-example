<?php
namespace app\common\model;

use think\Model;

class CardKey extends Model
{
    protected $name = 'card_key';
    protected $autoWriteTimestamp = 'int';
    protected $updateTime = false;

    public function material(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
