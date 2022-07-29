<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\AppUserProvider;
use App\Auth\AppUserGuard;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\Route;


class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        Auth::provider('appprovider', function(){
            return new AppUserProvider();    // 返回自定义的 user provider
        });
        Auth::extend('appguard', function($app, $name, array $config){
            // dd($app['request']);
            return new AppUserGuard(Auth::createUserProvider($config['provider'],$app['request']),$app['request']);   //返回自定义 guard 实例
        });
        $this->registerPolicies();

        // Passport::withoutCookieSerialization();

        Route::group(['middleware'=> 'passportWebapp'], function () {
            Passport::routes();

        });
        //Passport::routes(null,array('middleware'=> 'auth:webapp'));
        Passport::tokensExpireIn(now()->addDays(30));
        Passport::refreshTokensExpireIn(now()->addDays(30));


    }
}
