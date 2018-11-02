<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/22
 * Time: 10:50
 */

namespace app\common\controller;

use think\Db;
use think\facade\Cache;

class Task
{
    //任务详情
    public static function getTaskInfoById($id)
    {
        $key = config('redisKey.goods_task') . $id;
        $data = Cache::get($key);
        if (empty($data)) {
            $data = Db::name('goods_task')
                ->alias('t')
                ->leftJoin('goods_post p','t.post_id=p.id')
                ->field('t.true_pay,p.is_xs,t.search1,t.search2,t.singular,t.baby_id,t.baby_goods,t.shop,t.goods_price,t.mold,t.new_pass,t.coupon_money,t.keywords,t.img_link,t.return_pay,t.return_num,p.hint,p.pj_coupons')
                ->where([
                    't.id' => $id
                ])
                ->find();
            Cache::set($key, $data, config('redisKeyExpiretime.goods_task'));
        }
        return $data;
    }

    //当前时间的任务数,维持的话，取消或者放弃任务需要维持+1，获取到任务-1，后台添加任务+1
    public static function currentNum($group)
    {
        $childTaskDoingNumKey = config('redisKey.childTaskDoingNum');
        $key = config('redisKey.currentTaskNum') . $group;
        $total = Cache::get($key);
        if ($total === false) {
            $timei = time() - date("i") * 60 - date("s");
            if (date("i") >= 30) {
                $timei = time() - (date("i") - 30) * 60 - date("s");
            }

            $where = [];
            $where[] = ['status', '<', 3];//等待运行,或者未完成的
            //$where[] = ['run_time','>',strtotime(date("Y-m-d"))];//必须当天
            $where[] = ['run_time', '<=', $timei];//当前时间段内运行,4.30,5.00
            $where[] = ['group', '=', $group];
            $list = Db::name('goods_task_queue')->field('id,goods_num,finish_num')->where($where)->select();
            //dump($list);
            $total = 0;
            if (!empty($list)) {
                foreach ($list as $value) {
                    if (Cache::has($childTaskDoingNumKey . $value['id'])) {
                        $total += $value['goods_num'] - $value['finish_num'] - intval(Cache::get($childTaskDoingNumKey . $value['id']));
                    } else {
                        $total += $value['goods_num'] - $value['finish_num'];
                    }
                }
            }
            //dump($total);die;
            //dump(config('redisKeyExpiretime.currentTaskNum'));die;
            Cache::set($key, $total, config('redisKeyExpiretime.currentTaskNum'));
        }
        return $total;
    }

    //下一波时间的任务数
    public static function nextTimeNum($group)
    {
        $key = config('redisKey.nextTimeTaskNum') . $group;
        $data = Cache::get($key);
        if ($data === false) {
            if (date("i") >= 30) {
                $timei = time() - (date("i") - 30) * 60 - date("s");
                $time = (60-date("i"))*60;
            } else {
                $timei = time() - date("i") * 60 - date("s");
                $time = (30-date("i"))*60;
            }

            $where = [];
            $where[] = ['status', '<', 3];//等待运行,或者未完成的
            $where[] = ['run_time','<',$timei];//必须当天
            $where[] = ['run_time', '<=', $timei+60*30];//当前时间段内运行,4.30,5.00
            $where[] = ['group', '=', $group];
            $data = Db::name('goods_task_queue')->where($where)->count();
            Cache::set($key, $data, $time);
        }
        return $data;
    }
}