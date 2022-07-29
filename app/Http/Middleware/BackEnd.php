<?php

namespace App\Http\Middleware;

use Closure;

class BackEnd
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
        if (\Auth::guard('backend')->check()) {
            return $next($request);
        }
        return redirect('backend/login');
    }
}
