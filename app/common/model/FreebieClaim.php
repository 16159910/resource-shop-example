<?php
namespace app\common\model;

use think\Model;

class FreebieClaim extends Model
{
    protected $name = 'freebie_claim';
    protected $autoWriteTimestamp = 'int';
    protected $updateTime = false;
}
