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
class AppRequestLog
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
        //将所有请求写日志
        $url = $request->getRequestUri();
        $header = $request->header();
        $allRequest = $request->all();
        $allInfo = [
            'url' => $url,
            'header' => $header,
            'allRequest' => $allRequest,
        ];
        Log::useDailyFiles(storage_path('logs/appRequest/appRequestLog.log'), 180, 'debug');
        Log::info('appRequestLog',$allInfo);
        

        return $next($request);

    }

}
