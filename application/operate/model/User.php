<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class User extends Model
{
    use CURD;
    protected $autoWriteTimestamp = true;

	//关联用户信息表
    public function userinfo()
    {
        return $this->hasOne('Userinfo','uid');
    }

	//关联明细表
    public function deal_detail()
    {
        return $this->hasMany('DealDetail','uid');
    }

    //当前角色名称
    public function getRoleNameAttr($value)
    {
        $status = [0=>'未知',1=>'徒弟',2=>'师傅'];
        return $status[$value];
    }

    //当前组别名称
    public function getGroupNameAttr($value)
    {
        $status = [1=>'A组',2=>'B组',3=>'C组',4=>'D组',5=>'W组'];
        return $status[$value];
    }

    //用户当前状态名称
    public function getStatusNameAttr($value)
    {
        $status = [0=>'未知',1=>'待审核',2=>'师傅审核通过',3=>'正常',4=>'操作人员审核拒绝',5=>'师傅拒绝',6=>'邀请已达上限'];
        return $status[$value];
    }

    //当前任务状态
    public function getTaskStateAttr($value)
    {
        $status = [0=>'未进行',1=>'进行中'];
        return $status[$value];
    }


    //用户列表查询
    public function getList(){
    	$list = User::all();
    	foreach($list as &$data){
            //用户角色 用户所属组 师傅账号(未实现)
            $data->role_name = $data->role;
            $data->group_name = $data->group;
      //       //$data->master_account = User::where(['piud'=>$data->inviter])->value('username');
    		$data->status_name = $data->status;
            //用户信息表 用户姓名(未显示)
    		$data->job = $data->userinfo->job_text;
            $data->name =  $data->userinfo->name; 
    		//任务状态 30天接单数 接单频率
    		$data->deal_detail()->where('deal_state = 1')->order('id desc')->find() ? $data->task_state=1 : $data->task_state=0;
    		$data->month_count = $data->deal_detail()->where('create_time','between time',[strtotime("-1 month"),time()])->count();
    		$data->hz = $data->month_count/30*100;
            unset($data->userinfo);
    	}
    	unset($data);
    	return $list;
    }

}
