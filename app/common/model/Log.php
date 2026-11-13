<?php
namespace app\common\model;

use think\Model;

class Log extends Model
{
    protected $name = 'log';
    protected $autoWriteTimestamp = true;
    protected $updateTime = false;
}
