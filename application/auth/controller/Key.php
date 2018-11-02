<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/19
 * Time: 19:16
 */

namespace app\auth\controller;

use think\Db;
use think\facade\Request;

class Key
{
    //检查注册的key
    public function check()
    {
        $key = Request::param('key');
        if (preg_match('/^g/i', $key)) {
            $teacher = substr($key, 1) - 10000;
            $teacherInfo = Db::name('user')->where(['id' => $teacher, 'role' => 2])->field('id')->find();
            if (empty($teacherInfo['id'])) {
                return json([
                    'errcode' => 10001,
                    'message' => config('errcode.10001')
                ]);
            }
        } else {
            $admin = Db::name('admin')->where(['inviter' => $key])->field('username,id')->find();
            if (!$admin['id']) {
                return json([
                    'errcode' => 10011,
                    'message' => config('errcode.10011')
                ]);
            }
        }
        return json([
            'errcode' => 0,
            'message' => ''
        ]);
    }
}