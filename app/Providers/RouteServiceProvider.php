<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapBusinessHomeRoutes();

        $this->mapAdminRoutes();

        $this->mapBackEndRoutes();

        $this->mapMemberRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapBusinessHomeRoutes()
    {
        Route::prefix('home')
            ->middleware('web');
    }
//
    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    //
    protected function mapAdminRoutes()
    {
        Route::prefix('adm')
            ->middleware('web')
            ->namespace($this->namespace."\Admin")
            ->group(base_path('routes/admin.php'));
    }

    protected function mapBackEndRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware('backend')
            ->group(base_path('routes/backend.php'));
    }

    protected function mapMemberRoutes()
    {
        Route::prefix('member')
            ->middleware('web')
            ->namespace($this->namespace."\Member")
            ->group(base_path('routes/member.php'));
    }

}
