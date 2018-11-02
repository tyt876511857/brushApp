<?php

namespace app\operate\controller;

use think\Controller;
use think\Request;
use RedisExt;
use app\validate\controller\User as UserValidate;

class User extends Controller
{
    /**
     * @api {get} /operate/user/read 加载用户列表
     * @apiName operate/user/read
     * @apiGroup operate-User
     */
    public function index()
    {
        /**1.查询用户所有数据
        *  2.任务状态(判断明细表状态等于1是否在存在,存在为进行中,不存在则是未进行)
        *  3.30天接单数(created_time 当前时间-一月以前 范围内 的单子数量) 
        *  4.接单频率(当前的单数/当前的天数*100)
        *  5.数据缓存
        */
        if(!$list = RedisExt::getInstance()->get('userlist')){
            $list = $this->user->getList();
            RedisExt::getInstance()->set('userlist',json_encode($list),3600*24);
        }
        return output(json_decode($list));
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
        //
    }

     /**
     * @api {post} /operate/user/douser 后台添加用户
     * @apiName operate/user/douser
     * @apiGroup operate-User
     *
     * @apiParam {String} username 用户名.
     * @apiParam {String} password 密码.
     * @apiParam {String} wechat  微信号.
     * @apiParam {String} alipay  支付宝.
     * @apiParam {Integer} role 角色  1徒弟,2师傅.
     * @apiParam {String} name  真实姓名
     * @apiParam {Integer} sex 性别.
     * @apiParam {String} aliwang 旺旺
     * @apiParam {String} aliwang2 旺旺2
     * @apiParam {String} qq      QQ
     * @apiParam {String} mobile 手机.
     * @apiParam {Integer} job 职业.
     * @apiParam {Integer} birthday 出生年月，存时间戳.
     * @apiSuccessExample Success-Response:
     *{"code":200,"message":"添加成功"}
     * @apiErrorExample Error-Response:
     * {"code":500,"message":"错误信息"}
     */
    public function save(Request $request)
    {
        /**1.获取数据并进行字段验证
        *  2.添加主表,成功返回主键
        *  3.添加子表数据
        */
        $validate = new UserValidate;
        if(!$validate->scene('operate_add')->check($request->param())) return wrong($validate->getError(),'100');
        //组装User表数据 密码加密 师傅加邀请码 状态=3 组为admin表组 admin的id值
        $param = $request->only(['username','password','alipay','aliwang','role']);
        $param['password'] = createPassword($param['password']);
        if($param['role'] == 2) $param['inviter'] = $this->admin->getValue(RedisExt::getInstance()->get('aid'),'inviter');
        $param['status'] = 3;
        $param['group'] = $this->admin->getValue(RedisExt::getInstance()->get('aid'),'groups');
        $param['auditor'] = RedisExt::getInstance()->get('aid');
        if(!$insertId = $this->user->append($param)) return wrong('主表添加失败！');

        //组装userinfo表数据 wechat,name,sex,aliwang2,qq,mobile,job,birthday
        $data = $request->only(['wechat','name','sex','aliwang2','qq','mobile','job','birthday']);
        $data['uid'] = $insertId;
        $data['birthday'] = strtotime($data['birthday']);
        if(!$this->userinfo->append($data)) return wrong('子表添加失败！');

        return wrong('添加成功！','200');
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        //
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //
    }

    /*
    * 重载对象
    */
    public function __get($obj)
    {
        switch (strtolower($obj)) {
            case 'admin':
                return $this->admin = app(\app\operate\providers\Admin::class);
                break;
            case 'user':
                return $this->user = app(\app\operate\providers\User::class);
                break;
             case 'userinfo':
                return $this->userinfo = app(\app\operate\providers\Userinfo::class);
                break;
            default:
                # code...
                break;
        }
    }
}
