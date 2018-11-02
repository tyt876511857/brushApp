<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/21
 * Time: 19:14
 */

namespace app\task\controller;

use app\auth\controller\Base;
use app\common\controller\Task as CommonTask;
use app\common\controller\User as CommonUser;
use app\common\controller\WxpayService;
use RedisExt;
use think\Db;
use think\facade\Cache;
use think\facade\Request;

//注意事项，一些变量需要写到配置文件
//缓存的添加，比如做了多少单，以及上次任务时间，以及当前是否已经有任务的缓存

class Task extends Base
{
    //添加任务
    public function add()
    {
        //判断用户是否已经申请任务
        if (RedisExt::getInstance()->hExists('user_queue_addtime', $this->user['id'])) {
            return json([
                'errcode' => 0,
                'message' => '您已添加'
            ]);
        }

        //地区限制
        $ip = ip();
        $city = simpleCurl("http://www.7s6u.cn/api/ip.php?ip={$ip}");
        if (preg_match("/临沂|宁德|泉州/", $city)) {
            return json([
                'errcode' => 0,
                'message' => '地区限制'
            ]);
        }

        //查看上次宣速物流是否五星好评,可以缓存优化
        $xsInfo = Db::name('deal_detail')->field('order,shop,mold')->order('id', 'ASC')->where(['xs_wl' => 1, 'uid' => $this->user['id']])->find();
        if (!empty($xsInfo['id']) && $xsInfo['mold'] != 4) {//退款属性问下
            $order = $xsInfo['order'];
            $shop = $xsInfo['shop'];
            $url = "http://tao.yidaibei.com/taobao/TradeFullinfoGetRequest.php?shop=$shop&tid=$order";
            $xsJson = simpleCurl($url);
            $xsJson = json_decode($xsJson, 1);
            if ($xsJson['trade']['status'] == 'WAIT_BUYER_CONFIRM_GOODS') {
                $taskInfo = CommonTask::getTaskInfoById($xsInfo['taskId']);
                $data['info'] = $taskInfo;
                $data['order'] = $xsJson['order'];

                return json([
                    'errcode' => 10016,
                    'data' => $data,
                    'message' => config('errcode.10016')
                ]);
            }
        }

        //几天内能接单，不考虑淘宝2
        $time = time();
        $taskEndTime = CommonUser::getTaskEndTime($this->user['id']);//上次任务结束时间
        $TaskInterval = config('constant.TaskInterval');
        if ($taskEndTime + $TaskInterval > $time) {//淘宝1
            return json([
                'errcode' => 10017,
                'message' => config('errcode.10017')
            ]);
        }

        //用户当天取消数量,本日取消次数已超过3次，请明天再试
        $maxDayNum = config('constant.MAX_DAY_REFUND_NUM');//3
        $key = config('redisKey.userRefundNum') . $this->user['id'];//用户当天取消数量
        $userRefundNum = Cache::get($key);
        if (!empty($userRefundNum)) {
            if ($userRefundNum > $maxDayNum) {
                return json([
                    'errcode' => 10018,
                    'message' => config('errcode.10018')
                ]);
            }
        }

        //用户师傅当天最大的任务数
        if ($this->user['role'] == '0') {
            $teacherid = $this->user['pid'];//师傅id
            $maxDayNum = config('constant.MAX_DAY_TASK_NUM');//160
            $key = config('redisKey.teacherDayTask') . $teacherid;//师傅当天的徒弟接单数
            $taskNum = Cache::get($key);
            if (!empty($taskNum)) {
                if ($taskNum > $maxDayNum) {
                    return json([
                        'errcode' => 10019,
                        'message' => config('errcode.10019')
                    ]);
                }
            }
        }

        //31天的任务数量，处理任务的时候需要按照此数量，优先分配
        $num = count(CommonUser::getMonthGoodsList($this->user['id'])) - 1;//31天内用户的任务数量
        $type = Request::param('type') == 1 ? 1 : 2;//1普通申请，2排队申请

        //加入有序集合
        RedisExt::getInstance()->hSet("user_queue_addtime", $this->user['id'], time() . ':' . $type);
        RedisExt::getInstance()->zAdd("user_queue", $num, $this->user['id']);

        return json([
            'errcode' => 0,
            'message' => 'OK'
        ]);

        /*
        dump($this->user);die;
        for ($i = 1;$i<10000;$i++) {
            $user = json_encode(['uid'=>$i,'openid'=>$i]);
            RedisExt::getInstance()->zAdd("user_queue", $i, $user);
            //RedisExt::getInstance()->zRem("user_queue",$user);
        }

        dump(12);die;*/
    }

