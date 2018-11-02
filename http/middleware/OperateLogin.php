<?php
/**
 * 用户登陆状态检测
 */
namespace app\http\middleware;

use think\facade\Session;

class OprateLogin
{
	public function handle($request, \Closure $next)
    {
    	return '1111';
       //if(!Session::get('aid')) return $next($request);
    }
}