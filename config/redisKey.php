<?php

//redis缓存配置键名
return [

    'session:user'		       => 'session:user:',//登入的缓存
    'user'                     => 'user:',//根据id缓存
    'user_status'              => 'user_status:',//根据id缓存用户状态
    'user:name'                => 'user:name:',//根据name缓存
    'userMonthTaskNum'         => 'userMonthTaskNum:',//30天完成的任务数量
    'userTaskEndtime'          => 'userTaskEndtime:',//用户上次任务结束时间缓存
    'teacherDayNum'            => 'teacherDayNum:',//教师当天的邀请人数
    'teacherDayTask'           => 'teacherDayTask:',//师傅当天的徒弟接单数
    'userRefundNum'            => 'userRefundNum:',//用户当天取消单数
    'userTaskStatus'           => 'userTaskStatus:',//用户是否有任务状态值
    'goods_task'               => 'goods_task:',//用户是否有任务状态值
    'taskDoingNum'             => 'taskDoingNum:',//总任务进行计数
    'childTaskDoingNum'        => 'childTaskDoingNum:',//分任务进行计数
    'currentTaskNum'           => 'currentTaskNum:',//组当前时间段任务进行计数
    'nextTimeTaskNum'          => 'nextTimeTaskNum:',//组下个时间段任务进行数量
    'task_deal_with'           => 'task_deal_with:',//用户任务详情
    'verify_goods'             => 'verify_goods:',//用户验证商品价格和店铺时间



    ];