<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/28
 * Time: 10:29
 */
namespace app\common\controller;
use think\facade\Cache;

class Wechat
{
    public $access_token, $appid, $appsecret;

    public function __construct()
    {
        $this->appid = config('appidConfig.SERVICE_APPID');
        $this->appsecret = config('appidConfig.SERVICE_SECRET');
        $this->getAccess_token();
    }

    public function getAccess_token()
    {
        if (Cache::get('access_token')) {
            $this->access_token = Cache::get('access_token');
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $this->appid . "&secret=" . $this->appsecret;
        $text = $this->curl($url);
        $json = json_decode($text, true);
        $this->access_token = $json['access_token'];
        Cache::set('access_token',$this->access_token,7000);
    }

    public function sedTemplate($to, $url = '', $title = '', $order = '', $content = '', $footer = '')
    {
        if (empty($this->access_token)) {
            $this->getAccess_token();
        }
        $json['touser'] = $to;
        //$json['template_id'] = '3pF0ZEMXHLwzVv-kkVcvCQBh4Kp16it202nlVbqQ-0o';
        $json['template_id'] = 'UhjGqevzAC9cvJh0F1fOlXiguXGOBybk3Yzb2mTPIOk';
        $json['url'] = $url;
        $json['topcolor'] = '#FF0000';
        $json['data']['first']['value'] = $title;
        $json['data']['first']['color'] = "#173177";
        $json['data']['keyword1']['value'] = $order;
        $json['data']['keyword1']['color'] = "#173177";
        $json['data']['keyword2']['value'] = date("Y-m-d H:i:s", time());
        $json['data']['keyword2']['color'] = "#173177";
        $json['data']['keyword3']['value'] = $content;
        $json['data']['keyword3']['color'] = "#173177";
        $json['data']['remark']['value'] = $footer;
        $json['data']['remark']['color'] = "#173177";
        $json = json_encode($json);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->access_token;
        $this->curl($url, $json);
    }

    public function SedMessage($to, $title, $description, $url = '', $imgUrl = '')
    {
        $json['touser'] = $to;

        $json['msgtype'] = 'news';
        $json['news']['articles'][0]['title'] = $title;
        $json['news']['articles'][0]['description'] = $description;
        $json['news']['articles'][0]['url'] = $url;
        if ($imgUrl != '') {
            $json['news']['articles'][0]['picurl'] = $imgUrl;
        }
        $json = json_encode($json, JSON_UNESCAPED_UNICODE);
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $this->access_token;
        $this->curl($url, $json);
    }

    public function UpImg($img)
    {
        $type = "image";
        $url = "https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=" . $this->access_token . "&type=" . $type;
        // $file_path = dirname(__FILE__) . "/pro.jpg";
        $file_data = array("media" => new \CURLFile($img));
        $output = $this->curl($url, $file_data);
        return $output;
    }

    public function SedBlackMsg($to, $url = '', $title = '', $order = '', $content = '', $footer = '')
    {
        $json['touser'] = $to;
        $json['template_id'] = 'k87AvSeDLKnShyG5871bKY3PSCb3-23p7QxDEe4u07o';
        $json['url'] = $url;
        $json['topcolor'] = '#FF0000';
        $json['data']['first']['value'] = $title;
        $json['data']['first']['color'] = "#173177";
        $json['data']['keyword1']['value'] = $order;
        $json['data']['keyword1']['color'] = "#173177";
        $json['data']['keyword2']['value'] = $content;
        $json['data']['keyword2']['color'] = "#173177";

        $json['data']['remark']['value'] = $footer;
        $json['data']['remark']['color'] = "#173177";
        $json = json_encode($json);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->access_token;
        $this->curl($url, $json);
    }


    public function SedTipGet($to, $url = '', $title = '', $order = '', $content = '', $footer = '')
    {
        $json['touser'] = $to;
        $json['template_id'] = 'lrhKII1OGMdKNKzf81TrllBjlhkT5mOa704VgLdH-4M';
        $json['url'] = $url;
        $json['topcolor'] = '#FF0000';
        $json['data']['first']['value'] = $title;
        $json['data']['first']['color'] = "#173177";
        $json['data']['keyword1']['value'] = $order;
        $json['data']['keyword1']['color'] = "#173177";
        $json['data']['keyword2']['value'] = $content;
        $json['data']['keyword2']['color'] = "#173177";

        $json['data']['remark']['value'] = $footer;
        $json['data']['remark']['color'] = "#173177";
        $json = json_encode($json);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->access_token;
        $this->curl($url, $json);
    }
    public function SedMoneyMsg($to, $url = '', $title = '', $order = '', $content = '', $footer = '')
    {
        $json['touser'] = $to;
        $json['template_id'] = 'gyptIN9UpBQfKI2Q5CtuvNxC59K10vuFLqEMlNT-YZY';
        $json['url'] = $url;
        $json['topcolor'] = '#FF0000';
        $json['data']['first']['value'] = $title;
        $json['data']['first']['color'] = "#173177";
        $json['data']['keyword1']['value'] = $order;
        $json['data']['keyword1']['color'] = "#173177";
        $json['data']['keyword2']['value'] = $content;
        $json['data']['keyword2']['color'] = "#173177";

        $json['data']['remark']['value'] = $footer;
        $json['data']['remark']['color'] = "#173177";
        $json = json_encode($json);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $this->access_token;
        $this->curl($url, $json);
    }
    public function curl($url, $post = '')
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $data = curl_exec($ch);
        return $data;
    }
}

