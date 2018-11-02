<?php
namespace app\common\model;

use think\Model;

class User extends Model
{
    protected $pk = 'id'; //主键
    protected $table = 'wkh_user';

    // 设置当前模型的数据库连接
    // protected $connection = 'db_config';

  
    //获取总数
    public static function getCount($where){
        $count = User::alias('u')->leftJoin('userinfo ui','ui.uid=u.id')
        ->where($where)
        ->count();

        return $count;
    }

    //获取列表
    public static function getList($where,$page,$limit,$order){
        $data = User::alias('u')
            ->leftJoin('userinfo ui','ui.uid=u.id')
            ->where($where)
            ->order('u.id', $order)
            ->field('u.id,u.username,u.openid,u.status,ui.name,u.aliwang,u.group')
            ->limit($page * $limit, $limit)
            ->select();
        return $data;
    }

    //获取详情
    public static function getDetail($id){
        $data =  User::alias('u')
            ->leftJoin('userinfo ui','ui.uid=u.id')
            ->where(['u.id'=>$id])
            ->field('u.admin,u.role,u.id,u.username,u.openid,u.status,ui.name,u.aliwang,u.group,u.noticeOpenid,u.integral')
            ->find()->toArray();
        return $data;
    }

    //条件获取详情
    public static function getByWhere($where){
        $data =  User::alias('u')
            ->leftJoin('userinfo ui','ui.uid=u.id')
            ->where($where)
            ->field('u.id,u.username,u.openid,u.status,ui.name,u.aliwang,u.group,u.noticeOpenid,u.integral')
            ->find();
        return $data;
    }
}