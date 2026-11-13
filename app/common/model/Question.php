<?php
namespace app\common\model;

use think\Model;

class Question extends Model
{
    protected $name = 'question';
    protected $autoWriteTimestamp = true;

    // 读出来保持整数时间戳，避免 create_time 是字符串、reply_time 是整数这种不一致
    protected $dateFormat = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