    //获取当前任务状态
    public function status()
    {
        //dump($this->user);die;
        //几天内能接单，不考虑淘宝2
        $time = time();
        $taskEndTime = CommonUser::getTaskEndTime($this->user['id']);//上次任务结束时间
        $data['taskEndTime'] = $taskEndTime;
        $TaskInterval = config('constant.TaskInterval');
        if ($taskEndTime + $TaskInterval > $time) {//淘宝1
            $data['status'] = 1;
            return json([
                'errcode' => 0,
                'data' => $data
            ]);
        }

        $data['taskNum'] = 0;
        $data['nextTaskNum'] = 0;
        //$data['nextTime'] = date('Y-m-d H:i:s',$timei);
        $data['queueType'] = 1;

        $taskStateKey = config('redisKey.userTaskStatus') . $this->user['id'];
        if (Cache::has($taskStateKey)) {
            $arr = explode(':', Cache::get($taskStateKey));
            $data['addTime'] = $arr[0];//任务添加时间
            $data['status'] = 1;//任务正在经行中
            return json([
                'errcode' => 0,
                'data' => $data
            ]);
        } else {
            //先判断1天之内有没有放弃超过3单
            //用户当天取消数量,本日取消次数已超过3次，请明天再试
            $maxDayNum = config('constant.MAX_DAY_REFUND_NUM');//3
            $key = config('redisKey.userRefundNum') . $this->user['id'];//用户当天取消数量
            $userRefundNum = Cache::get($key);
            if (!empty($userRefundNum)) {
                if ($userRefundNum > $maxDayNum) {
                    $data['status'] = 2;
                    return json([
                        'errcode' => 0,
                        'data' => $data
                    ]);
                }
            }

            //获取排队信息
            if ($queueAddInfo = RedisExt::getInstance()->hGet('user_queue_addtime', $this->user['id'])) {
                $queueInfo = explode(':', $queueAddInfo);
                $data['status'] = 4;
                $data['taskTime'] = $queueInfo[0];
                $data['queueType'] = $queueInfo[1];
                return json([
                    'errcode' => 0,
                    'data' => $data
                ]);
            } else {
                //获取当前时间段的任务，如果有,提示任务数和当前排队人数，如果没有,获取下一个时间段的任务数量
                if ($curNum = CommonTask::currentNum($this->user['group'])) {
                    $data['status'] = 5;
                    $data['taskNum'] = $curNum;
                    $data['userNum'] = RedisExt::getInstance()->zCard('user_queue');
                } else {
                    if (date("i") >= 30) {
                        $timei = time() - (date("i") - 30) * 60 - date("s");
                    } else {
                        $timei = time() - date("i") * 60 - date("s");
                    }
                    $data['nextTime'] = date('Y-m-d H:i:s',$timei);
                    $data['status'] = 6;
                    $data['nextTaskNum'] = CommonTask::nextTimeNum($this->user['group']);
                }

                return json([
                    'errcode' => 0,
                    'data' => $data
                ]);
            }
        }
    }

