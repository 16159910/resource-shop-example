<?php
namespace app\common\model;

use think\Model;

class DownloadLog extends Model
{
    protected $name = 'download_log';
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
