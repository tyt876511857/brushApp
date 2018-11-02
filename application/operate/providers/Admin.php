<?php

namespace app\operate\providers;

use app\operate\model\Admin as Model;

class Admin
{
	protected $model;

    public function __construct(Model $model) {
        $this->model = $model;
    }

    // 根据username字段查询用户
    public function checkuser($user){
    	return $this->model->getByUsername($user);
    }

    //根据id查询出字段值
    public function getValue($id, $field){
    	return $this->model->where('id',$id)->value($field);
    }
   
}