    //获取任务进度
    public function detail()
    {
        $taskStateKey = config('redisKey.userTaskStatus') . $this->user['id'];
        if (Cache::has($taskStateKey)) {
            $arr = explode(':', Cache::get($taskStateKey));
            $taskId = $arr[1];
            $taskInfo = CommonTask::getTaskInfoById($taskId);
            $taskInfo['status'] = 1;
            $taskInfo['addTime'] = $arr[0];
            $taskInfo['deal_id'] = $arr[2];
            //判断是否验证过
            $verifyTime = config('redisKey.verify_goods') . $taskInfo['deal_id'];
            if (Cache::has($verifyTime)) {
                $taskInfo['startTime'] = Cache::get($verifyTime);
                $taskInfo['z'] = Db::name('deal_detail')->where('id',$taskInfo['deal_id'])->value('deal_state');//2是待审核
            } else {
                $taskInfo['z'] = 4;
                $taskInfo['startTime'] = 0;
            }
            //几小时反单
            $taskInfo['tips'] = "0小时返单";
            $taskInfo['h'] = 0;

            return json([
                'errcode' => 0,
                'data' => $taskInfo
            ]);
        } else {
            $taskInfo['status'] = 0;
            return json([
                'errcode' => 0,
                'message' => '没有正在执行的任务哦，请去马上申请',
                'data' => $taskInfo
            ]);
        }
    }

    //继续任务接口
    public function dotask()
    {
        $taskStateKey = config('redisKey.userTaskStatus') . $this->user['id'];
        if (Cache::has($taskStateKey)) {
            $arr = explode(':', Cache::get($taskStateKey));
            $taskId = $arr[1];
            $taskInfo = CommonTask::getTaskInfoById($taskId);
            $taskInfo['status'] = 1;
            $taskInfo['addTime'] = $arr[0];
            $taskInfo['deal_id'] = $arr[2];
            //判断是否验证过
            $verifyTime = config('redisKey.verify_goods') . $taskInfo['deal_id'];
            if (Cache::has($verifyTime)) {
                $taskInfo['startTime'] = Cache::get($verifyTime);
                $taskInfo['z'] = Db::name('deal_detail')->where('id',$taskInfo['deal_id'])->value('deal_state');
            } else {
                $taskInfo['z'] = 4;
                $taskInfo['startTime'] = 0;
            }
            $user = CommonUser::getUserInfoById($this->user['id']);
            $taskInfo['taobao'] = $user['aliwang'];
            //几小时反单
            $taskInfo['tips'] = "0小时返单";
            $taskInfo['h'] = 0;

            return json([
                'errcode' => 0,
                'data' => $taskInfo
            ]);
        } else {
            $taskInfo['status'] = 0;
            $taskInfo['addTime'] = 0;
            return json([
                'errcode' => 0,
                'message' => '没有正在执行的任务哦，请去马上申请',
                'data' => $taskInfo
            ]);
        }
    }

    //任务过期，或者用户取消任务，找到对应的任务正在经行数量减1，用户任务状态删除,处理自动过期
    public function cancel()
    {
        //用户当天的取消单数
        $refundKey = config('redisKey.userRefundNum') . $this->user['id'];

        //当前任务组数
        $currentTaskNumkey = config('redisKey.currentTaskNum');

        $taskStateTimeKey = config('redisKey.userTaskStatus');

        //总任务进行中的数缓存计数，只有取消的时候减一
        $taskDoingNumKey = config('redisKey.taskDoingNum');

        //分任务进行中的数缓存计数，只有取消的时候减一
        $childTaskDoingNumKey = config('redisKey.childTaskDoingNum');
        $cache = RedisExt::getInstance()->hGet('user_task_addtime', $this->user['id']);
        if ($cache) {
            $keyArr = explode(':', $cache);
            if (Cache::has($taskDoingNumKey . $keyArr[1])) {
                Cache::dec($taskDoingNumKey . $keyArr[1]);
            }

            if (Cache::has($childTaskDoingNumKey . $keyArr[2])) {
                Cache::dec($childTaskDoingNumKey . $keyArr[2]);
            }

            if (Cache::has($currentTaskNumkey . $keyArr[3])) {
                Cache::inc($currentTaskNumkey . $keyArr[3]);
            }
            RedisExt::getInstance()->hDel('user_task_addtime', $this->user['id']);

            if (!Cache::has($refundKey)) {//存在缓存
                //23：59：59
                $todayLastSeconds = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
                $cacheTime = $todayLastSeconds - time();
                Cache::set($refundKey, 1, $cacheTime);

            } else {
                //当日取消数量数加一
                Cache::inc($refundKey);
            }

            //更新用户任务细分表
            $taskInfo = Cache::get($taskStateTimeKey . $this->user['id']);
            $arr = explode(':', $taskInfo);
            if (!empty($arr[2])) {
                Db::name('deal_detail')->where('id', $arr[2])->update(['deal_state' => 3]);
            }
            Cache::rm($taskStateTimeKey . $this->user['id']);
            return json([
                'errcode' => 0,
                'message' => '取消成功'
            ]);
        } else {
            return json([
                'errcode' => 10019,
                'message' => config('errcode.10019')
            ]);
        }

    }

