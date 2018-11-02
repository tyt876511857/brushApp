<?php

namespace app\operate\controller;

use think\Controller;
use think\Request;
use think\captcha\Captcha;
use think\facade\Session;
use RedisExt;

class Login extends Controller
{
    /**
     * @api {get} /operate/user/captcha 获取验证码
     * @apiName operate/user/captcha
     * @apiGroup operate-Login
     */
    public function index()
    {
        $captcha = new Captcha([
            'expire'   => 100,
            'fontSize' => 36,
            'length'   => 4
        ]);
        return $captcha->entry('login');
    }
     /**
     * @api {post} /operate/user/dologin 管理员登录
     * @apiName /operate/user/dologin
     * @apiGroup operate-Login
     *
     * @apiParam {String} code 验证码.
     * @apiParam {String} username 用户名.
     * @apiParam {String} password 密码.
     *
     * @apiSuccessExample Success-Response:
     *{"code":200,"message":"登录成功" }
     *
     * @apiErrorExample Error-Response:
     * {"code":500,"message":"错误信息"}
     *
     */
    public function save(Request $request)
    {
        /**
        * 1.判断验证码是否正确
        * 2.判断用户名和密码是否正确
        * 3.成功,存储session,进入首页面
        */
        $data = $request->only(['code','username','password']);
        //if(!captcha_check($data['code'],'login')) return wrong('验证码错误');
        if(!$res = $this->admin->checkuser($data['username'])) return wrong('用户名错误');
        if(!password_verify($data['password'],$res['password'])) return wrong('密码错误');
        Session::set('aid',$res['id']);
        RedisExt::getInstance()->set('aid',Session::get('aid'));

        return wrong('登录成功！','200');
    }
    /**
     * 显示资源列表
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read()
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
            default:
                # code...
                break;
        }
    }

}
