<?php

namespace app\operate\controller;

use think\Controller;
use think\Request;
use think\Db;

class release extends Controller
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
     * @api {post} operate/task/release 发布任务
     * @apiName operate/task/release
     * @apiGroup operate-Task
     *
     * @apiParam {String} param 文件形式.
     *
     * @apiSuccessExample Success-Response:
     *
     *{"code":200,'message':'添加成功'}
     *
     * @apiErrorExample Error-Response:
     *
     * {"errcode":500,"message":"错误信息"}
     *
     */

    public function save(Request $request)
    { 
        /*1.根据主键在添加附表数据,评价单使用优惠券(pjdyh),有淘口令(pj_word)
          2.根据添加的日期,点数多少单,类型,关键字,保存相对应的时间戳
          3.判断多组优惠是否存在,自动调组则清空,根据相对应的选择组,时间点数添加
          多少单.
        */
        //启动事物
        Db::startTrans();
        try{
            //主表添加数据返回主键
            $data = $request->only(['run_way','attache','operator','art_designer','pj_why','pm','xp_per',
            'group','level','key_num','category','is_group','is_virtual','pj_coupons','hint']);
            $insertId = $this->goods_post->append($data);
            //添加子表数据
            $list = $request->except(['run_way','attache','operator','art_designer','pj_why','pm','xp_per','key_num','category','is_virtual']);
            $sum = $this->goods_task->append($list, $insertId);
            if(!$sum) return wrong('添加失败！');
            //统计总单数保存至数据表相对应的字段
            $upid = $this->goods_post->count_singular($sum,$insertId);
            if($upid) Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return wrong('添加失败！');
        }
        
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
            case 'goods_post':
                return $this->goods_post = app(\app\operate\providers\GoodsPost::class);
                break;
            case 'goods_task':
                return $this->goods_task = app(\app\operate\providers\GoodsTask::class);
                break;
            default:
                # code...
                break;
        }
    }
}
