<?php

namespace app\validate\controller;

use think\Validate;

class User extends Validate
{
    protected $rule = [
        'password' => 'require|max:25|min:6',
        'name' => 'require|chs',
        'username' => 'require|chs',
        'inviter'=> 'require|alphaDash',
        'role' => 'require|between:1,2',
        'sex' => 'require|between:0,1',
        'job' => 'require|between:1,18',
        'conf_password' => 'require|max:25|min:6|confirm:password',
        'qq' => 'require',
        'wechat' => 'require',
        'aliwang' => 'require',
        'mobile' => 'require|mobile',
        'alipay' => 'require',
        'birthday' => 'require',
    ];

    protected $message = [
        'name.require' => '缺少真实名',
        'name.max' => '名称最多不能超过25个字符',
        'password' => '密码格式错误',
        'conf_password' => '确认密码错误',
    ];

    protected $scene = [
        'add' => ['password', 'name', 'username','role', 'wechat', 'qq', 'aliwang', 'mobile', 'alipay','sex','job','inviter','birthday'],
        'operate_add' => ['password', 'name', 'username','role', 'wechat', 'qq', 'aliwang', 'mobile', 'alipay','sex','job','birthday'],
        'edit' => ['name', 'job', 'username'],
        'bind' => ['username', 'password'],
    ];

}