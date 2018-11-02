<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class GoodsTaskQueue extends Model
{
	use CURD;
	//自动写入 state字段
	protected $insert = ['status' => 1];

	/**
     * 复制GoodsTask表数据
     * @param GoodsTask模型传递的数据
     * @param $int GoodsTask表自增ID
     */
    public function increment($data,$int){
    	//字段重新赋值
    	$list['task_id'] = $int;
    	$list['mold'] = $data['mold'];
    	$list['shop'] = $data['shop'];
    	$list['group'] = $data['group'];
    	$list['level'] = $data['level'];
    	$list['goods_num'] = $data['singular'];
    	$list['run_time'] = $data['start_time'];
    	if(!$res = GoodsTaskQueue::add($list)) return false;
    	return true;
    }
}
