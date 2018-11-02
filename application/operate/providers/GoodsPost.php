<?php
namespace app\operate\providers;

use app\operate\model\GoodsPost as Model;


class GoodsPost
{
    protected $model;

    //实例化模型对象
    public function __construct(Model $model) {
        $this->model = $model;
    }
    //添加数据,返回主键ID
    public function append($data) {
        $data = $this->model->add($data);
        return $data->id;
    }
    //统计总单数保存至数据表中,返回自增ID
    public function count_singular($sum,$insertId){
        $data = ['id' => $insertId,'count' => $sum];
        $obj_data = $this->model->modify($data);
        return $obj_data->id;
    }
}
