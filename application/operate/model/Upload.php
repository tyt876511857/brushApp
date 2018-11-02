<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class Upload extends Model
{
	use CURD;
	protected $autoWriteTimestamp = true;

}
