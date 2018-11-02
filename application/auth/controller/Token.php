<?php

namespace app\auth\controller;

use think\facade\Cache;
use think\facade\Request;
use RedisExt;

class Token
{
    /**
     * 创建token
     * @param $role
     * @param $user_info
     * @return string
     */
    public static function createToken($role, $user_info)
    {
        $hash = md5(openssl_random_pseudo_bytes(16));
        $key = config("redisKey.session:{$role}").$hash;
        Cache::set($key, $user_info, config("redisKeyExpiretime.session:{$role}"));
        return $key;
    }

    /**
     * 验证token
     * @return array|bool
     */
    public static function verifyToken()
    {
        $token_str = Request::header('token');
        if (empty($token_str)) {
            return false;
        }
        $token_arr = explode(':', $token_str);
        if (empty($token_arr[1])) {
            return false;
        }
        $role = $token_arr[1];
        $info = Cache::get($token_str);
        if (empty($info)) {
            return false;//校验失败
        }
        //更新session时间
        RedisExt::getInstance()->expire($token_str, 60 * 60 * 24 * 7);
        $data = [
            'role' => $role,
            'info' => $info
        ];
        return $data;
    }
}