    //验证店铺和金额
    public function verify()
    {
        $deal_id= Request::param('deal_id');
        $key = config('redisKey.verify_goods') . $deal_id;
        if (Cache::get($key) !== false) {
            return json([
                'errcode' => 400,
                'message' => config('errcode.400')
            ]);
        }
        Cache::set($key,time(),config('redisKeyExpiretime.verify_goods'));
        return json([
            'errcode' => 0,
            'message' => 'ok'
        ]);
    }

    //用户提交订单号确认完成任务
    public function confirm()
    {
        $order_id = Request::param('order');
        //检测用户的订单号，是否已付款
        if ($order_id) {
            $taskStateKey = config('redisKey.userTaskStatus') . $this->user['id'];
            if ($taskInfo = Cache::get($taskStateKey)) {
                $arr = explode(':', $taskInfo);
                Db::name('deal_detail')->where('id', $arr[2])->update(['order' => $order_id, 'deal_state' => 2]);
                return json([
                    'errcode' => 0,
                    'message' => '提交成功等待审核,审核通过后在我的任务里提现'
                ]);
            } else {
                return json([
                    'errcode' => 10019,
                    'message' => config('errcode.10019')
                ]);
            }
        } else {
            //$order =
            return json([
                'errcode' => 400,
                'message' => config('errcode.400')
            ]);
        }
    }

