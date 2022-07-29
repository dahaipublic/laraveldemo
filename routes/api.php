<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
//测试路由
Route::any('get/errorinfo', 'Api\LoginController@uploadError')->name('uploadError');
Route::any('get/getuseropenid', 'Api\LoginController@getuseropenid')->name('getuseropenid');
Route::any('get/loginWx', 'Api\LoginController@loginWx')->name('loginWx');

Route::group(['prefix' => 'auth', 'namespace' => 'Api'], function ($router) {
    Route::post('login', 'LoginController@login')->middleware('throttle:10,1');
    Route::post('slideImg', 'LoginController@slideImg');
    Route::post('checkSlideImg', 'LoginController@checkSlideImg');
    Route::post('checkppenlogin', 'LoginController@checkOpenLogin');
    Route::post('checkpaypin', 'LoginController@checkPayPin');
    Route::post('register', 'LoginController@register')->middleware('throttle:10,1');
    Route::post('registerNew', 'LoginController@registerNew')->middleware('throttle:10,1');
    Route::post('registerTest', 'LoginController@registerTest');
    Route::post('reg', 'LoginController@reg')->middleware('throttle:10,1');
    Route::post('resetpin', 'UserController@resetPin'); // 修改密码
    Route::post('getarea', 'LoginController@getarea');
    Route::post('sarea', 'LoginController@searchArea');
    Route::post('refresh', 'LoginController@refresh');
    Route::get('agreement', 'LoginController@agreement');
});

Route::group(['middleware' => 'AppSingleLoginOrNot', 'prefix' => 'auth', 'namespace' => 'Api'], function ($router) {
    Route::post('sendmsg', 'UserController@sendMsg')->middleware('throttle:10,1');
    Route::post('sendemail', 'UserController@sendMail')->middleware('throttle:10,1');
    Route::post('msgcheckcode', 'UserController@msgVerifyCode')->middleware('throttle:10,1');
    Route::post('applanglist', 'LoginController@applanglist');
    Route::post('captcha', 'UserController@captcha');
    Route::post('checkCaptcha', 'UserController@checkCaptcha');
    Route::post('authenterprise', 'UserController@authEnterprise');
    Route::post('upenterprise', 'UserController@updateEnterprise');
    Route::post('getenterprisecategory', 'UserController@getEnterpriseCategory');
    Route::post('getenterpriseinfo', 'UserController@getEnterpriseInfo');
});
Route::group(['middleware' => 'AppSingleLogin', 'prefix' => 'user', 'namespace' => 'Api'], function ($router) {
    Route::post('userInfo', 'UserController@userInfo');
    Route::post('logout', 'UserController@logout');
    Route::post('updateprofile', 'UserController@update');
    Route::post('setpin', 'UserController@setPin'); // 设置密码 / 忘记密码
    Route::post('changepin', 'UserController@changePin');
    Route::post('resetphone', 'UserController@resetPhone');
    Route::post('resetemail', 'UserController@resetEmail');
    Route::post('verifyCode', 'UserController@verifyCode');
    Route::post('changephone', 'UserController@tihuanPhone');
    Route::post('changeemail', 'UserController@tihuanEmail');
    Route::post('locklogin', 'UserController@locklogin');
    Route::post('recommend', 'UserController@genRecommend');
    Route::post('setfinger', 'UserController@setFingerLogin');
    Route::post('makewalletaddress', 'UserController@genrateWallet');
    Route::post('setlang', 'UserController@setlang');
    Route::post('phonegetuser', 'UserController@phoneGetUser');
    Route::post('rstpcheckcode', 'UserController@resetphoneVerifyCode');
    Route::post('getRegionInfo', 'UserController@getRegionInfo');
    Route::post('getSetupPanel', 'UserController@getSetupPanel');


    Route::post('checkpi', 'UserController@verifyPin')->middleware('throttle:20,1');
    Route::post('checkpa', 'UserController@verifyPassword')->middleware('throttle:20,1');

    Route::post('changeMoney', 'UserController@changeMoney');
    Route::post('setSecretMoney', 'UserController@setSecretMoney');

    //Route::post('verifyState', 'UserController@verifyState');
    Route::post('friendVerify', 'UserController@friendVerify');
    Route::post('notifySetting', 'UserController@notifySetting');

    Route::post('updatePingTime', 'UserController@updatePingTime');

    Route::post('currentList', 'UserController@currentList');

    Route::post('setDefaultCurrent', 'UserController@setDefaultCurrent');
    Route::post('getbusinessRecord', 'ChatController@getBusinessChat');
    Route::post('getbusinesschatlist', 'ChatController@getChatList');

});

