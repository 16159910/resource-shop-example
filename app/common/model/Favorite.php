<?php
namespace app\common\model;

use think\Model;

class Favorite extends Model
{
    protected $name = 'favorite';
    protected $autoWriteTimestamp = 'int';
    protected $updateTime = false;

    public function material(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
