<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/29
 * Time: 15:04
 */

namespace app\crontab\controller;

use app\common\controller\User as CommonUser;
use app\common\controller\Task as CommonTask;
use think\Db;
use RedisExt;

class Order
{
    //更新任务详情
    public function update()
    {
        //redis锁

        //获取所有等待的订单

        //获取淘宝接口订单状态

        //根据不同的订单类型，更新淘宝标志,是否宣速物流

        //佣金发放,添加账单纪录

        //释放redis锁
        $taskStateTimeKey = config('redisKey.userTaskStatus');
        $keyTaskEnd = config('redisKey.userTaskEndtime');
        $keyTaskEndTime = config('redisKeyExpiretime.userTaskEndtime');
        $redis = RedisExt::getInstance();
        while (true) {
            $taskList = Db::name('deal_detail')->where('deal_state',2)->select();
            if (!empty($taskList)) {
                foreach ($taskList as $item) {
                    $lock_key = 'LOCK_ORDER:' . $item['id'];
                    $is_lock = $redis->setnx($lock_key, 1); // 加锁
                    if ($is_lock == true) { // 获取锁权限
                        $memo = '';
                        $tkd = 0;
                        $taskListId = $item['id'];
                        //用户信息，其实可以冗余，减少数据库查询
                        $userInfo = CommonUser::getUserInfoById($item['uid']);
                        $userName = $userInfo['username'];
                        echo $userName;                                         //用户名
                        echo PHP_EOL;
                        echo $order = $item['order'];                                     //订单号
                        echo PHP_EOL;
                        echo $taobao = addslashes($item['shop']);                           //店铺名
                        echo PHP_EOL;
                        $taskId = $item['task_id'];                                          //任务id
                        $orderInfoarr = $this->newGetOrderInfo($taobao, $order);                //获取订单信息


                        // print_r($orderInfoarr);
                        //exit();

                        //$userInfo = $db->query("users", "`user` = '$user'", "Row");    //获取用户信息
                        $integral = $userInfo['integral'];
                        $taskInfo = CommonTask::getTaskInfoById($taskId);
                        $flag = 2;                                                       //黄旗

                        if (!empty($taskInfo['coupon_money'])) {
                            $item['principal'] = $taskInfo['coupon_money'];
                        } else {
                            $item['principal'] = $taskInfo['goods_price'];
                        }

                        $verify = [
                            'uid'=>$item['uid'],
                            'deid'=>$item['id'],
                            'shop'=>$item['shop'],
                            'aliwang'=>empty($orderInfoarr['buyer_nick'])?'':$orderInfoarr['buyer_nick'],
                            'good_pay'=>empty($orderInfoarr['payment'])?0:$orderInfoarr['payment'],
                            'order_code'=>$item['order'],
                            'verify_type'=>($orderInfoarr['type'] == 'cod'? 2:1),
                            'verify_state'=>empty($orderInfoarr['status'])?0:$orderInfoarr['status'],
                            'sc_time'=>time()
                        ];
                        //退款单
                        if ($item['mold'] == 4) {
                            if ($orderInfoarr['status'] != 'WAIT_BUYER_PAY' && $orderInfoarr['status'] != 'TRADE_CLOSED_BY_TAOBAO') {
                                $item['principal'] = $taskInfo['return_pay'];
                                $orderInfoarr['status'] = "WAIT_SELLER_SEND_GOODS";
                                $tkd = 1;
                                $memo = "-@-退款单-";
                                $flag = 3;                                              //绿旗
                            }
                        }
                        if ($orderInfoarr['orders']['order'][0]['refund_status'] != 'NO_REFUND' && $orderInfoarr['orders']['order'][0]['refund_status'] != 'CLOSED') {
                            $this->newNote($taobao, $order, "-@-可能退款-$integral", 3);
                            $verify['msg'] = '可能退款';
                            $this->doVerify($item['id'],$verify);
                            //任务改为进行中
                            Db::name('deal_detail')->where('id',$item['id'])->update(['deal_state'=>0]);
                            //删除锁
                            $redis->del($lock_key);
                            $redis->del($taskStateTimeKey.$item['uid']);
                            $redis->set($keyTaskEnd . $item['uid'],$keyTaskEndTime);
                            $redis->hDel('user_task_addtime', $item['uid']);
                            continue;
                        }
                        if ($orderInfoarr['type'] != 'cod' && $orderInfoarr['payment'] == $item['principal']) {                                                                    //判断金额是否一致
                            if ($orderInfoarr['status'] == 'WAIT_SELLER_SEND_GOODS' || $orderInfoarr['status'] == 'WAIT_BUYER_CONFIRM_GOODS') {                                 //判断订单状态
                                if (strtolower(str_replace(array(" ", "　", "\t", "\n", "\r"), '', $item['aliwang'])) != strtolower(trim($orderInfoarr['buyer_nick']))) {    //判断旺旺号是否一致
                                    echo "旺旺号不一致" . PHP_EOL;
                                    //$json['status'] = "error";
                                    //$json['msg'] = "未检测到" . $item['taobao'] . "旺旺号的付款成功信息";
                                    $memo = '-@-不发货同意退款-ww' . $memo;
                                    $msg = "下单旺旺与绑定旺旺不一致";
                                    $this->newNote($taobao, $order, $memo . "-" . $integral, 3);
                                    $verify['msg'] = $msg;
                                    $this->doVerify($item['id'],$verify);
                                    Db::name('deal_detail')->where('id',$item['id'])->update(['deal_state'=>0]);
                                    //删除锁
                                    $redis->del($lock_key);
                                    $redis->del($taskStateTimeKey.$item['uid']);
                                    $redis->set($keyTaskEnd . $item['uid'],$keyTaskEndTime);
                                    $redis->hDel('user_task_addtime', $item['uid']);
                                    continue;
                                }
                                $this->newNote($taobao, $order, $memo . "-" . $integral, $flag);


                                if ($item['mold'] == '2') {   //好评任务
                                    $this->LogisticsOfflineSendRequest($taobao, $order);
                                }

                                $orders['true_pay'] = $orderInfoarr['payment'];
                                $orders['com_money'] = $this->getComMoney($item['principal']);

                                //如果是师傅
                                Db::transaction(function () {
                                    if ($userInfo['pid'] != 0) {
                                        $teacher1Info =  CommonUser::getUserInfoById($userInfo['pid']);
                                        if ($teacher1Info['pid'] == 0) {

                                            //如果没有师傅的师傅
                                            if ($item['principal'] <= 10) {
                                                $comMoney2 = 1.5;
                                            } else {
                                                $comMoney2 = 2;
                                            }

                                            $date['name'] = $teacher1Info['name'];
                                            $date['username'] = $teacher1Info['username'];
                                            $date['explain'] = '徒弟返佣-' . $userName;
                                            $date['change'] = $comMoney2;
                                            Db::name('bill')->insert($date);
                                            $this->doComMoney($teacher1Info['id'],['commission'=>$comMoney2,'uid'=>$teacher1Info['id']]);
                                        } else {
                                            //有师傅的师傅
                                            $comMoney2 = 1;
                                            $comMoney3 = 1;
                                            if ($item['principal'] <= 10) {
                                                $comMoney2 = 1;
                                                $comMoney3 = 0.5;
                                            }

                                            $date['name'] = $teacher1Info['name'];
                                            $date['username'] = $teacher1Info['username'];
                                            $date['explain'] = '徒弟返佣-' . $userName;
                                            $date['change'] = $comMoney2;
                                            Db::name('bill')->insert($date);
                                            $this->doComMoney($teacher1Info['id'],['commission'=>$comMoney2,'uid'=>$teacher1Info['id']]);

                                            $teacher2Info = CommonUser::getUserInfoById($teacher1Info['pid']);
                                            $date['name'] = $teacher2Info['name'];
                                            $date['username'] = $teacher2Info['username'];
                                            $date['change'] = $comMoney3;
                                            $date['explain'] = '徒孙返佣-' . $userName;

                                            Db::name('bill')->insert($date);

                                            $this->doComMoney($teacher2Info['id'],['commission'=>$comMoney3,'uid'=>$teacher2Info['id']]);

                                        }
                                    }

                                    //if ($tkd) {  //如果是退款单
                                    //$users['money'] = $userInfo['money'];
                                    //$users['comMoney'] = $userInfo['comMoney'] + 10;
                                    //}

                                    if ($taskInfo['mold'] == 3) {
                                        $this->LogisticsOfflineSendRequest($taobao, $order);
                                        $orders['xs_wl'] = 1;
                                    }

                                    $users['integral'] = $userInfo['integral'] + 1; //信誉+1
                                    if ($taskInfo['is_xs']) {
                                        $this->LogisticsOfflineSendRequest($taobao, $order);
                                    }

                                    Db::name('user')->where('id',$item['uid'])->update($users);

                                    $orders['end_time'] = time();
                                    $orders['deal_state'] = 4;

                                    Db::name('deal_detail')->where('id',$item['id'])->update($orders);

                                    //完成状态更新
                                    Db::name('goods_task')->where('id',$item['task_id'])->inc('finish_num');
                                    Db::name('goods_task_queue')->where('id',$item['task_queue_id'])->inc('finish_num');
                                    $verify['msg'] = '审核成功';
                                    $this->doVerify($item['id'],$verify);
                                });

                                //redis缓存维持
                                $redis->del($taskStateTimeKey.$item['uid']);
                                $redis->set($keyTaskEnd . $item['uid'],$keyTaskEndTime);
                                $redis->hDel('user_task_addtime', $item['uid']);
                                $redis->sAdd('user_7day_shop:' . $item['uid'],$taskInfo['shop']);
                                $redis->sAdd('user_30day_goods:' . $item['uid'],$taskInfo['baby_id']);
                                $this->newNote($taobao, $order, "-@-$taskListId" . "-" . $integral, $flag);

                            } else { //订单状态异常
                                echo "订单状态异常" . PHP_EOL;
                                $orders = array();
                                $orders['deal_state'] = 0;
                                $verify['msg'] = '订单状态异常';
                                $this->doVerify($item['id'],$verify);
                                Db::name('deal_detail')->where('id',$item['id'])->update($orders);

                                $memo = "-@-" . $userInfo['group'] . '-不发货同意退款-zt' . $memo;
                                $this->newNote($taobao, $order, $memo . "-" . $integral, 3);
                                $this->newNote($taobao, $order, $memo . "-" . $integral, 3);
                                //删除锁
                                $redis->del($taskStateTimeKey.$item['uid']);
                                $redis->set($keyTaskEnd . $item['uid'],$keyTaskEndTime);
                                $redis->hDel('user_task_addtime', $item['uid']);
                                $redis->del($lock_key);
                                continue;
                            }
                        } else { // 金额不对
                            echo "金额不对" . PHP_EOL;
                            $verify['msg'] = '付款金额不对或选择货到付款';
                            $this->doVerify($item['id'],$verify);
                            $orders = array();
                            $orders['deal_state'] = 0;
                            Db::name('deal_detail')->where('id',$item['id'])->update($orders);
                            $memo = "-@-" . $userInfo['userGroup'] . '-不发货同意退款-je' . $memo;
                            $this->newNote($taobao, $order, $memo . "-" . $integral, 3);
                            $this->newNote($taobao, $order, $memo . "-" . $integral, 3);
                            //删除锁
                            $redis->del($taskStateTimeKey.$item['uid']);
                            $redis->set($keyTaskEnd . $item['uid'],$keyTaskEndTime);
                            $redis->hDel('user_task_addtime', $item['uid']);
                            $redis->del($lock_key);
                            continue;
                        }

                        //删除锁
                        $redis->del($lock_key);
                    } else {
                        // 防止死锁
                        if ($redis->ttl($lock_key) == -1) {
                            $redis->expire($lock_key, 5);
                        }
                    }

                }

            } else {
                echo date('Y-m-d H:i:s', time()) . "  无人等待审核\n";
                sleep(2);
            }



        }
    }

