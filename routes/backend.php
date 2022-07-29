<?php

use Illuminate\Http\Request;
//use Illuminate\Routing\Router;
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
Route::group(['prefix' => 'backend', 'namespace'=>'BackEnd'], function () {
    Route::get('login', 'LoginController@showLogin')->name('login');
    Route::post('login', 'LoginController@login');
    Route::get('logout', 'LoginController@logout');
});

Route::group(['prefix' => 'backend', 'namespace'=>'BackEnd', 'middleware' => 'auth.backend'], function () {
    Route::get('home', 'IndexController@index')->name('index');
    Route::any('error_list', 'IndexController@errorList');
    Route::get('detail/{id}', 'IndexController@detail');
    Route::get('order_list/{id}/{current_id}', 'IndexController@orderList');
    Route::post('order_list', 'IndexController@orderList');
    Route::get('order_detail/{id}', 'IndexController@orderDetail');
    Route::any('business_list', 'IndexController@businessList');
    Route::get('business_detail/{id}', 'IndexController@businessDetail');
    Route::get('business_order_list/{id}/{current_id}', 'IndexController@businessOrderList');
});
