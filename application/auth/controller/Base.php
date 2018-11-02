<?php

namespace app\auth\controller;

use think\Controller;
use RedisExt;
use think\facade\Request;
use app\common\controller\User as CommonUser;

class Base extends Controller
{
    protected $user;

    public function __construct()
    {
        $info = Token::verifyToken();
        if (empty($info)) {
            $this->authFail();
        }
        //检测权限
        $this->checkPower($info);
        return true;
    }

    public function checkPower(&$info)
    {
        switch ($info['role']) {
            case 'user':
                $this->user = $info['info'];
                if (!isset($this->user['id'])) {
                    $this->authFail();
                }

                //可以通过id，获取用户缓存，判断是否被禁用，token判断缓存的话，被禁用token未更新
                if ($this->user['bind']) {
                    $status = CommonUser::getUserStatusById($this->user['id']);
                    if ($status != 3 ) {
                        renderJson(10014,config('errcode.10014'));
                    }
                }

                break;
            default:
                //直接返回登录失败
                $data = [
                    'code' => 401,
                    'message' => '角色错误'
                ];
                header('HTTP/1.0 401 Unauthorized');
                header('Content-type: application/json');
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
                die();
        }

    }

    public function authFail()
    {
        $appid = config('appidConfig.SERVICE_APPID');
        $redirect_uri = config('constant.WECHAT_WEB_URL');
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
        //直接返回登录失败
        $data = [
            'errcode' => 401,
            'message' => '请登录',
            'url'=>$url
        ];
        header('HTTP/1.0 401 Unauthorized');
        header('Content-type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    /**
     * Api 接口请求频率限制
     */
    public function apiLimit()
    {
        $token = Request::header('token');
        RedisExt::getInstance()->incr("concurrent:{$token}");
        RedisExt::getInstance()->expire("concurrent:{$token}", 3);
        if (RedisExt::getInstance()->get("concurrent:{$token}") > 2) {
            $response = [
                'errcode' => 10040,
                'message' => config('errcode.10040')
            ];
            header('Content-type: application/json');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            die();
        }
    }

}
