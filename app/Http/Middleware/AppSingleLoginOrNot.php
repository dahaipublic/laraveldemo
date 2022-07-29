<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class AppSingleLoginOrNot
{
    protected $header = 'authorization';
    protected $prefix = 'bearer';
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $myapitoken = $this->parse($request);
        App::setLocale('cn');

        if (empty($myapitoken)) {

            return $next($request);
        }
        if (!empty($myapitoken)) {
            $tokenarrays = explode('.', $myapitoken);
            //$userId = Redis::get($tokenarrays[0]);
            //redis 里面存储的用户序列化信息
            $seriUserId = Redis::get($tokenarrays[0]);


//            $mynowtime = time();
//            $ttlTime = config('app.ttl');
//            //token存活时间
//            $needLoginTtl = config('app.needloginttl')*60;
//            $needLoginTtl = empty($needLoginTtl)?2592000:$needLoginTtl;

            if (!empty($seriUserId)) {
//                $unseriUserId = unserialize($seriUserId);
//                $userId = $unseriUserId['uid'];
//                $logintime = $unseriUserId['t'];
//                $allreadyNewToken = $unseriUserId['nt'];
                //设置app语言
                if (empty(Auth::guard('api')->user())) {
                    Log::debug("AppSingleLogin token user not find in database ");
                    //return response()->json(['code'=>405,'msg'=>trans('app.tokenInvalide')]);
                }else{
                    $myLang = Auth::guard('api')->user()->language;
                    if (!empty($myLang)) {
                        \App::setLocale($myLang);
                    }
                }
//                if (!empty($unseriUserId['ot'])) {
//                     Redis::expire($unseriUserId['ot'], 20);
//                    //把这个新 token里面的 老token 删掉，防止下次继续删除（多余动作）
//                    $unseriUserId['ot']='';
//                    //var_dump($unseriUserId);
//                    Redis::set($tokenarrays[0] , serialize($unseriUserId), 'EX', $needLoginTtl);
//                }
              //  $firstLoginTime = Redis::get('API_STRING_SINGLETOKEN_'.$userId);

                // if ($this->isRelogin($tokenarrays[1],$userId,$firstLoginTime,$request->getClientIp())) {
                //     //清空登录数据, 重定向
                //     Redis::del($tokenarrays[0]);
                //     return response()->json(['code'=>405,'msg'=>trans('app.kickOffOldLoginUser')]);
                // }

                //$logintime = Redis::get('API_STRING_SINGLETOKEN_'.$userId);
                // if (empty($userId)) {
                //     return response()->json(['code'=>405,'msg'=>trans('app.tokenInvalide')]);
                // }

                ////TOKEN失效，放回新的token给前端

//                if ($logintime+$ttlTime*60<$mynowtime) {
//
//                    if (!empty($allreadyNewToken)) {
//                        Log::debug("AppSingleLoginOrNot How Dare you use old token again :Old token ".$myapitoken." NT:::  ".$allreadyNewToken);
//                        return $this->setAuthenticationHeader($next($request), $allreadyNewToken);
//                    }else{
//                        //redis 并发上锁
//                        if (Redis::command('set', ['reg_lock_'.$tokenarrays[0], true, 'NX', 'EX', 10])){
//                            if (array_key_exists("HTTP_CF_CONNECTING_IP",$_SERVER))
//                            {
//                                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
//                            }else{
//                                $ip = $request->getClientIp();
//                            }
//
//                            $singleToken = $tokenarrays[1];
//                            $newtoken = $this->gettoken($userId);
//                            //清空登录数据,
//                            // Redis::del($tokenarrays[0]);
//                            $unseriUserId['nt'] = $newtoken.".".$singleToken;
//                            //Redis::set($tokenarrays[0], serialize($unseriUserId) , 'EX', 30);
//                            Redis::set($tokenarrays[0], serialize($unseriUserId), 'EX', $needLoginTtl);
//                            //Redis::set($newtoken, $userId);
//                            $redData = ['uid'=>$userId,'t'=>$mynowtime,'nt'=>'','ot'=>$tokenarrays[0]];
//                            $a = Redis::set($newtoken , serialize($redData), 'EX', $needLoginTtl);
//                            Log::debug("APP AppSingleLoginOrNot Refresh Token :Old token".$myapitoken." New Token: ".$newtoken);
//                            return $this->setAuthenticationHeader($next($request), $newtoken.".".$singleToken);
//                        }else{//没有拿到更新token锁，通过请求
//                            Log::debug("APP AppSingleLoginOrNot I Can't Get The Update Redis Lock :Old token".$myapitoken);
//                            return $next($request);
//                        }
//                    }
//                }//token没超时通过请求
            }
            
        }
        

        return $next($request);
    }

     /**
     * 判断用户是否 重复登录
     * @param $request
     * @return bool
     */
    protected function isRelogin($cookieSingleToken,$userId,$lastLoginTimestamp,$clientIp)
    {   
        if ($cookieSingleToken) {
            // 从 Redis 获取 time
            // 重新获取加密参数加密
            if (array_key_exists("HTTP_CF_CONNECTING_IP",$_SERVER))
            {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];;
            }else{
                $ip = $clientIp;
            }
            $redisSingleToken = md5("_(8)./a6pi4354".$lastLoginTimestamp."53454#$&G43514" . $userId . "989883467a5@F0lH");

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
     * @param  \Illuminate\Http\Request  $request
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
        $str = md5(uniqid(md5(microtime(true).$id),true));  
        $str = sha1($str);  //加密
        return $str;
    }
    /**
     * Try to parse the token from the request header.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return null|string
     */
    public function parse($request)
    {
        $header = $request->headers->get($this->header) ?: $this->fromAltHeaders($request);

        if ($header && preg_match('/'.$this->prefix.'\s*(\S+)\b/i', $header, $matches)) {
            return $matches[1];
        }
    }

    /**
     * Set the authentication header.
     *
     * @param  \Illuminate\Http\Response|\Illuminate\Http\JsonResponse  $response
     * @param  string|null  $token
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function setAuthenticationHeader($response, $token = null)
    {
        $token = $token;
        $response->headers->set('Authorization', 'bearer '.$token);

        return $response;
    }

}
