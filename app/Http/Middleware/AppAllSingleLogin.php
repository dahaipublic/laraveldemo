<?php

namespace App\Http\Middleware;

use App\Jobs\DeleteRedisToken;
use App\Models\ModifyUsersPassword;
use App\Models\User;
use Auth;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use \App\Models\Api\LoginToken;

/**
 *
 * - author llc
 *
 */
class AppAllSingleLogin
{
    protected $header = 'authorization';
    protected $prefix = 'bearer';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $timestamp = $request->header('timestamp');
        \App::setLocale('en');
        $salt = config('app.salt');
        if (Auth('api')->check()) {
            if (empty($request->header('authorization'))) {
                return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
            }
            $uid = Auth('api')->id();
            $user = Auth('api')->user();
            $userId = $uid;
            $firstLoginTime = Redis::get(User::STRING_SINGLETOKEN . $userId);
            //单设备登录
            $myapitoken = $this->parse($request);
            if (empty($myapitoken)) {
                return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
            }
            $tokenarrays = explode('.', $myapitoken);
            if (empty($firstLoginTime)) {
                $dbloginlog = \App\Models\UserLogLogin::where("userId", $userId)->select("id", "userId", "redistime")->orderBy("id", "DESC")->first();
                $firstLoginTime = $dbloginlog->redistime;
                Log::debug("AppSingleLogin 405 redis sigle login token not found: " . $myapitoken . $firstLoginTime . "url: " . $request->getRequestUri());
                //Redis::set('API_STRING_SINGLETOKEN_' . $userId, $firstLoginTime);

            } else {
                if ($this->isRelogin($tokenarrays[1], $userId, $firstLoginTime, $request->getClientIp())) {
                    //清空登录数据, 重定向
                    Log::debug("AppSingleLogin 405 loginIsExpire App token: " . $myapitoken . $firstLoginTime . "url: " . $request->getRequestUri());
                    Redis::del($tokenarrays[0]);
                    // 用户信息
                    return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser'), 'error' => 3]);
                }
            }
            $now_time = time();

            if (config('app.APP_ENV') == 'production') {
                if ($request->server('HTTP_BACKDOORKEY', '') !== config('api.BACKDOOR_KEY')) {
                    // 加密
                    if (empty($request->header('sign'))) {
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    }
                    if (empty($timestamp)) {
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    } elseif (!($timestamp < ($now_time + 300) && $timestamp > ($now_time - 300))) { // 判断时间, 五分钟内
//                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser') . 'app_token=' . $user->app_token . 'error=3' . ',qd sign=' .
//                            $request->header('sign') . '&timestamp=' . $timestamp . '&uid=' . $uid .',ht time=' . $now_time . 'code=', 'error' => 'error3']);
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    }
                    if (!Auth('api')->check()) {
                        return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
                    } else {
                        $app_token = Redis::get(User::STRING_APPTOKEN . $uid);
                    }

                    // 加密加密
                    $sign = sha1($uid . '+' . $app_token . '+' . $timestamp . '+' . $salt);
                    if ($sign != $request->header('sign')) {
//                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser') . '&error=4' .
//                            ",uid=" . $uid . ',ht sign=' . $sign . '&timestamp=' . time() . ',qd sign=' . $request->header('sign') . '&qd_timestamp=' . $timestamp . ',ht_app_token=' . $user->app_token . ',code=', 'error' => 'error4']);
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    }
                }
            }
        } else {
            if (config('app.APP_ENV') == 'production') {
                if (!empty($request->header('authorization'))) {
                    return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
                }
                // 过滤一个
                $timestamp = $request->header('timestamp');
                $now_time = time();
                if ($request->server('HTTP_BACKDOORKEY', '') !== config('api.BACKDOOR_KEY')) {
                    // 加密
                    if (empty($request->header('sign'))) {
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    }
                    if (empty($timestamp)) {
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    } elseif (!($timestamp < ($now_time + 300) && $timestamp > ($now_time - 300))) { // 判断时间, 五分钟内
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
                    }
                    if (empty($user)) {
                        $uid = '0';
                        $app_token = config('app.default_app_token');
                    }
                    // 加密加密
                    $sign = sha1($uid . '+' . $app_token . '+' . $timestamp . '+' . $salt);
                    if ($sign != $request->header('sign')) {
                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser')]);
//                        return response()->json(['code' => 415, 'msg' => trans('app.kickOffOldLoginUser') . 'error=8' . '***qd** sign=' . $request->header('sign') . '&qd_timestamp=' . $timestamp .
//                            "&uid=" . $uid . '&ht sign=' . $sign . '&ht~~~ &ht_app_token=' . $app_token . '&ht_sign=' . $sign . ',code=', 'error' => 'error8']);
                    }
                }
            }

        }

        return $next($request);

    }

    /**
     * 判断用户是否 重复登录
     * @param $request
     * @return bool
     */
    protected function isRelogin($cookieSingleToken, $userId, $lastLoginTimestamp, $clientIp)
    {
        if ($cookieSingleToken) {
            // 从 Redis 获取 time 重新获取加密参数加密
            if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } else {
                $ip = $clientIp;
            }
            $redisSingleToken = encryptionToken($userId,$lastLoginTimestamp);
            if ($cookieSingleToken != $redisSingleToken) {
                //认定为重复登录了
                return true;
            }
            return false;
        }

        return false;
    }

    /**
     * Try to parse the token from the request header.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return null|string
     */
    public function parse($request)
    {
        $header = $request->headers->get($this->header) ?: $this->fromAltHeaders($request);

        if ($header && preg_match('/' . $this->prefix . '\s*(\S+)\b/i', $header, $matches)) {
            return $matches[1];
        }
    }


    /**
     * Attempt to parse the token from some other possible headers.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return null|string
     */
    protected function fromAltHeaders($request)
    {
        return $request->server->get('HTTP_AUTHORIZATION') ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION');
    }

}
