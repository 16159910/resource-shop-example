<?php
namespace app\common\model;

use think\Model;

class Freebie extends Model
{
    protected $name = 'freebie';
    protected $autoWriteTimestamp = true;

    /** 领取方式 */
    public const TYPE_DIRECT = 1;
    public const TYPE_MP     = 2;
    public const TYPE_BUY    = 3;
    public const TYPE_ORDERS = 4;
}