    //插入验证表
    public function doVerify($id,$data)
    {
        if (Db::name('deal_verify')->where('deid',$id)->find()) {
            Db::name('deal_verify')->where('deid',$id)->update($data);
        } else {
            Db::name('deal_verify')->insert($data);
        }
    }

    //更新佣金表
    public function doComMoney($id,$data)
    {
        if (Db::name('master_fy')->where('id',$id)->find()) {
            Db::name('master_fy')->where('id', 1)->setInc('commission', $data['commission']);
        } else {
            Db::name('master_fy')->insert($data);
        }
    }


    public function newNote($taobao, $orderId, $memo, $flag)
    {
        $taobao = urlencode($taobao);
        $url = "http://tao.yidaibei.com/taobao/TradeMemoUpdateRequest.php?shop=$taobao&tid=$orderId&mome=$memo&flag=$flag";
        echo $json = simpleCurl($url);
        return $json;
    }

    public function newGetOrderInfo($taobao, $orderId)
    {
        $taobao = urlencode($taobao);
        $url = "http://tao.yidaibei.com/taobao/TradeFullinfoGetRequest.php?shop=$taobao&tid=$orderId";
        $json = simpleCurl($url);
        return json_decode($json, true)['trade'];
    }


    public function LogisticsOfflineSendRequest($shop, $tid)
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        $out_sid = '6' . date("mdHis") . mt_rand(0, 9) . mt_rand(0, 9) . substr($msectime, 10, 3) . mt_rand(0, 9) . mt_rand(0, 9);
        return simpleCurl("http://tao.yidaibei.com/taobao/LogisticsOfflineSendRequest.php?shop=$shop&tid=$tid&out_sid=$out_sid&company_code=宣速物流");
    }

    public function getComMoney($money)
    {
        switch (true) {
            case $money < 300;
                return 3;
            case $money < 500;
                return 4;
            case $money < 600;
                return 5;
            case $money < 700;
                return 6;
            case $money < 800;
                return 7;
            case $money < 900;
                return 8;
            case $money < 1000;
                return 9;
        }

    }



}