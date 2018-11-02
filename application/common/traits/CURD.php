<?php
namespace app\common\traits;

//公用模型,实现基础通用的增删改查功能
trait CURD
{
     protected $require_field = [];
    /**
     * 添加新数据
     * @param 数组
     */
    public function add($data) {
        return static::create($data,true);
    }
    /**
     * 更新原有数据
     * @param 条件和字段值
     */
    public function modify(array $condition) {
        return static::update($condition);
    }


}
