<?php

//redis缓存时间配置
return [
    //登入的缓存
    'session:user'		       => 60 * 60 * 24 * 7,
    'user'                     => 60 * 60 * 6,//根据id缓存
    'user_status'              => 60 * 60 * 6,//根据id缓存用户状态
    'user:name'                => 60 * 60 * 6,//根据name缓存
    'userTaskEndtime'          => 60 * 60 * 24 * 30,//上次任务完成时间缓存
    'userMonthTaskNum'         => 60 * 60 * 24 * 31,//当月任务数量
    'userTaskStatus'           => 60 * 40,//用户接单任务过期时间
    'goods_task'               => 60 * 60 * 24,//任务缓存
    'taskDoingNum'             => 60 * 60 * 6,//总任务进行过期时间
    'childTaskDoingNum'        => 60 * 60 * 6,//分任务进行过期时间
    'currentTaskNum'           => 60 * 30,//组当前时间段任务进行计数
    'task_deal_with'           => 60 * 40,//用户接单任务过期时间
    'verify_goods'             => 60 * 40,//用户验证商品价格和店铺时间

    ];