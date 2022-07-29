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

//Route::group(['middleware'=>'cors'], function (){

Route::post('information/uploadImg','InformationController@uploadImg');
Route::post('auth/register', 'UserController@register');
Route::post('information/getCategory','InformationController@getCategory');
Route::post('auth/getAllLanguage', 'UserController@getAllLanguage');
Route::post('captcha', 'UserController@captcha');
Route::post('checkCaptcha', 'UserController@checkCaptcha');

// AppSingleLogin
// MemberSingleLogin
Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'information'], function ($router) {
    Route::post('addInformation','InformationController@addInformation');
    Route::post('getInformationDetails','InformationController@getInformationDetails');
    Route::post('editInformation','InformationController@editInformation');
    Route::post('getMyInformationList','InformationController@getMyInformationList');
    Route::post('delInformation','InformationController@delInformation');
    Route::post('cancelCheckInformation','InformationController@cancelCheckInformation');
    Route::post('getInformationStatusNumber','InformationController@getInformationStatusNumber');
    Route::post('upAndDownInformation','InformationController@upAndDownInformation');
    Route::post('cancelCheckInformation','InformationController@cancelCheckInformation');
    Route::post('getPreviewInformation','InformationController@getPreviewInformation');
});


Route::group(['prefix' => 'auth'], function ($router) {
    Route::get('logingettoken', 'UserController@csrftoken');
    Route::post('login', 'UserController@login');
    Route::any('sendMail', 'UserController@sendMail');
    Route::post('forgetLoginPwd', 'UserController@forgetLoginPwd');
});

Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'auth'], function ($router) {
    Route::post('logout', 'UserController@logout');
    Route::post('getUserInfo', 'UserController@getUserInfo');
    Route::post('editUserInfo', 'UserController@editUserInfo');
    Route::post('resetPin', 'UserController@resetPin');
    Route::post('setLang', 'UserController@setLang');
    Route::post('resetEmail', 'UserController@resetEmail');
    Route::post('getUserPrivacyInfo', 'UserController@getUserPrivacyInfo');
    Route::post('editUserPrivacyInfo', 'UserController@editUserPrivacyInfo');
});

Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'wallet'], function ($router) {
    Route::post('getRechargeAddress', 'WalletController@getRechargeAddress');
    Route::post('digitalCurrencyWithdraw', 'WalletController@digitalCurrencyWithdraw');
    Route::post('getOrderStatus', 'WalletController@getOrderStatus');
    Route::post('getOrderFilter', 'WalletController@getOrderFilter');
    Route::post('getWithdrawList', 'WalletController@getWithdrawList');
    Route::match(['get', 'post'], 'getMyOrderList', 'WalletController@getMyOrderList');
});

Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'analysis'], function ($router) {
    Route::post('getTotalNumber', 'AnalysisController@getTotalNumber');
    Route::post('getWave', 'AnalysisController@getWave');
    Route::post('getDataStatistics', 'AnalysisController@getDataStatistics');
});


Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'group'], function ($router) {
    Route::post('create', 'GroupController@create');
    Route::post('getTotalNumber', 'GroupController@getTotalNumber');
    Route::post('getMyGroupList', 'GroupController@getMyGroupList');
    Route::post('getMyJoinGroupList', 'GroupController@getMyJoinGroupList');
    Route::post('getGroupMember', 'GroupController@getGroupMember');
    Route::post('getGroupDetails', 'GroupController@getGroupDetails');
    Route::post('editGroupInfo', 'GroupController@editGroupInfo');
    Route::post('getFriends', 'GroupController@getFriends');
});

Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'message'], function ($router) {
    Route::post('getNoReadMessageNumber', 'MessageController@getNoReadMessageNumber');
    Route::post('getMessageList', 'MessageController@getMessageList');
    Route::post('getMessageDetails', 'MessageController@getMessageDetails');
});

Route::group(['middleware' => ['MemberSingleLogin'], 'prefix' => 'friends'], function ($router) {
    Route::post('getTotalNumber', 'FriendsController@getTotalNumber');
    Route::post('getFriendsList', 'FriendsController@getFriendsList');
    Route::post('delFriends', 'FriendsController@delFriends');
    Route::post('getApplyList', 'FriendsController@getApplyList');
    Route::post('agree', 'FriendsController@agree');
    Route::post('reject', 'FriendsController@reject');
    Route::post('search', 'FriendsController@search');
    Route::post('apply', 'FriendsController@apply');
});

Route::group(['middleware' => ['MemberSingleLogin']], function ($router) {
    Route::group(['prefix' => 'community'], function ($router) {
        Route::post('getCommunityList', 'CommunityController@getCommunityList');
        Route::post('save', 'CommunityController@save');
        Route::post('myCreateList', 'CommunityController@myCreateList');
        Route::post('myFollowList', 'CommunityController@myFollowList');
        Route::post('getCountData', 'CommunityController@getCountData');
        Route::post('dismiss', 'CommunityController@dismiss');
        Route::post('getDetails', 'CommunityController@getDetails');
        Route::post('getFollowUserList', 'CommunityController@getFollowUserList');
    });

    Route::group(['prefix' => 'communityInformation'], function ($router) {
        Route::post('publish', 'CommunityInformationController@publish');
        Route::post('getList', 'CommunityInformationController@getList');
        Route::post('del', 'CommunityInformationController@del');
        Route::post('getDetails', 'CommunityInformationController@getDetails');
        Route::post('upOrDown', 'CommunityInformationController@upOrDown');
        Route::post('cancelCheck', 'CommunityInformationController@cancelCheck');
    });
});



//});


