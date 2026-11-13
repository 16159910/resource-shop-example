<?php
namespace app\common\model;

use think\Model;

class User extends Model
{
    protected $name = 'user';
    protected $autoWriteTimestamp = true;
    protected $hidden = ['password'];
}
