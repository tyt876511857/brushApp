<?php
/**
 * Created by PhpStorm.
 * User: aoya
 * Date: 2018/5/29
 * Time: 10:46
 */

namespace app\common\controller;

use app\common\model\User as UserModel;
use RedisExt;
use think\Db;
use think\facade\Cache;

class User
{
    /**
     * 通过用户名获取用户信息
     * @param $name
     * @return string|mixed
     */
    public static function getUserInfoByName($name)
    {
        $key = config('redisKey.user:name') . $name;
        $data = Cache::get($key);
        if (empty($data)) {
            $data = UserModel::getByWhere(['u.name' => $name]);
            /*$data = Db::name('user')
                ->field('id,name,status,username')
                ->where([
                    'name' => $name
                ])
                ->find();*/
            Cache::set($key, $data, config('redisKeyExpiretime.user:name'));
        }
        return $data;
    }

    /**
     * 获取用户审核状态
     * @param $name
     * @return string|mixed
     */
    public static function getUserStatusById($id)
    {
        $key = config('redisKey.user_status') . $id;
        $status = Cache::get($key);
        if (empty($status)) {
            $data = Db::name('user')
                ->field('status')
                ->where([
                    'id' => $id
                ])
                ->find();
            $status = $data['status'];
            Cache::set($key, $status, config('redisKeyExpiretime.user_status'));
        }
        return $status;
    }

    /**
     * 通过id获取用户信息
     * @param $id
     * @return bool|mixed
     */
    public static function getUserInfoById($id)
    {
        $key = config('redisKey.user') . $id;
        $data = Cache::get($key);
        if (empty($data)) {
            $data = UserModel::getDetail($id);
            /*$data = Db::name('user')
                ->field('id,name,status,username')
                ->where([
                    'id' => $id
                ])
                ->find();*/
            Cache::set($key, $data, config('redisKeyExpiretime.user'));
        }
        return $data;
    }

    /**
     * 通过id获取用户上次任务完成时间
     * @param $id
     * @return bool|mixed
     */
    public static function getTaskEndTime($id)
    {
        $key = config('redisKey.userTaskEndtime') . $id;
        $data = Cache::get($key);
        if (empty($data)) {
            $userTaskInfo = Db::name('deal_detail')
                ->field('end_time')
                ->order('id', 'DESC')
                ->where('deal_state','>',4)
                ->where([
                    'uid' => $id
                ])
                ->find();
            if (!empty($userTaskInfo)) {
                $data = $userTaskInfo['end_time'];
                Cache::set($key, $userTaskInfo['end_time'], config('redisKeyExpiretime.userTaskEndtime'));
            } else {
                $data = 0;
            }
        }
        return $data;
    }

    /**
     * 通过id获取用户1个月内任务完成的商品id,用户完成任务时候要修改集合
     * @param $id
     * @return bool|mixed
     */
    public static function getMonthGoodsList($id)
    {
        $data = [-1];
        $key = 'user_30day_goods:' . $id;
        $redis = RedisExt::getInstance();
        if ($redis->exists($key)) {
            return $redis->sMembers($key);
        }
        $time30 = time() - 30 * 24 * 60 * 60;
        $babies = Db::name('deal_detail')->field('baby_id')->where(['uid' => $id])->where('deal_state', '>', 4)->where('create_time', '>', $time30)->select();
        if (!empty($babies)) {
            foreach ($babies as $v) {
                $redis->sAdd($key, $v['baby_id']);
                $data[] = $v['baby_id'];
            }
        }
        //防止是空的，exists检测不出来
        $redis->sAdd($key,-1);
        $redis->expire($key,30 * 24 * 60 * 60);
        return $data;
    }

    /**
     * 通过id获取用户7天内的店铺,用户完成任务时候要修改集合
     * @param $id
     * @return bool|mixed
     */
    public static function getWeekShopList($id)
    {
        $data = ['-1'];
        $key = 'user_7day_shop:' . $id;
        $redis = RedisExt::getInstance();
        if ($redis->exists($key)) {
            return $redis->sMembers($key);
        }
        $time7 = time() - 7 * 24 * 60 * 60;
        $list = Db::name('deal_detail')->field('shop')->where(['uid' => $id])->where('deal_state', '>', 4)->where('create_time', '>', $time7)->select();
        if (!empty($list)) {
            foreach ($list as $v) {
                $redis->sAdd($key, $v['shop']);
                $data[] = $v['shop'];
            }
        }
        //防止是空的，exists检测不出来
        $redis->sAdd($key,-1);
        $redis->expire($key,7 * 24 * 60 * 60);
        return $data;
    }



}