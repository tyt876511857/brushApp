<?php

namespace app\operate\model;

use think\Model;
use app\common\traits\CURD;

class Userinfo extends Model
{
	use CURD;
	
    //当前职位
    public function getJobTextAttr($value,$data){
    	$job = [1=>'学生',2=>'自由职业',3=>'待业',4=>'家庭主妇',5=>'工薪族',6=>'务农',7=>'管理人才',8=>'技术人才',9=>'工人',10=>'宝妈',11=>'销售',12=>'客服',13=>'工程',14=>'个体户',
    	'15'=>'会计','16'=>'文员','17'=>'检验员','18'=>'其他'];
    	return $job[$data['job']];
    }
}
