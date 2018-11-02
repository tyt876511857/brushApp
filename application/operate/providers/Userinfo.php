<?php

namespace app\operate\providers;

use app\operate\model\Userinfo as Model;

class Userinfo
{
	protected $model;

	public function __construct(Model $model){
		$this->model = $model;
	}

	//添加信息,返回主键ID
	public function append($data){
		$data = $this->model->add($data);
		return $data->id;
	}
}