    //任务提现
    public function withdraw()
    {
        $tid  = Request::param('tid');
        if (empty($tid)) {
            $json['status'] = "error";
            $json['msg']    = "失败!";
            return json($json);
        }

        $redis = RedisExt::getInstance();
        $lock_key = 'LOCK_WITHDRAW:' . $this->user['id'];
        $is_lock = $redis->setnx($lock_key, 1); // 加锁
        if ($is_lock == true) { // 获取锁权限
            $taskInfo = Db::name('deal_detail')
                ->alias('d')
                ->leftJoin('asset_detail a','a.deid=d.id')
                ->field('d.com_money,a.time_withdraw,d.id,d.end_time as okTime,d.shop,d.order,d.true_pay as principal')
                ->where('d.id',$tid)
                ->where('uid',$this->user['id'])
                ->where('deal_state','>',4)
                ->where('deal_state','<',9)
                ->find();

            if (empty($taskInfo['id']) || $taskInfo['time_withdraw']) {
                $json['status'] = "error";
                $json['msg']    = "失败!";
                //删除锁
                $redis->del($lock_key);
                return json($json);
            }
            //$okTime   = $taskInfo['okTime'];
            $shop     = $taskInfo['shop'];
            $order    = $taskInfo['order'];
            $moneyNum = $taskInfo['principal'];

            /* $configInfo = $db->query("setting", "`name` = 'commission'", "Row");
             $config     = $configInfo['value'];
             $config     = json_decode($config, 1);

             $shopList = $db->query("taobaolist", "`name` = '$shop'");
             if ($shopList['id']) {
                 foreach ($config['zj'] as $k => $v) {
                     if ($moneyNum > $v['name'][0] && $moneyNum <= $v['name'][1]) {

                         if (time() - $v['value'] * 60 * 60 <= $okTime) {
                             $json['status'] = 'error';
                             $json['msg']    = "您的订单系统已审核通过,等待商家审核中," . $v['value'] . "小时后才可以申请提现成功哦！";
                             break;
                         }
                     }
                 }
             } else {
                 foreach ($config['sj'] as $k => $v) {
                     if ($moneyNum > $v['name'][0] && $moneyNum <= $v['name'][1]) {

                         if (time() - $v['value'] * 60 * 60 <= $okTime) {
                             $json['status'] = 'error';
                             $json['msg']    = "您的订单系统已审核通过,等待商家审核中," . $v['value'] . "小时后才可以申请提现成功哦！";
                             break;
                         }
                     }
                 }
             }*/

            $moneyNum = $moneyNum + $taskInfo['com_money'];

            $url    = "http://tao.yidaibei.com/taobao/TradeFullinfoGetRequest.php?shop=$shop&tid=$order&xs=1";
            $xsJson = simpleCurl($url);
            $xsJson = json_decode($xsJson, 1);
            if ($xsJson['trade']['orders']['order'][0]['refund_status'] == 'WAIT_SELLER_AGREE' || $xsJson['trade']['orders']['order'][0]['refund_status'] == 'SUCCESS') {
                $json['status'] = "error";
                $json['msg']    = "失败!t";
                //删除锁
                $redis->del($lock_key);
                return json($json);
            }

            $outTradeNo = date("YmdHis") . ($moneyNum * 100) . uniqid().$this->user['id'];    //订单号
            $insert = [
                'uid'=>$this->user['id'],
                'deid'=>$tid,
                'type_withdraw'=>5,
                'deal_code'=>$outTradeNo,
                'money'=>$moneyNum,
                'status'=>2,
                'time_withdraw'=>time(),

            ];
            $id = Db::name('asset_detail')->insertGetId($insert);
            $openId  = $this->user['openid'];      //获取openid

            $payAmount = $moneyNum;             //转账金额，单位:元。转账最小金额为1元
            $trueName  = '';         //收款人真实姓名

            $mchid = config('appidConfig.MCHID');
            $appid = config('appidConfig.SERVICE_APPID');
            $appKey = config('appidConfig.APP_KEY');
            $apiKey = config('appidConfig.API_KEY');
            $wxPay = new WxpayService($mchid, $appid, $appKey, $apiKey);
            $result = $wxPay->createJsBizPackage($openId, $payAmount, $outTradeNo, $trueName);

            if ($result !== true) {
                Db::name('asset_detail')->where('id',$id)->update(['status'=>1,'time_withdraw'=>0]);
                $json['status'] = 'error';
                $json['msg']    = '现在提现人数过多，请稍等2小时再申请或联系操作员' . $result;
                //删除锁
                $redis->del($lock_key);
                return json($json);
            }

            $userInfo = $this->user;
            $user = CommonUser::getUserInfoById($userInfo['id']);
            if ($userInfo['role'] == 1) {
                $teacherInfo = CommonUser::getUserInfoById($userInfo['pid']);
                $teacher = $teacherInfo['username'];
                $pid = $userInfo['pid'];
            } else {
                $pid = $userInfo['id'];
                $teacher = $userInfo['username'];
            }
            $beginToday = mktime(0, 0, 0, date('m'), date('d'), date('Y'));//本日起始
            $endToday   = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y')) - 1;//本日末尾
            $orderNumD  = Db::name('deal_detail')
                ->where('create_time','>',$beginToday)
                ->where('create_time','<',$endToday)
                ->where('pid',$pid)
                ->count();//日订单总数

            //$moneyInfo = $db->query("setting", "`name` = 'money'", "Row");
            //$balance   = $moneyInfo['value']; //微信余额
            $balance   = 0;

            $date['wechatid'] = $mchid;
            $date['explain'] = '本金提现-' . $taskInfo['id'];
            $date['status']  = 1;
            $date['orderId'] = $outTradeNo;

            $date['balance'] = $balance;

            $date['text']    = '-' . $teacher . '-' . $orderNumD . '-' . $userInfo['group'] . '-' . $user['admin'];
            $date['user']    = $userInfo['username'];
            $date['change']  = '-' . $moneyNum;
            $date['name']    = $userInfo['name'];
            $date['openid']  = $userInfo['openid'];
            $date['addTime'] = time();
            Db::name('billing')->insert($date);

            $json['status'] = 'success';
            $json['msg']    = '提现成功';
            //删除锁
            $redis->del($lock_key);
            return json($json);

        } else {
            // 防止死锁
            if ($redis->ttl($lock_key) == -1) {
                $redis->expire($lock_key, 5);
            }
            $json['status'] = "error";
            $json['msg']    = "操作频繁，请于30秒后重试";
            return json($json);
        }
    }


