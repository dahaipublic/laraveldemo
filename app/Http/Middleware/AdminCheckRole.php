<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

class AdminCheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $check_login = Auth::guard('admin')->check();
        if (!$check_login){
            return response()->json(['code' => 405, 'data' => '', 'msg' => trans('web.notLogin')]);
        }

        // 某些路由不用验证
        if ($this->shouldPassThrough($request)) {
            return $next($request);
        }

        // 超级管理员主账号登录，则放行
        if (Auth::guard('admin')->user()->isRole('administrator')) {
            return $next($request);
        }

        //用超级管理员子账号登录，检查权限
        $user = Auth::guard('admin')->user();
        $permissions = array();
        // 3该角色的权限缓存在Redis 里面
        //$permissions = Cache::rememberForever('subBusinessPermission_'.$user->id, function()use($user){
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[] = $permission->action;
            }
        }
        if (empty($permissions)) {
            return response()->json(['code' => 409, 'data' => '', 'msg' => trans('web.permissionForbid')]);
        }
        //获取路由Action
        $currentAction = Route::currentRouteAction();
        list($class, $method) = explode('@', $currentAction);

        //获取控制器名.方法名  格式
        $action = strtolower(class_basename($class) . '@' . $method);

        $permissionsCollect = collect($permissions)->filter()->map(function ($method) {
            return strtolower($method);
        });

        if (!$permissionsCollect->contains($action)) {
            return response()->json(['code' => 409, 'data' => '', 'msg' => trans('web.permissionForbid')]);
        } else {
            return $next($request);
        }

    }

    /**
     * Determine if the request has a URI that should pass through verification.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return bool
     */
    protected function shouldPassThrough($request)
    {
        $excepts = [
//            'adm/logingettoken',
//            'adm/setlang',
//            'adm/feedback/grtImAccount',
//            'adm/feedback/getChatCount',  //获取未读消息数量
        ];

        foreach ($excepts as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
