<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
use think\Response;

if ( ! function_exists('wrong')) {
    /**
     * 获取\think\response\Json对象实例,并返回信息
     * @param        $msg  文本信息
     * @param int    $code 状态码
     * @param string $url  跳转地址
     * @return Response
     */
    function wrong($msg, $code = 500, $url = '') {
        $msg = compact('code', 'msg', 'url');
        return Response::create($msg, 'json', 200, [], []);
    }
}
if ( ! function_exists('output')) {
    /**
     * 获取\think\response\Json对象实例
     * @param array $data 返回的data数据
     * @return Response
     */
    function output($data = []) {
        $code = 200;
        $data = compact('code', 'data');
        return Response::create($data, 'json', 200, [], []);
    }
}

/**
 * 创建指定密码hash
 * @param $password
 * @return bool|mixed|string
 */
function createPassword($password)
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return $hash;
}

/**
 * 检验是否是正确手机号
 * @param $text
 * @return bool|mixed|string
 */
function checkMobile($text)
{
    $search = '/^0?1[3|4|5|6|7|8][0-9]\d{8}$/';
    if (preg_match($search, $text)) {
        return true;
    } else {
        return false;
    }
}

/**
 *按json方式输出通信数据
 * @param integer $code 状态码
 * @param string $message 提示信息
 * @param array $data 数据
 *return string 返回值为json
 */
//fixedme 静态方法？static呢？-----静态方法，构造json数据
function renderJson($code, $message = '', $data = array())
{
    if (!is_numeric($code)) {
        return '';
    }
    $result = array(
        'errcode' => $code,
        'message' => $message,
        'data' => $data
    );
    header('Content-type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

function ip()
{
    //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
}

function doPost($url, $data , $retJson = true ,$setHeader = false){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if ($setHeader) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
        );
    }
    if(!empty($_SERVER['HTTP_USER_AGENT'])){
        curl_setopt($ch, CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
    }
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_URL, $url);
    $ret = curl_exec($ch);
    curl_close($ch);
    if($retJson == false){
        $ret = json_decode($ret);
    }
    return $ret;
}

function simpleCurl($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}
