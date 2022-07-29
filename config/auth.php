<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'webapp',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "session", "token"
    |   'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    |
    */

    'guards' => [

        'api' => [
            'driver' => 'appguard',
            'provider' => 'users',
        ],
        'webapp' => [
            'driver' => 'session',
            'provider' => 'webappusers',
        ],
        'webappapi' => [
            'driver' => 'passport',
            'provider' => 'webappusers',
        ],
        'member' => [
            'driver' => 'session',
            'provider' => 'member',
        ],
        'admin' => [
            'driver' => 'session',
            'provider' => 'admin',
        ],
        'business' => [
            'driver' => 'session',
            'provider' => 'business',
        ],
        'subbusiness' => [
            'driver' => 'session',
            'provider' => 'subbusiness',
        ],
        'backend' => [
            'driver' => 'session',
            'provider' => 'backend',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'appprovider',
            'model' => App\Models\User::class,
        ],
        'business' => [
            'driver' => 'eloquent',
            'model' => App\Models\Business\User::class,
        ],
        'webappusers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Api\Webappuser::class,
        ],
        'subbusiness' => [
            'driver' => 'eloquent',
            'model' => App\Models\Business\SubUser::class,
        ],
        'member' => [
            'driver' => 'eloquent',
            'model' => App\Models\Member\User::class,
        ],
        'admin' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin\User::class,
        ],
        'backend' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin\User::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one user table or model in the application and you want to have
    | separate password reset settings based on the specific user types.
    |
    | The expire time is the number of minutes that the reset token should be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 600,
        ],
        'business' => [
            'provider' => 'business',
            'table' => 'business_password_resets',
            'expire' => 60,
        ],
        'subbusiness' => [
            'provider' => 'business',
            'table' => 'sub_business_password_resets',
            'expire' => 60,
        ],
        'member' => [
            'provider' => 'member',
            'table' => 'password_resets',
            'expire' => 60,
        ],
        'admin' => [
            'provider' => 'admin',
            'table' => 'system_account_resets',
            'expire' => 60,
        ],
    ],

];
