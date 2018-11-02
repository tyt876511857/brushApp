<?php

namespace app\operate\providers;

use app\operate\model\ReviewLibrary as Model;
use RedisExt;

class ReviewLibrary
{
    protected $model;

    public function __construct(Model $model) {
        $this->model = $model;
    }

    //执行数据添加,返回主键ID
    public function append($data){
    	$data['aid'] = RedisExt::getInstance()->get('aid'); //默认值
    	$data = $this->model->add($data);
    	return $data->id; 
    }

}
