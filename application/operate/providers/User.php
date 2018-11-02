<?php

namespace app\operate\providers;

use app\operate\model\User as Model;

class User
{
    protected $model;

    public function __construct(Model $model){
    	$this->model = $model;
    }

    //用户列表查询
    public function getList(){
    	return $this->model->getList();
    }

    //后台添加用户,返回主键ID
    public function append($data){
    	$data = $this->model->add($data);
    	return $data->id;
    }
}