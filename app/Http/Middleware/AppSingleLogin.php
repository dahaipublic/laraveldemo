<?php

namespace App\Http\Middleware;

use App\Jobs\DeleteRedisToken;
use App\Models\ModifyUsersPassword;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use \App\Models\Api\LoginToken;

/**
 *
 * - author dahai
 *
 */
class AppSingleLogin
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
        \App::setLocale('cn');
        $myapitoken = $this->parse($request);
        if (empty($myapitoken)) {
            return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire')]);
        }
        $tokenarrays = explode('.', $myapitoken);

        //token存活时间
        $needLoginTtl = config('app.needloginttl') * 60;
        $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;

        //redis 里面存储的用户序列化信息
        $seriUserId = Redis::get($tokenarrays[0]);

        if (empty($seriUserId)) {
            //尝试在数据库里面匹配
            $databaseInfo = \App\Models\Api\LoginToken::where("token", $tokenarrays[0])->first();
            if (empty($databaseInfo)) {
                return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire'), 'error' => 0]);
            } else {
                $user = User::select("status")->where('id', $databaseInfo->uid)->first();
                if (!empty($user)) {
                    if ($user->status != 1) {
                        return response()->json(['code' => 405, 'msg' => trans('app.accountAreBlockLogin')]);
                    } else if (ModifyUsersPassword::where('uid', $databaseInfo->uid)->where('created_at', '>', date('Y-m-d H:i:s', time() - 3600 * 24))->count("id") > 0) {
                        return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire')]);
                    }
                    Redis::set($databaseInfo['token'], $databaseInfo['tokentext'], 'EX', $needLoginTtl);
                    $seriUserId = $databaseInfo['tokentext'];
                } else {
                    return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire'), 'error' => 0]);
                }

            }
        }


        $mynowtime = time();
        $unseriUserId = unserialize($seriUserId);
        $userId = $unseriUserId['uid'];
        $logintime = $unseriUserId['t'];
        $allreadyNewToken = $unseriUserId['nt'];

        $user = User::select("status")->where('id', $userId)->first();
        if (!empty($user)) {
            if ($user->status != 1) {
                return response()->json(['code' => 405, 'msg' => trans('app.accountAreBlockLogin')]);
            }
        }

        //检验是否从用户后台退出登录
//        $checkUidKey = $userId . md5($userId) . 'member';
//        $checkMenberLogout = Redis::get($checkUidKey);
//        if (!empty($checkMenberLogout)) {
//            Redis::del($checkUidKey);
//            return response()->json(['code' => 405, 'msg' => trans('app.memberCheckLogin')]);
//        }

        //app发过来的 token 里面存储了old token,就删除
        if (!empty($unseriUserId['ot'])) {
            Redis::expire($unseriUserId['ot'], 1000);
            //把这个新 token里面的 老token 删掉，防止下次继续删除（多余动作）
            // Log::debug("AppSingleLogin token: ".$myapitoken." token parse to ::  ".$seriUserId." delete old token: ".$unseriUserId['ot']." the new token : ".$unseriUserId['nt']);
            $unseriUserId['ot'] = '';
            Redis::set($tokenarrays[0], serialize($unseriUserId), 'EX', $needLoginTtl);
        }
        $firstLoginTime = Redis::get('API_STRING_SINGLETOKEN_' . $userId);

        if (empty($userId)) {
//            Log::debug("AppSingleLogin 405 token user not find in redis ".$seriUserId." userid: ".$userId."url: ".$request->getRequestUri());
            return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire'), 'error' => 1]);
        }

        $uid = Auth('api')->id();
        $user = Auth('api')->user();
