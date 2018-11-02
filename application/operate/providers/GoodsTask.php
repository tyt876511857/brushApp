<?php
namespace app\operate\providers;

use app\operate\model\GoodsTask as Model;

class GoodsTask
{
    protected $model;

    //实例化模型对象
    public function __construct(Model $model) {
        $this->model = $model;
    }
    //子表添加数据
    public function append($list,$insertId){
    	return $this->model->increment($list,$insertId);
    }

}