    //佣金提现
    public function withComdraw()
    {
        $order_id = Request::param('order_id');
        //检测用户的订单号，是否已付款
        if ($order_id) {
            $taskStateKey = config('redisKey.userTaskStatus') . $this->user['id'];
            if ($taskInfo = Cache::get($taskStateKey)) {
                $arr = explode(':', Cache::get($taskStateKey));
                Db::name('deal_detail')->where('id', $arr[2])->update(['order' => $order_id, 'deal_state' => 2]);
                return json([
                    'errcode' => 0,
                    'message' => '提交成功等待审核,审核通过后在我的任务里提现'
                ]);
            } else {
                return json([
                    'errcode' => 10019,
                    'message' => config('errcode.10019')
                ]);
            }
        } else {
            //$order =
            return json([
                'errcode' => 400,
                'message' => config('errcode.400')
            ]);
        }
    }
    
    //个人中心页面数据
    public function lists()
    {
        $status = Request::param('status');

        $taskInfos = Db::name('deal_detail')
            ->alias('d')
            ->leftJoin('deal_verify v','v.deid=d.id')
            ->leftJoin('asset_detail a','a.deid=d.id')
            ->field('a.status as withdrawStatus,d.img_link as img,d.id,d.create_time as addTime,d.mold,d.deal_state,d.order,d.true_pay as principal,d.com_money as comMoney,d.shop,d.qx_reason,v.verify_state as payStatus')
            ->order('d.id','DESC')
            ->where('d.uid',$this->user['id'])
            ->select();
        //$userInfo['okNum'] = Db::name('deal_detail')->where('status','>',4)->where('uid',$this->user['id'])->count();
        $taskAll      = [];
        $taskComplete = [];
        $taskCancel   = [];
        $taskOngoing  = [];
        $taskNo       = [];
        $newList      = [];
        foreach ($taskInfos as $k => $v) {
            //$sj   = $db->query("taobaolist", "`name` = '$shop'", "Row");//多久可以提现
            if ($v['payStatus'] == 'WAIT_BUYER_CONFIRM_GOODS' && $v['xs_wl'] != 1) {
                $v['payStatus'] = '等待物流签收确认收货';
            } else if ($v['mold'] != 2) {
                $v['payStatus'] = '请立即五分不带字好评';
            }

            if (empty($v['withdrawStatus'])) {
                $v['withdrawStatus'] = 1;
            }

            if ($v['deal_state'] == 4) {
                $v['cancelNote'] = '30分钟超时系统自动取消';
            }

            //$v['comMoney'] = $v['comMoney'] . ".00";

            if ($v['deal_state'] > 4) {
                $taskComplete[] = $v;
            }

            if ($v['deal_state'] == '3' || $v['deal_state'] == '4') {
                $taskCancel[] = $v;
            }
            if ($v['deal_state'] == '1' || $v['deal_state'] == '2') {
                $taskOngoing[] = $v;
            }
            if ($v['qx_reason'] != 0) {
                $taskNo[] = $v;
            }
            $taskAll[] = $v;
        }


        //$time = $time - 24 * 60 * 60 * 3;

        /*$time2 = $time - 24 * 60 * 60 * 15;
        //$list = $db->query("tasklist", "`user` = '$userName' AND (`class` = 'pjd' OR `addTime` <= '$time') AND (`status` = '1001' OR `status` = '1006')");
        $list = $db->query("tasklist", "`user` = '$userName' AND ( `addTime` <= '$time') AND (`status` = '1001' OR `status` = '1006') AND `addTime` >= '$time2' AND `commentNum` = '0'");
        //print_r($list);

        foreach ($list as $k => $v) {
            $link = $v['babyId'];
            $time = time() - 30 * 24 * 60 * 60;
            $info = $db->query("backup_new_z", "`babyId` = '$link' AND `useTime` <= '$time' ", "Row");
            if (!$info['id'] || $v['text2'] == 'null' || $v['commentNum1'] == 1) {
                unset($list[$k]);
            } else {
                $newList[] = $v;
            }
        }*/

        $json['role']  = 0;
        $json['nums']["goodNum"]     = count($newList);
        $json['nums']["completeNum"] = count($taskComplete);
        $json['nums']["cancelNum"]   = count($taskCancel);
        $json['nums']['ongoingNum']  = count($taskOngoing);
        $json['nums']['allNum']      = count($taskAll);
        $json['nums']['noNum']       = count($taskNo);
        if ($status == 'complete') {
            $json['data'] = $taskComplete;
        } else if ($status == 'cancel') {
            $json['data'] = $taskCancel;
        } else if ($status == 'good') {
            $json['data'] = $newList;
        } else if ($status == 'ongoing') {
            $json['data'] = $taskOngoing;
        } else if ($status == 'all') {
            $json['data'] = $taskAll;
        } else {
            $json['data'] = $taskNo;
        }
        $json['errcode'] = 0;
        return json($json);
    }


