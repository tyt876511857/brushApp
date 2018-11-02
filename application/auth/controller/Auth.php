<?php
/**
 * Created by PhpStorm.
 * User: aoya
 * Date: 2018/5/23
 * Time: 11:42
 */

namespace app\auth\controller;

use app\validate\controller\User as UserValidate;
use think\Controller;
use think\Db;
use think\facade\Cache;
use think\facade\Request;

class Auth extends Controller
{
    /**
     * @api {get} /token 微信认证获取token
     * @apiName getTokenByCode
     * @apiGroup Auth
     *
     * @apiParam {String} code 微信code.
     *
     * @apiSuccessExample Success-Response:
     *
     *{"errcode":0,"data":{"id":3,"username":"徐晓全","role":1,"openid":"oSZ2b1Z6fKJK2GcP3Zbu5sIBKxcE","status":3,"pid":1,"group":"1","bind":1},"token":"session:user:b13a23867cfb3718e01baae65cbbcd9b"}
     *
     * @apiErrorExample Error-Response:
     *
     * {"errcode":401,"message":"登录失败"}
     *
     */
    public function getTokenByCode()
    {
        //此处是支付公众号的appid
        $appid = config('appidConfig.SERVICE_APPID');
        $secret = config('appidConfig.SERVICE_SECRET');
        $code = Request::param('code');
        if (empty($code)) {
            $myurl = 'http://192.168.11.34:2027/api/token';
            $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$myurl.'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
            header ( "location:" . $url );
            renderJson(400, config('errcode.400'));
        }

        //第一步:取全局access_token
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$secret";
        $token = simpleCurl($url);
        //dump($token);die;
        //第二步:取得openid
        $oauth2Url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";
        $oauth2 = simpleCurl($oauth2Url);
        if (empty($oauth2['openid'])) {
            return json([
                'errcode' => -1,
                'message' => config('errcode.-1'),
                'data'=>$oauth2
            ]);
        }
        //dump($oauth2);die;
        $access_token = $token["access_token"];
        $openid = $oauth2['openid'];

        //通过openid去数据库获取用户信息，已经存在，则直接返回此次token，以及用户的信息
        $user = Db::name('user')
            ->field('id,username,role,openid,status,pid,group')
            ->where(['openid' => $openid])
            ->find();
        if (empty($user)) {
            //数据库不存在该openid对应的记录
            //第三步:根据全局access_token和openid查询用户信息
            $get_user_info_url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$openid&lang=zh_CN";
            $userInfo = simpleCurl($get_user_info_url);
            $user['openid'] = $userInfo['openid'];
            $user['nickname'] = $userInfo['nickname'];
            $user['headimgurl'] = $userInfo['headimgurl'];
            $user['bind'] = 0;//未绑定
        } else {
            $userinfo = Db::name('userinfo')
                ->field('id,img,nickname')
                ->where(['uid' => $user['id']])
                ->find();
            $user['bind'] = 1;//已注册绑定
            $user['nickname'] = $userinfo['nickname'];
            $user['headimgurl'] = $userinfo['img'];
            //判断是否禁用
        }

        $key = Token::createToken('user', $user);
        return json([
            'errcode' => 0,
            'data' => $user,
            'token' => $key
        ]);
    }

