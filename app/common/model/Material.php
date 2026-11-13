<?php
namespace app\common\model;

use think\Model;

class Material extends Model
{
    protected $name = 'material';
    protected $autoWriteTimestamp = true;

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function previews()
    {
        return $this->hasMany(MaterialPreview::class, 'material_id')->order('sort', 'asc');
    }
}
