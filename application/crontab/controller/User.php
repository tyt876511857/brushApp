<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 14:46
 */

namespace app\crontab\controller;

use app\common\controller\Task as CommonTask;
use app\common\controller\User as CommonUser;
use app\common\controller\Wechat as CommonWechat;
use RedisExt;
use think\Db;
use think\facade\Cache;

class User
{
    //处理用户的任务
    public function run()
    {
        $wechat = new CommonWechat();
        $dayTime = strtotime(date("Y-m-d"));
        $webUrl = config('constant.WECHAT_WEB_URL');
        $expire = config('constant.QUEUE_EXPIRE');

        //师傅最大的当日单量
        $teacherDayTask = config('redisKey.teacherDayTask');

        //当前任务组数
        $currentTaskNumkey = config('redisKey.currentTaskNum');

        //用户当前唯一单数
        $taskStateTime = config('redisKeyExpiretime.userTaskStatus');
        $taskStateTimeKey = config('redisKey.userTaskStatus');

        //总任务进行中的数缓存计数，只有取消的时候减一
        $taskDoingNumTime = config('redisKeyExpiretime.taskDoingNum');
        $taskDoingNumKey = config('redisKey.taskDoingNum');

        //分任务进行中的数缓存计数，只有取消的时候减一
        $childTaskDoingNumTime = config('redisKeyExpiretime.childTaskDoingNum');
        $childTaskDoingNumKey = config('redisKey.childTaskDoingNum');
        $redis = RedisExt::getInstance();
        while (true) {
            //获取有序集合
            if ($queueList = $redis->zRange("user_queue", 0, -1)) {
                echo '获取排队人' . PHP_EOL;
                sleep(2);
                foreach ($queueList as $value) {
                    $time = time();
                    $users = CommonUser::getUserInfoById($value);
                    $queueAddInfo = $redis->hGet('user_queue_addtime', $value);
                    if (empty($queueAddInfo)) {
                        $redis->zRem("user_queue", $value);
                        continue;
                    } else {
                        $queueInfo = explode(':',$queueAddInfo);
                        $queueAddTime = $queueInfo[0];
                        $queueType = $queueInfo[1];
                    }

                    echo $users['username'] . '排队时间:' . ($time - $queueAddTime) . PHP_EOL;
                    //任务时间是否超时
                    if ($queueAddTime + $expire <= $time && $queueType != 2) {
                        $redis->zRem("user_queue", $value);
                        $redis->hDel("user_queue_addtime",$value);
                        echo $users['username'] . "超时" . PHP_EOL;
                        continue;
                    }

                    //是否已经有任务
                    if (Cache::get($taskStateTimeKey . $users['id'])) {
                        $redis->zRem("user_queue", $value);
                        $redis->hDel("user_queue_addtime",$value);
                        echo $users['username'] . "已经有任务" . PHP_EOL;
                        continue;
                    }

                    //echo $value['addTime'] + 60 * 5 . PHP_EOL;

                    //dump($users);die;
                    //黑名单判断,集合缓存提升性能
                    /*if (Db::name('blacktable')->where('ww',$users['aliwang'])->find()) {
                        echo "黑名单" . PHP_EOL;
                        $upLog['text'] = "黑名单旺旺，跳过" . $users['aliwang'];
                        //$db->insert("task_log", $upLog);
                        continue;
                    }*/

                    $timei = time() - date("i") * 60 - date("s");
                    if (date("i") >= 30) {
                        $timei = time() - (date("i") - 30) * 60 - date("s");
                    }

                    $where = [];
                    $where[] = ['status', '<', 3];//等待运行,或者未完成的
                    //$where[] = ['run_time','>',$dayTime];//必须当天
                    $where[] = ['run_time', '<=', $timei];//当前时间段内运行,4.30,5.00
                    if ($queueAddTime + 60 * 1 > $time) {
                        $where[] = ['group', '=', $users['group']];
                    }

                    //获取任务细分表,可以考虑放到最上面，导致不能实时增加执行的任务，但是性能好
                    $taskQueue = Db::name('goods_task_queue')->where($where)->order('level', 'ASC')->select();
                    echo Db::name('a')->getLastSql();
                    if (!empty($taskQueue)) {
                        foreach ($taskQueue as $k => $v) {

                            $taskId = $v['task_id'];
                            $taskInfo = CommonTask::getTaskInfoById($taskId);
                            echo '任务数量：' . $taskInfo['singular'] . PHP_EOL;

                            //当前总任务完成数和进行中的数考虑缓存
                            $taskDoingKey = $taskDoingNumKey . $taskId;
                            if ($taskDongingNum = Cache::get($taskDoingKey)) {
                                if ($taskDongingNum >= $taskInfo['singular']) {
                                    echo $taskId . "总任务超量" . PHP_EOL;
                                    continue;
                                }
                            } else {
                                Cache::set($taskDoingKey, 0, $taskDoingNumTime);
                            }

                            //当前分任务完成数和进行中的数考虑缓存
                            $childTaskDoingKey = $childTaskDoingNumKey . $v['id'];
                            if ($childTaskDongingNum = Cache::get($childTaskDoingKey)) {
                                if ($childTaskDongingNum + $v['finish_num'] >= $v['goods_num']) {
                                    echo $v['id'] . "分任务超量" . PHP_EOL;
                                    continue;
                                }
                            } else {
                                Cache::set($childTaskDoingKey, 0, $childTaskDoingNumTime);
                            }
                            /*$taskFinish = Db::name('deal_detail')->where('task_id',$taskId)->where('deal_state','<','3')->count();
                            if ($taskFinish >= $taskInfo['singular']) {
                                Db::name('goods_task_queue')->where('id',$v['id'])->update(['status'=>3]);
                                echo $taskId . "超量" . PHP_EOL;
                                continue;
                            }*/

                            usleep(1000);

                            //30天内是否做过
                            $babyId = $taskInfo['baby_id'];
                            if (in_array($babyId, CommonUser::getMonthGoodsList($users['id']))) {
                                echo $users['username'] . "30天内做过该链接：" . PHP_EOL;
                                $upLog['text'] = "30天内做过该链接，跳过";
                                // $db->insert("task_log", $upLog);
                                echo $babyId;
                                echo PHP_EOL;
                                continue;
                            }
                            usleep(10000);

                            //判断用户是否7天内做过该店铺
                            if (in_array($taskInfo['shop'], CommonUser::getWeekShopList($users['id']))) {
                                echo $users['username'] . "7天做过该店铺：" . $taskInfo['shop'] . PHP_EOL;
                                $upLog['text'] = "7天做过该店铺，跳过" . $taskInfo['shop'];
                                // $db->insert("task_log", $upLog);
                                continue;
                            }

                            //积分判定
                            $okNum = $users['integral'];
                            $omoney = empty($taskInfo['pass_new']) ? $taskInfo['goods_price'] : $taskInfo['goods_price'] - $taskInfo['coupon_money'];
                            if ($users['username'] != '邢慧杰') {

                                if ($okNum == 0 && $omoney >= 100) {
                                    echo "信誉点" . $okNum . ",超过100元,跳过" . PHP_EOL;

                                    /*   $upLog['text'] = "信誉点".$okNum.",超过100元,跳过" . $text;
                                       $db->insert("task_log", $upLog);*/
                                    continue;
                                }
                                if ($okNum < 2 && $omoney >= 200) {
                                    echo "超过200元" . PHP_EOL;
                                    /*$upLog['text'] = "信誉点".$okNum.",超过200元,跳过" . $text;
                                    $db->insert("task_log", $upLog);*/
                                    continue;
                                }
                                if ($okNum < 4 && $omoney >= 300) {
                                    echo "超过300元" . PHP_EOL;
                                    /*  $upLog['text'] = "信誉点".$okNum.",超过300元,跳过" . $text;
                                      $db->insert("task_log", $upLog);*/
                                    continue;
                                }

                                if ($omoney >= 400 && $okNum < 6) {
                                    echo "超过400元" . PHP_EOL;

                                    continue;
                                }
                            }

                            $sqlData = array();
                            $sqlData['task_id'] = $v['task_id'];
                            $sqlData['pid'] = $users['pid'];
                            $sqlData['task_queue_id'] = $v['id'];
                            //$sqlData['username'] = $users['username'];
                            $sqlData['uid'] = $value;
                            $sqlData['shop'] = $taskInfo['shop'];
                            $sqlData['aliwang'] = $users['aliwang'];
                            $sqlData['img_link'] = $taskInfo['img_link'];

                            $sqlData['true_pay'] = $omoney;
                            $sqlData['baby_id'] = $babyId;
                            $sqlData['mold'] = $taskInfo['mold'];
                            $sqlData['com_money'] = 3;
                            $sqlData['teacher_money'] = 1.5;//$taskInfo['teacherMoney'];//这里自己按照规则计算
                            $sqlData['create_time'] = time();
                            $sqlData['group'] = $users['group'];


                            //$url = "http://lalala.mdeust6.cn/api/r.php?babyId=$babyId&user={$users['username']}";

                            //$repeatJson = $this->getR($db, $babyId, $users['username']);
                            //$repeatJson = json_decode($repeatJson, 1);

                            //$repeat = $repeatJson['repeat'];
                            /*if ($repeatJson['no'] == 1) {
                                echo "前2" . PHP_EOL;

                                $upLog['text'] = "链接排名前二，跳过" . $text;
                                // $db->insert("task_log", $upLog);
                                //continue;
                            }
                            if ($repeat >= 0.5) {
                                echo "重复率" . PHP_EOL;
                                $upLog['text'] = "大于20%，跳过" . $text;
                                // $db->insert("task_log", $upLog);
                                continue;
                            }
                            $sqlData['repeat'] = $repeat;*/


                            if ($id = Db::name('deal_detail')->insertGetId($sqlData)) {
                                //dump($taskStateTime.':'.$v['task_id']);die;
                                //用户已接任务状态
                                Cache::set($taskStateTimeKey . $users['id'], $sqlData['create_time'].':'.$v['task_id'].':'.$id, $taskStateTime);

                                //用户级任务时间，取消任务用
                                $redis->hSet("user_task_addtime", $users['id'], $sqlData['create_time'].':'.$v['task_id'].':'.$v['id'].':'.$v['group']);

                                //总任务进行中的数量
                                Cache::inc($taskDoingKey);

                                //分任务进行中的数量
                                Cache::inc($childTaskDoingKey);

                                //当前该组任务数减1
                                if (Cache::get($currentTaskNumkey.$v['group'])>0) {
                                    Cache::dec($currentTaskNumkey.$v['group']);
                                }

                                //师傅当天的徒弟接单数
                                if (!empty($users['pid'])) {
                                    if (!Cache::has($teacherDayTask.$users['pid'])) {
                                        $cacheTime = mktime(23, 59, 59, date('m'), date('d'), date('Y')) - time();
                                        Cache::set($teacherDayTask.$users['pid'],1,$cacheTime);
                                    } else {
                                        Cache::inc($teacherDayTask.$users['pid']);
                                    }
                                }

                                //出排队
                                $redis->zRem("user_queue", $value);
                                $redis->hDel("user_queue_addtime",$value);

                                //发送微信通知
                                $wechat->sedTemplate($users['noticeOpenid'], $webUrl, '成功申请到任务', '', '申请成功', '任务申请成功，赶紧去完成吧');
                                $upLog['text'] = "成功申请到任务";
                                //$db->insert("task_log", $upLog);
                                break;

                            } else {
                                echo "数字异常" . $taskId . PHP_EOL;
                                continue;
                            }
                            usleep(1000);
                        }

                    }

                    //没任务
                }
                sleep(1);

            } else {
                echo date('Y-m-d H:i:s', time()) . "  无人排队\n";
                sleep(2);
            }

        }

    }