//        //设置app语言
        if (empty($user)) {
//            Log::debug("AppSingleLogin 405 token user not find in database ".$seriUserId." userid: ".$userId."url: ".$request->getRequestUri());
            return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire'), 'error' => 2]);
        }
        $myLang = $user->language ?: 'cn';
        if (!empty($myLang)) {
            \App::setLocale($myLang);
        }


        // 判断是不是线上
        $url = url()->current();
        if (!in_array($uid, [11813, 11818, 11796, 11820])) {
            if (strpos($url, 'chain-chat.app') && $request->server('HTTP_BACKDOORKEY', '') !== config('api.BACKDOOR_KEY')) {
                // 加密
                if (empty($request->header('sign'))) {
                    return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
                }
                if (empty($request->header('timestamp'))) {
                    return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
                } elseif ($request->header('timestamp') < time() - 300) { // 判断时间, 五分钟内
                    return response()->json(['code' => 405, 'msg' => trans('app.kickOffOldLoginUser')]);
                }
                // 加密加密
                $sign = sha1($uid . '+' . $user->app_token . '+' . $request->header('timestamp'));
                if ($sign != $request->header('sign')) {

//                    Log::useFiles(storage_path('AppSingleLogin.log'));
//                    Log::info('AppSingleLogin: '.Auth('api')->id() .', token: '.$tokenarrays[0] .', OS: '.$request->header('OS') .', sign: '.$request->header('sign').', shal: '.sha1($uid.'+'.$user->app_token.'+'.$request->header('timestamp')).', timestamp: '.$request->header('timestamp'));

                    return response()->json(['code' => 405,'msg' => trans('app.kickOffOldLoginUser')]);
                }
            }
        }

        //单设备登录
        if (empty($firstLoginTime)) {
//            $dbloginlog = \App\Models\UserLogLogin::where("userId",$userId)->select("id","userId","redistime")->orderBy("id","DESC")->first();
//            $firstLoginTime = $dbloginlog->redistime;
//            Log::debug("AppSingleLogin 405 redis sigle login token not found: ".$myapitoken."  token parse to : ".$seriUserId."redistime: ".$firstLoginTime."url: ".$request->getRequestUri());
            //Redis::set('API_STRING_SINGLETOKEN_' . $userId, $firstLoginTime);
            return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire'), 'error' => 3]);
        } else {
            if ($this->isRelogin($tokenarrays[1], $userId, $firstLoginTime, $request->getClientIp())) {
                //清空登录数据, 重定向
//                Log::debug("AppSingleLogin 405 loginIsExpire App token: ".$myapitoken."  token parse to : ".$seriUserId."redistime: ".$firstLoginTime."url: ".$request->getRequestUri());
                Redis::del($tokenarrays[0]);
                // 用户信息
                return response()->json(['code' => 405, 'msg' => trans('app.loginIsExpire'), 'error' => 3]);
            }
        }

        //TOKEN失效，放回新的token给前端
//        if ($logintime+$ttlTime*60<$mynowtime) {
        if ($logintime + 5 < $mynowtime) {
            //app 发过来的token 是老token,已经发过一个新token给app了
            if (!empty($allreadyNewToken)) {
                // Log::debug("AppSingleLogin How Dare you use old token again :Old token: ".$myapitoken." NT:::  ".$allreadyNewToken);
                return $this->setAuthenticationHeader($next($request), $allreadyNewToken);
            } else {
                //redis 并发上锁
                if (Redis::command('set', ['reg_lock_' . $tokenarrays[0], true, 'NX', 'EX', 10])) {
                    if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
                    } else {
                        $ip = $request->getClientIp();
                    }

                    $singleToken = $tokenarrays[1];
                    $newtoken = $this->gettoken($userId);
                    //清空登录数据,
                    // Redis::del($tokenarrays[0]);
                    $unseriUserId['nt'] = $newtoken . "." . $singleToken;
                    $seriUnseriUserId = serialize($unseriUserId);
                    Redis::set($tokenarrays[0], $seriUnseriUserId, 'EX', $needLoginTtl);

                    $redData = ['uid' => $userId, 't' => $mynowtime, 'nt' => '', 'ot' => $tokenarrays[0]];
                    $reriaRedData = serialize($redData);
                    Redis::set($newtoken, $reriaRedData, 'EX', $needLoginTtl);
                    \App\Models\Api\LoginToken::updateOrCreate(['uid' => $userId, 'type' => 0], ['tokentext' => $seriUnseriUserId, 'token' => $tokenarrays[0]]);
                    \App\Models\Api\LoginToken::updateOrCreate(['uid' => $userId, 'type' => 1], ['tokentext' => $reriaRedData, 'token' => $newtoken]);
                    //Log::debug("APP AppSingleLogin Token out of time :Old token: ".$myapitoken." New Token".$newtoken.".".$singleToken);
                    return $this->setAuthenticationHeader($next($request), $newtoken . "." . $singleToken);
                } else {//没有拿到更新token锁，通过请求
                    //Log::debug("APP AppSingleLogin I Can't Get The Update Redis Lock :Old token: ".$myapitoken);
                    return $next($request);
                }
            }
        }//token没超时通过请求

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
            // 从 Redis 获取 time
            // 重新获取加密参数加密
            if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } else {
                $ip = $clientIp;
            }
            $redisSingleToken = md5("_(8)./a6pi4354" . $lastLoginTimestamp . "53454#$&G43514" . $userId . "989883467a5@F0lH");

            if ($cookieSingleToken != $redisSingleToken) {
                //认定为重复登录了
                return true;
            }
            return false;
        }

        return false;
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

    public function gettoken($id)
    {
        //生成一个不会重复的字符串
        $str = md5(uniqid(md5(microtime(true)), true) . $id);
        $str = sha1($str);  //加密
        return $str;
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
     * Set the authentication header.
     *
     * @param  \Illuminate\Http\Response|\Illuminate\Http\JsonResponse $response
     * @param  string|null $token
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function setAuthenticationHeader($response, $token = null)
    {
        $response->headers->set('Authorization', 'bearer ' . $token);

        return $response;
    }


}
