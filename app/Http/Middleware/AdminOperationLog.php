<?php

namespace App\Http\Middleware;

use App\Models\Admin\SystemOperationLog;
use App\Models\Admin\User;
use Closure;

class AdminOperationLog
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
        $group = $request->is('home/*') ? 'business' : 'admin';

        $user_id = 0;

        if(\Auth::guard($group)->check()) {
            $user_id = (int)\Auth::guard($group)->id();
        }

        $log = [
            'user_id' => $user_id,
            'path'    => $request->path(),
            'method'  => $request->method(),
            'ip'      => $request->getClientIp(),
            'input'   => json_encode($request->input()),
        ];

        if ($group == 'admin' && $request->method()=='POST'){
            $user = User::where('id',$user_id)->where('status', User::STATUS_ALLOW)->first();
            // md5 加密
            $adminlog = new SystemOperationLog();
            $adminlog->ip = $request->getClientIp();
            $adminlog->admin_id = $user_id;
            $adminlog->method = $request->method();
            $adminlog->path = $request->path();
            $adminlog->input = json_encode($request->input());
            //$adminlog->save();

        }

        //操作日志队列 处理并发  降低mysql压力
        //\App\Jobs\AdminOperationLog::dispatch($log);

        return $next($request);
    }
}