    //取消任务
    public function cancel()
    {
        $redis = RedisExt::getInstance();

        //用户当天的取消单数
        $refundKey = config('redisKey.userRefundNum');

        //当前任务组数
        $currentTaskNumkey = config('redisKey.currentTaskNum');

        //用户当前唯一单数
        $taskStateTime = config('redisKeyExpiretime.userTaskStatus')-10;
        $taskStateTimeKey = config('redisKey.userTaskStatus');

        //总任务进行中的数缓存计数，只有取消的时候减一
        $taskDoingNumKey = config('redisKey.taskDoingNum');

        //分任务进行中的数缓存计数，只有取消的时候减一
        $childTaskDoingNumKey = config('redisKey.childTaskDoingNum');
        while (true) {
            $keyList = $redis->hKeys('user_task_addtime');
            if (!empty($keyList)) {
                foreach ($keyList as $uid) {
                    $keyArr = explode(':', $redis->hGet('user_task_addtime', $uid));
                    if (empty($keyArr[1]) || empty($keyArr[2]) || empty($keyArr[3]) || empty($keyArr[0])) {
                        $redis->hDel('user_task_addtime', $uid);
                        continue;
                    }
                    //任务过时
                    if (time() - intval($keyArr[0]) > $taskStateTime) {

                        if (Cache::has($taskDoingNumKey . $keyArr[1])) {
                            Cache::dec($taskDoingNumKey . $keyArr[1]);
                        }

                        if (Cache::has($childTaskDoingNumKey . $keyArr[2])) {
                            Cache::dec($childTaskDoingNumKey . $keyArr[2]);
                        }

                        if (Cache::has($currentTaskNumkey . $keyArr[3])) {
                            Cache::inc($currentTaskNumkey . $keyArr[3]);
                        }

                        $key = $refundKey.$uid;
                        if (!Cache::has($key)) {//不存在缓存
                            //23：59：59
                            $todayLastSeconds = mktime(23, 59, 59, date('m'), date('d'), date('Y'));
                            $cacheTime = $todayLastSeconds - time();
                            Cache::set($key, 1, $cacheTime);

                        } else {
                            //当日取消数量数加一
                            Cache::inc($key);
                        }

                        //更新用户任务细分表
                        $taskInfo = Cache::get($taskStateTimeKey . $uid);
                        $arr = explode(':',$taskInfo);
                        if (!empty($arr[2])) {
                            Db::name('deal_detail')->where('id',$arr[2])->update(['deal_state'=>4]);
                        }
                        Cache::rm($taskStateTimeKey . $uid);
                        $redis->hDel('user_task_addtime', $uid);
                    } else {
                        echo date('Y-m-d H:i:s', time()) . "  {$uid}:任务未过期\n";
                        sleep(2);
                    }
                }
            } else {
                echo date('Y-m-d H:i:s', time()) . "  无人获取到任务\n";
                sleep(2);
            }
        }
    }


}