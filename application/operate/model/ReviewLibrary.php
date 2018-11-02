<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class ReviewLibrary extends Model
{
	use CURD;
	protected $autoWriteTimestamp = true;

}
