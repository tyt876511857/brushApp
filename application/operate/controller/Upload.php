<?php

namespace app\operate\controller;

use think\Controller;
use think\Request;

class Upload extends Controller
{
    protected $upload;

    public function __construct(\app\operate\providers\Upload $upload) {
        $this->upload = $upload;
    }

    /**
     * @api {post} operate/comment/praise 图片上传
     * @apiName operate/comment/praise
     * @apiGroup operate-Upload
     *
     * @apiParam {resource} file 文件.
     */
    public function praise(Request $request)
    {
        //自定义图片路径
        $upload = "./static/upload/praise";
        //执行上传,返回文件信息
        return output($this->upload->picture($request,$upload));
    }

    /**
     * @api {post} operate/comment/video 视频上传
     * @apiName operate/comment/video
     * @apiGroup operate-Upload
     *
     * @apiParam {resource} file 文件.
     */
    public function video(Request $request){
        //自定义图片路径
        $upload = "./static/upload/praise";
        //执行上传,返回文件信息
        return output($this->upload->video($request,$upload));
    }

}