    /**
     * @api {post} /register 注册
     * @apiName register
     * @apiGroup Auth
     *
     * @apiParam {String} password 密码.
     * @apiParam {String} name 真实名.
     * @apiParam {String} username 用户名.
     * @apiParam {String} inviter 邀请码.
     * @apiParam {Integer} role 角色  1徒弟,2师傅.
     * @apiParam {Integer} sex 性别.
     * @apiParam {Integer} job 职业 1-18.
     * @apiParam {String} qq qq.
     * @apiParam {String} wechat 微信.
     * @apiParam {String} aliwang 旺旺.
     * @apiParam {String} phone 手机.
     * @apiParam {String} alipay 支付宝.
     * @apiParam {Integer} birthday 出生年月，存时间戳.
     *
     * @apiSuccessExample Success-Response:
     *
     *{"errcode":0}
     *
     * @apiErrorExample Error-Response:
     *
     * {"errcode":10011,"message":"注册失败"}
     *
     */
    public function register()
    {
        //todo  validate
        $validate = new UserValidate;
        if (!$validate->scene('add')->check(Request::param())) {
            return json([
                'errcode' => 100,
                'message' => $validate->getError()
            ]);
        }

        //缓存会话
        $userInfo = self::getTokenUser();

        //获取ip所在地
        //$url = 'http://ip.taobao.com/service/getIpInfo.php?ip=' . $_SERVER["REMOTE_ADDR"];
        //$text = file_get_contents($url);

        //金华还是限制
        //if (preg_match('/(金华)/', $text)) {
            //return json([
                //'errcode' => 100,
                //'message' => config('errcode.100')
            //]);
        //}

        $param = Request::param('');

        if ($param['role'] == '1') {//徒弟注册的逻辑
            $teacher = substr($param['inviter'], 1) - 10000;
            //可优化
            $arr = Db::name('user')->where(['id' => $teacher, 'role' => 2])->field('id,status,username,group,inviter')->find();
            if (empty($arr['id'])) {
                return json([
                    'errcode' => 10001,
                    'message' => config('errcode.10001')
                ]);
            }

            //状态判断
            if ($arr['status'] != 3) {
                return json([
                    'errcode' => 10002,
                    'message' => config('errcode.10002')
                ]);
            }

            //判断老师是否今日邀请注册已达上限，redis缓存技术
            $maxDayNum = config('constant.MAX_DAY_NUM');//30
            $key = config('redisKey.teacherDayNum') . $arr['id'];
            $teacherDayNum = Cache::get($key);
            if (!empty($teacherDayNum)) {
                if ($teacherDayNum > $maxDayNum) {
                    return json([
                        'errcode' => 10003,
                        'message' => config('errcode.10003')
                    ]);
                }
            }

            //所属老师的名字
            $param['teacher_name'] = $arr['username'];
            $param['pid'] = $arr['id'];
            $param['group'] = $arr['group'];

            //所属操作员名字
            $inviter = $arr['inviter'];
            $admin = Db::name('admin')->where(['inviter' => $inviter])->field('username')->find();
            $param['admin_name'] = $admin['username'];
        } else {//师傅注册的逻辑
            $param['pid'] = 0;
            $inviter = $param['inviter'];
            $admin = Db::name('admin')->where(['inviter' => $inviter])->field('username,id,groups')->find();
            if (empty($admin['id'])) {
                return json([
                    'errcode' => 10004,
                    'message' => config('errcode.10004')
                ]);
            } else {
                $param['admin_name'] = $admin['username'];
                $param['group'] = $admin['groups'];
            }
        }

        //qq是否存在
        $arr = Db::name('userinfo')->where(['qq' => $param['qq']])->field('id')->find();
        if (!empty($arr)) {
            return json([
                'errcode' => 10005,
                'message' => config('errcode.10005')
            ]);
        }

        //微信是否存在
        $arr = Db::name('userinfo')->where(['wechat' => $param['wechat']])->field('id')->find();
        if (!empty($arr)) {
            return json([
                'errcode' => 10006,
                'message' => config('errcode.10006')
            ]);
        }

        //旺旺是否存在
        $arr = Db::name('user')->where(['aliwang' => $param['aliwang']])->field('id')->find();
        if (!empty($arr)) {
            return json([
                'errcode' => 10007,
                'message' => config('errcode.10007')
            ]);
        }

        //电话是否存在
        $arr = Db::name('userinfo')->where(['mobile' => $param['mobile']])->field('id')->find();
        if (!empty($arr)) {
            return json([
                'errcode' => 10008,
                'message' => config('errcode.10008')
            ]);
        }

        //username是否存在
        $arr = Db::name('user')->where(['username' => $param['username']])->field('id')->find();
        if (!empty($arr)) {
            return json([
                'errcode' => 10009,
                'message' => config('errcode.10009')
            ]);
        }

        //支付宝是否存在
        $arr = Db::name('user')->where(['alipay' => $param['alipay']])->field('id')->find();
        if (!empty($arr)) {
            return json([
                'errcode' => 10010,
                'message' => config('errcode.10010')
            ]);
        }

        $param['password'] = createPassword($param['password']);
        $param['birthday'] = intval(substr($param['birthday'], 0, -3));
        $param['nickname'] = $userInfo['nickname'];
        $param['img'] = $userInfo['headimgurl'];
        $param['create_time'] = time();

        // 启动事务
        Db::startTrans();
        try {
            $uid = Db::name('user')->strict(false)->insertGetId($param);
            $param['uid'] = $uid;
            Db::name('userinfo')->strict(false)->insert($param);
            // 提交事务
            Db::commit();
            if ($param['role'] == '1') {
                if (!empty($teacherDayNum)) {//存在缓存
                    //当日注册数加一
                    Cache::inc($key);
                } else {
                    //23：59：59
                    $todayLastSeconds = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
                    $cacheTime = $todayLastSeconds - time();
                    Cache::set($key, 1, $cacheTime);
                }
            }
            return json([
                'errcode' => 0,
                'message' => '添加成功'
            ]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json([
                'errcode' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * @api {post} /bind 绑定，token的bind=0,绑定之后变为1
     * @apiName bind
     * @apiGroup Auth
     *
     * @apiParam {String} password 密码.
     * @apiParam {String} username 用户名.
     *
     * @apiSuccessExample Success-Response:
     *
     *{"errcode":0}
     *
     * @apiErrorExample Error-Response:
     *
     * {"errcode":10011,"message":"绑定失败"}
     *
     */
    public function bind()
    {
        $tokenUserInfo = self::getTokenUser();
        //验证
        $param = Request::param('');
        $validate = new UserValidate;
        if (!$validate->scene('bind')->check($param)) {
            return json([
                'errcode' => 100,
                'message' => $validate->getError()
            ]);
        }

        //判断微信用户是否已经绑定
        $bind = Db::name('user')
            ->where(['openid' => $tokenUserInfo['openid']])
            ->field('id')
            ->find();
        if (!empty($bind)) {
            return json([
                'errcode' => 10015,
                'message' => config('errcode.10015')
            ]);
        }

        //通过用户名获取用户
        $userInfo = Db::name('user')
            ->where(['username' => $param['username']])
            ->field('id,password,status,openid,role,pid,group,username')
            ->find();
        //dump($tokenUserInfo);die;
        //验证密码
        if (empty($userInfo) || !password_verify($param['password'], $userInfo['password'])) {
            return json([
                'errcode' => 200,
                'message' => config('errcode.200')
            ]);
        }
        //验证账号是否已经绑定过,不考虑绑定别人还没绑定的
        if (!empty($userInfo['openid'])) {
            return json([
                'errcode' => 10015,
                'message' => config('errcode.10015')
            ]);
        }

        if ($userInfo['status'] == 6) {
            return json([
                'errcode' => 10012,
                'message' => config('errcode.10012')
            ]);
        } else if ($userInfo['status'] == 2) {
            return json([
                'errcode' => 10013,
                'message' => config('errcode.10013')
            ]);
        } else if ($userInfo['status'] == 5) {
            return json([
                'errcode' => 10014,
                'message' => config('errcode.10014')
            ]);
        } else {
            //更新openid
            $res = Db::name('user')
                ->where('id', $userInfo['id'])
                ->update(['openid' => $tokenUserInfo['openid']]);
            if ($res) {
                //更新原来的token
                $tokenUserInfo['id'] = $userInfo['id'];
                $tokenUserInfo['bind'] = 1;
                $tokenUserInfo['role'] = $userInfo['role'];
                $tokenUserInfo['pid'] = $userInfo['pid'];
                $tokenUserInfo['group'] = $userInfo['group'];
                $tokenUserInfo['username'] = $userInfo['username'];
                //vue请求头
                $token_str = Request::header('token');
                Cache::set($token_str, $tokenUserInfo, config("redisKeyExpiretime.session:user"));
                return json([
                    'errcode' => 0,
                    'message' => '成功!'
                ]);
            } else {
                return json([
                    'errcode' => 1000,
                    'message' => config('errcode.1000')
                ]);
            }
        }
    }

    //获取会话用户
    public static function getTokenUser()
    {
        //获取用户的会话缓存
        $userInfo = Token::verifyToken();
        if (empty($userInfo)) {
            if (empty($info)) {
                //直接返回登录失败
                $data = [
                    'code' => 401,
                    'message' => '请登录'
                ];
                header('HTTP/1.0 401 Unauthorized');
                header('Content-type: application/json');
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
                die();
            }
        }
        return $userInfo['info'];
    }


    /**
     * @api {get} /check_status 检查登录状态
     * @apiName checkStatus
     * @apiGroup Auth
     *
     * @apiSuccessExample Success-Response:
     *
     *{"errcode":0,"data":{"id":1}}
     *
     * @apiErrorExample Error-Response:
     *
     * {"errcode":401,"message":"请登录"}
     *
     */
    public function checkStatus()
    {
        $info = Token::verifyToken();
        if (empty($info)) {
            //直接返回登录失败
            $data = [
                'errcode' => 401,
                'message' => '请登录'
            ];
            header('HTTP/1.0 401 Unauthorized');
            header('Content-type: application/json');
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            die();
        }
        $user = [
            'errcode' => 0,
            'data' => $info['info']
        ];
        return json($user);
    }


}