    //当前用户信息
    public function user()
    {
        $data = CommonUser::getUserInfoById($this->user['id']);
        if ($data['role'] == 2) {
            $comMoney = Db::name('master_fy')->where('id',$this->user['id'])->value('commission');
            if (empty($comMoney)) {
                $data['comMoney'] = 0;
            } else {
                $data['comMoney'] = $comMoney;
            }
        } else {
            $data['comMoney'] = 0;
        }
        return json([
            'errcode' => 0,
            'data' =>$data
        ]);
    }

    //获取提现详情
    public function flow()
    {
        $flow = Db::name('asset_detail')->where('uid',$this->user['id'])->where('status',2)->field('money,time_withdraw,id,deid')->select();
        return json([
            'errcode' => 0,
            'data' => $flow
        ]);
    }

    //更新订单状态
    public function updateVerify()
    {
        $tid   = Request::param('tid');
        $id   = Request::param('id');
        $shop  = Request::param('shop');
        $redis = RedisExt::getInstance();
        $lock_key = 'LOCK_VERIFY:' . $tid;
        $is_lock = $redis->setnx($lock_key, 1); // 加锁
        if ($is_lock == true) { // 获取锁权限
            $taskInfo = Db::name('deal_verify')->field()
                ->where('uid',$this->user['id'])
                ->where('deid',$id)
                ->find();
            if (empty($taskInfo)) {
                $json['status'] = "error";
                $json['msg']    = "数据不存在";
                return json($json);
            }
            if ($taskInfo['verify_state'] != 'TRADE_FINISHED' && $taskInfo['verify_state'] != 'TRADE_CLOSED') {
                $url                     = "http://tao.yidaibei.com/taobao/TradeFullinfoGetRequest.php?shop=$shop&tid=$tid&xs=1";
                $xsJson                  = curlGet($url);
                $xsJson                  = json_decode($xsJson, 1);
                $status                  = $xsJson['trade']['status'];
                $refund_status           = $xsJson['trade']['orders']['order'][0]['refund_status'];
                $update['refund_status'] = $refund_status;
                $update['verify_state']     = $status;
                Db::name('deal_verify')
                    ->where('uid',$this->user['id'])
                    ->where('deid',$id)
                    ->update($update);
                $json['msg'] = 'up';
            } else {
                $json['msg'] = 'cache';
            }
            //删除锁
            $redis->del($lock_key);
            return json($json);
        } else {
            // 防止死锁
            if ($redis->ttl($lock_key) == -1) {
                $redis->expire($lock_key, 5);
            }
            $json['status'] = "error";
            $json['msg']    = "操作频繁，请于30秒后重试";
            return json($json);
        }

    }

}