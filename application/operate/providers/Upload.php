<?php

namespace app\operate\providers;

use app\operate\model\Upload as Model;
use think\exception\ValidateException;
use RedisExt;

class Upload
{
    protected $model;

    public function __construct(Model $model) {
        $this->model = $model;
    }
	/**
     * 图片上传公共方法
     * @param $request 实例化对象
     * @param $upload  自定义上传文件路径
     * @return 文件信息
     */
	public function picture($request,$upload){
		$rule = [
            'size' => 2 * 1024 * 1024,
            'ext' => 'jpg,jpeg,png,gif'
        ];
        $data = $this->upload($request->file('file'), $rule, $upload);
        //数据入库
        $data['aid'] = RedisExt::getInstance()->get('aid');
        $new_data = $this->model->add($data)->toArray();
        return array_intersect_key($new_data,array_flip(['id', 'orig_name', 'pathname']));
    }
    /**
     * 视频上传公共方法
     * @param $request 实例化对象
     * @param $upload  自定义上传文件路径
     * @return 文件信息
     */
    public function video($request,$upload){
        $rule = [];
        $data = $this->upload($request->file('file'), $rule, $upload);
        //数据入库
         $data['aid'] = RedisExt::getInstance()->get('aid');
        $data['type'] = 2;
        $new_data = $this->model->add($data)->toArray();
        return array_intersect_key($new_data,array_flip(['id', 'orig_name', 'pathname']));
    }
    /**
     * 图片视频上传处理
     * @param $file 请求的数据
     * @param $rule 文件验证规则
     */
    protected function upload($file, $rule, $save_path) {
        $info = $file->validate($rule)->move($save_path);
        if ( ! $info)
            throw new ValidateException($file->getError());
        //返回文件信息
        $path = str_replace('\\', '/', $info->getSaveName());
        $name = $info->getInfo('name');
        $name = substr($name, 0, stripos($name, '.'));
        return [
            'orig_name' => $name,
            'ext'       => $info->getExtension(),
            'size'      => $info->getInfo('size'),
            'pathname'      => substr($save_path, 1) .'/'. $path
        ];
    }
}








