<?php
/**
 * Created by PhpStorm.
 * User: tyt
 * Date: 2018/10/19
 * Time: 16:10
 */
//常规配置配置
return [
    'MAX_DAY_NUM'		           =>     30,// 师傅当日最大的徒弟数
    'MAX_DAY_TASK_NUM'             =>     160,// 师傅当日最大的徒弟接单数
    'TaskInterval'                 =>     60 * 60 * 30,//间隔接单天数
    'MAX_DAY_REFUND_NUM'           =>     3,//当天最大取消单数，超过这个不能接单
    'WECHAT_WEB_URL'               =>     'http://127.0.0.1:8080/code',//'http://lalala.mdeust6.cn',
    'QUEUE_EXPIRE'                  =>     60 * 20,//用户排队过期时间


];