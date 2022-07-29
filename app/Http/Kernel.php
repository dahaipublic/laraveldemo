<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
//        \App\Http\Middleware\TrustProxies::class,
//        \Fideloper\Proxy\TrustProxies::class,

    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    //
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class, // TODO
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AdminOperationLog::class,
        ],

        'api' => [
//            \App\Http\Middleware\AppRequestLog::class,
            'throttle:1000,1',
            'bindings',
        ],
//
        'admin' => [
            \App\Http\Middleware\Admin::class,
            'throttle:120,1',
            'bindings',

        ],


        'member' => [
            \App\Http\Middleware\MemberSingleLogin::class,
            'throttle:500,1',
            'bindings',

        ],

        'business' => [
            \App\Http\Middleware\Business::class,
            'throttle:1,1',
            'bindings',

        ],

        'backend' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            // \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        //'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'throttle' => \App\Http\Middleware\ThrottleRequests::class,

        //新增
        'refreshtoken' => \App\Http\Middleware\RefreshToken::class,
        'cors' => \App\Http\Middleware\CORS::class,
        'loginornotlogin' => \App\Http\Middleware\LoginOrNotLogin::class,
        'CheckRole' => \App\Http\Middleware\CheckRole::class,
        'AdminCheckRole' => \App\Http\Middleware\AdminCheckRole::class,

        'MemberSingleLogin' => \App\Http\Middleware\MemberSingleLogin::class,
        'AppSingleLogin' => \App\Http\Middleware\AppSingleLogin::class,
        'AppSingleLoginOrNot' => \App\Http\Middleware\AppSingleLoginOrNot::class,
        'OutsideUserCreate' => \App\Http\Middleware\OutCreateBusiness::class,
        'CheckApiSign' => \App\Http\Middleware\CheckApiSign::class,
        'passportWebapp' => \App\Http\Middleware\PassportCustomProvider::class,
        'Oauthapi' => \App\Http\Middleware\Oauthapi::class,

        'auth.backend' => \App\Http\Middleware\BackEnd::class,
        'guest.backend' => \App\Http\Middleware\GuestBackEnd::class,
        'BoundUser' => \App\Http\Middleware\BoundUser::class,
        'webapp' => \App\Http\Middleware\WebApp::class,
        'EncryptInput' => \App\Http\Middleware\EncryptInput::class,

    ];
}
