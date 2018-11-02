<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class GoodsPost extends Model
{
    use CURD;
    //开启时间戳字段
    protected $autoWriteTimestamp = true;
}
