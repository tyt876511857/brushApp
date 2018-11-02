<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class GoodsTask extends Model
{
	use CURD;
	//自动写入 state字段
	protected $insert = ['state' => 1];
	protected $sum = '';

	//修改group字段值
	protected function setGroupAttr($value){
		switch ($value) {
			case 'A组':
				return 1;
				break;
			case 'B组':
				return 2;
				break;
			case 'C组':
				return 3;
				break;
			case 'D组':
				return 4;
				break;
			case 'W组':
				return 5;
				break;
			default:
				return $value;
				break;
		}
	}
	/**
	* 添加子表数据,失败返回false,成功返回总单数
	* @param list 数据集合
	* @param insertId 主键ID
	* return int
	*/
	public function increment($list, $insertId){
		//静态变量初始值为0
		static $sum = 0; 
		//公共保存数据
		$data['post_id'] = $insertId;
		$data['shop'] = $list['shop'];
		$data['baby_id'] = $list['baby_id'];
		$data['baby_goods'] = $list['baby_goods'];
		$data['goods_price'] = $list['goods_price'];
		$data['img_link'] = $list['img_link'];
		$data['search1'] = $list['search1'];
		$data['search2'] = $list['search2'];
		$data['group'] = $list['group'];
		$data['level'] = $list['level'];
		//第一层循环,添加关键字
		foreach($list['zi'] as $v){
			$data['keywords'] = $v['keywords'];
			//第二层循环.添加单子类型.如果是多组优惠的话,添加组别,类型是优惠券.
			foreach($v as $key => $val){
				if($key == "keywords")
					continue;
				if(!is_numeric($key)){
					$data['group'] = $key;
					$data['mold'] = $key = 3;
				}else{
					$data['mold'] = $key;
				}
				//第三层循环.执行添加数据
				foreach($val as $value){
					//单数值为空,不添加数据
					if(empty($value['singular'])) continue;
					//如果有淘口令(优惠单,评价单使用优惠券)
					if($key == 2 && $list['pj_coupons'] || $key == 3)
						$data['new_pass'] = $list['new_pass'] ? $list['new_pass'] : $list['pj_word'];
					//如果是退款单(退款单计件数和退款金额)
					if($key == 4){
						$data['return_num'] = $list['return_num'];
						$data['return_pay'] = $list['return_pay'];
					}
					//时间戳和单数
					$data['start_time'] = $value['start_time'];
					$data['singular'] = $value['singular'];
					//统计总单数
					$sum += $data['singular'];
					$new_data = GoodsTask::add($data);
					if(!$int = $new_data->id) return false;
					//复制数据到GoodsoperateQueue表
					if(!app(\app\operate\model\GoodsTaskQueue::class)->increment($data,$int)) return false;
 				}
			}
		}
		return $sum;
	}

}
