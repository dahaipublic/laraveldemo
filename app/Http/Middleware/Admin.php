<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\User;
use Illuminate\Support\Facades\Log;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $check = Auth::guard('admin')->check();

        if (!$check) {
            //调试代码 测试环境开启 正式关闭
            if (isset($_SERVER['HTTP_ORIGIN']) && ($_SERVER['HTTP_ORIGIN'] != config('app.APP_ADMIN_URL')) && config('app.env') != 'production') {
                $user = User::where('id', 1)->first();
                \Auth::guard('admin')->login($user);
            } else {
                return response()->json(['code' => 405, 'data' => '', 'msg' => trans('app.notLogin')]);
            }

        }

        if ($this->isRelogin($request)) {
            //清空登录数据, 重定向
            Auth::guard('admin')->logout();
            return response()->json(['code' => 409, 'data' => '', 'msg' => trans('app.kickOffOldLoginUser')]);
        }


        //设置语言
        $myLang = Auth::guard('admin')->user()->language;
        if (!empty($myLang)) {
            \App::setLocale($myLang);
        }

        return $next($request);

    }

    /**
     * 判断用户是否 重复登录
     * @param $request
     * @return bool
     */
    protected function isRelogin($request)
    {
        $user = Auth::guard('admin')->user();
        if ($user) {
            $cookieSingleToken = $request->cookie('SECRETAPPLICATIONTOKEN');
            if ($cookieSingleToken) {
                // 从 Redis 获取 time
                $lastLoginTimestamp = Redis::get('ADMIN_STRING_SINGLETOKEN_' . $user->id);
                // 重新获取加密参数加密


                if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];;
                } else {
                    $ip = $request->getClientIp();
                }
                //$redisSingleToken = md5($ip . $user->id . $lastLoginTimestamp);
                $redisSingleToken = md5("5$#dfsauj7bnccDDDcHcmn%1" . $user->id . $lastLoginTimestamp);

                if ($cookieSingleToken != $redisSingleToken) {
                    //认定为重复登录了
                    // Log::debug("Admin Middleware kickOffOldLoginUser  userid: ".$user->id."  ip: ".$ip."  lastLoginTimestamp: ".$lastLoginTimestamp. " redisSingleToken: ".$redisSingleToken." cookieSingleToken: ".$cookieSingleToken);
                    return true;
                }
                return false;
            }
        }
        return false;
    }
}
