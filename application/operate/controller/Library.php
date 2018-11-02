<?php

namespace app\operate\controller;

use think\Controller;
use think\Request;

class Library extends Controller
{
    /**
     * 显示资源列表
     *
     * @return \think\Response
     */
    public function index()
    {
        //
    }

    /**
     * 显示创建资源表单页.
     *
     * @return \think\Response
     */
    public function create()
    {
        //
    }

    /**
     * @api {post} operate/library 生成评库数据
     * @apiName operate/library
     * @apiGroup Library
     *
     * @apiParam {Integer} mold 类型: 1.正常评价2.追加评价
     * @apiParam {Int} pid 产品表ID.
     * @apiParam {String} first_praise 初次好评.
     * @apiParam {String} first_img 初次好评图片.
     * @apiParam {String} video 视频.
     * @apiParam {String} append_praise 追加好评.
     * @apiParam {String} append_img 追加图片.
     * @apiParam {String} append_video 追加视频
     * @apiSuccessExample Success-Response:
     *{"code":200 , "message":'添加成功'}
     * @apiErrorExample Error-Response:
     * {"code":500,"message":"错误信息"}
     *
     */
    public function save(Request $request)
    {
        /**1.当执行添加时传 '类型' 传 '产品表ID',传图片表id
        *  2.上传图片页面显示字段 '好评', '好评图片', '视频', '追加好评', '追加好评图片', '追加视频'
        *  3.执行数据添加
        */
        $data = $request->only(['mold','pid','first_praise','first_img','video','append_praise','append_img','append_video']);
        if(!$this->review_library->append($data)) return wrong('添加失败！');
        return wrong('添加成功！','200');
    }

    /**
     * 显示指定的资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function read($id)
    {
        //
    }

    /**
     * 显示编辑资源表单页.
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * 保存更新的资源
     *
     * @param  \think\Request  $request
     * @param  int  $id
     * @return \think\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * 删除指定资源
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function delete($id)
    {
        //
    }
    /*
    * 重载对象
    */
    public function __get($obj)
    {
        switch (strtolower($obj)) {
            case 'review_library':
                return $this->review_library = app(\app\operate\providers\ReviewLibrary::class);
                break;
            default:
                # code...
                break;
        }
    }
}
