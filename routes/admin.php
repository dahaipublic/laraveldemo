<?php

Route::get('auth/logingettoken', 'AuthController@csrftoken');
Route::post('auth/login', 'AuthController@login');

//如果有不需要鉴权的接口，可以放在此路由群组下
Route::group(['middleware' => 'admin'], function () {


    Route::post('adinfo', 'AuthController@me');
    Route::post('adallpermission', 'UserController@getAllPermition');
    Route::post('adall-business-permission', 'UserController@getBusinessAllMenu');
    Route::post('admenu', 'UserController@getAllMenu');
    Route::post('adlogout', 'AuthController@logout');
    Route::post('getUserMenu', 'AuthController@getUserMenu');
    Route::post('admin/information/getCategoryList', 'InformationController@getCategoryList');
    Route::post('feedback/grtImAccount', 'FeedbackController@grtImAccount');
    Route::post('users/avatar/upload', 'UserController@uploadImg');//头像修改

    Route::Group(['prefix'=>'analysis'],function(){
        Route::post('getAdminBalance', 'AnalysisController@getAdminBalance');
        Route::get('getUserBalance', 'AnalysisController@getUserBalance');
        Route::post('getCondition', 'AnalysisController@getCondition');
        Route::post('config', 'AnalysisController@update_config');
        Route::get('config', 'AnalysisController@get_config');
    });

});
//Route::any('testasdf', 'TestController@testposaddress')->name('testasdf');

Route::post('reggiftregion', 'AuthController@allregion')->name('allregion');
Route::any('language', 'AuthController@allLanguage')->name('language');
Route::post('admin/logout', 'AuthController@logout');//退出登陆

Route::group(['middleware' => ['admin', 'AdminCheckRole']], function () {
//文章管理
    Route::post('artical/getlist', 'ArticalController@getList');//获取文章列表/搜索
    Route::post('artical/getcontent', 'ArticalController@getContent');//获取指定文章id的内容
        Route::post('artical/check', 'ArticalController@check');//审核文章
    Route::post('artical/publish', 'ArticalController@publish');//发布文章
    Route::post('artical/upOrDown', 'ArticalController@upOrDown');//上下线资讯
    Route::post('artical/del', 'ArticalController@del');//删除资讯
//用户管理
    Route::post('users/modifyPassword', 'UserController@modifyPassword');//获取用户、公众号 官方账号
    Route::post('users/getlist', 'UserController@getList');//获取用户、公众号 官方账号
    Route::post('users/detail', 'UserController@detail');//获取用户详情
    Route::post('users/addaccount', 'UserController@addAccount');//添加账户
    Route::post('users/modify', 'UserController@modifyUserinfo');//用户信息修改
    Route::post('users/checkUser', 'UserController@checkUser'); //
    Route::post('setBlackUser', 'AdminController@setBlackUser');//封号
    Route::post('setCanGroupChat', 'AdminController@setCanGroupChat');//用户群禁言/解除群禁言
//交易记录
    Route::match(['get', 'post'], 'transition', 'OrderController@transtionList');//获取交易记录/查询
    Route::post('getAllCurrency', 'OrderController@getAllCurrency');//获取所有币种
//权限管理
    Route::post('admin/list', 'AdminController@getList');//管理员列表
    Route::post('admin/delete', 'AdminController@delete');//删除管理员
    Route::post('admin/create', 'AdminController@create');//管理员创建
    Route::post('admin/getAdminInfo', 'AdminController@getAdminInfo');//获取管理员信息
    Route::post('admin/editAdmin', 'AdminController@editAdmin');//修改管理员信息
    Route::post('admin/role/create', 'AdminController@roleCreate');//创建角色
    Route::post('admin/getrolelist', 'AdminController@getRoleList');//获取角色列表
    Route::post('admin/getPermissions', 'AdminController@getPermissions');//获取所有的权限
    Route::post('admin/getRoleInfo', 'AdminController@getRoleInfo');//获取角色详细

    Route::post('admin/changeRole', 'AdminController@changeRole');//更新管理员的角色
    Route::post('admin/editRolePermission', 'AdminController@editRolePermission');//更新角色权限
    Route::post('admin/delRole', 'AdminController@delRole');//删除角色
//社区、群组
    Route::post('admin/community/list', 'CommunityController@getList');//获取社区列表
    Route::post('admin/community/detail', 'CommunityController@communityDetail');//获取社区详情 detail
    Route::post('admin/group', 'CommunityController@group');//获取群组
    Route::post('admin/group/detail', 'CommunityController@groupDetail');//获取群组详情
    Route::post('admin/group/member', 'CommunityController@groupMember');//获取群组成员
    Route::post('admin/community/getTags', 'CommunityController@getTags');
    Route::post('admin/community/addTag', 'CommunityController@addTag');
    Route::post('admin/community/delTag', 'CommunityController@delTag');
    Route::post('admin/information/addCategory', 'InformationController@addCategory');
    Route::post('admin/information/delCategory', 'InformationController@delCategory');
    Route::post('admin/information/editCategorySort', 'InformationController@editCategorySort');
    Route::post('admin/community/check', 'CommunityController@check');
    Route::post('admin/community/getFollowUserList', 'CommunityController@getFollowUserList');
//社区资讯文章
    Route::group(['prefix' => 'admin/communityInformation'], function ($router) {
        Route::post('edit', 'CommunityInformationController@edit');
        Route::post('getList', 'CommunityInformationController@getList');
        Route::post('del', 'CommunityInformationController@del');
        Route::post('getDetails', 'CommunityInformationController@getDetails');
        Route::post('check', 'CommunityInformationController@check');
        Route::post('upOrDown', 'CommunityInformationController@upOrDown');
    });
//系统配置
    Route::post('config/list', 'SystemConfigController@list');//获取系统配置
    Route::post('config/edit', 'SystemConfigController@edit');//修改系统配置
    Route::post('config/create', 'SystemConfigController@create');//创建系统配置





    Route::post('adrolelist', 'RoleController@getRoles');
    Route::post('adroleadd', 'RoleController@roleAdd');
    Route::post('adroledetail', 'RoleController@getRoledetail');
    Route::post('adroleupdate', 'RoleController@updateRole');
    Route::post('adroleremove', 'RoleController@delRole');
    Route::post('aduserlist', 'UserController@userList');
    Route::post('aduseradd', 'UserController@addUser');
    Route::post('aduserdetail', 'UserController@getUserDetail');
    Route::post('aduserupdate', 'UserController@userupdate');
    Route::post('aduserdel', 'UserController@delUser');
    Route::post('setlang', 'UserController@setlang');
    Route::any('adminloginbusiness', 'UserController@loginBusiness');
    //临时pos管理
    Route::any('posadminloginbusiness', 'UserController@postmanagerloginBusiness');
    Route::any('generatebranch', 'UserController@generatebranch');
    //活动送金
    Route::post('activelist', 'CandyController@index');
    Route::post('addactive', 'CandyController@create');
    Route::post('activedetail', 'CandyController@show');
    Route::post('updateactive', 'CandyController@store');
    Route::post('delactive', 'CandyController@destroy');

});

// Route::post('send_email', 'UserController@sendMail');

// Route::post('send_msg', 'UserController@sendMsg');

//    Route::post('logingettoken', 'AuthController@csrftoken');



Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'member'], function () {
    Route::post('setLevel', 'MemberController@setLevel');

    Route::post('setPrize', 'MemberController@setPrize');

    Route::get('prizeList', 'MemberController@prizeList');

    Route::get('del', 'MemberController@del');

    Route::get('memberList', 'MemberController@memberList');

    Route::post('setIntegral', 'MemberController@setIntegral');

    Route::get('getIntegral', 'MemberController@getIntegral');

});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'order'], function () {
    Route::get('order', 'OrderController@order');

    Route::get('orderExport', 'OrderController@orderExport');//下载路由

    Route::get('depositHistory', 'OrderController@depositHistory');

    Route::get('orderType', 'OrderController@orderType');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'log'], function () {
    Route::match(['get', 'post'], 'log', 'LogController@log');
    Route::post('adminList', 'LogController@adminList');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'manage'], function () {
    Route::get('businessList', 'ManageController@businessList');

    Route::get('downloadBusiness', 'ManageController@downloadBusiness');//下载路由

    Route::get('recommendList', 'ManageController@recommendList');

    Route::get('downloadRecommend', 'ManageController@downloadRecommend');//下载路由

    Route::get('info', 'ManageController@info');

    Route::post('editInfo', 'ManageController@editInfo');

    Route::post('editDraw', 'ManageController@editDraw');

    Route::post('editBank', 'ManageController@editBank');

    Route::post('setPosCharge', 'ManageController@setPosCharge');

    Route::post('setCharge', 'ManageController@setCharge');

    Route::get('getPos', 'ManageController@getPos');

    Route::get('getPosDetails', 'ManageController@getPosDetails');

    Route::post('editPos', 'ManageController@editPos');

    Route::get('unbind', 'ManageController@unbind');

    Route::get('make', 'ManageController@make');

    //Route::post('changeAvator','ManageController@changeAvator');

    Route::get('isOpen', 'ManageController@isOpen');

    Route::get('canOpen', 'ManageController@canOpen');

    Route::post('del', 'ManageController@del');

    Route::post('add', 'ManageController@add');

    Route::get('getCountry', 'ManageController@getCountry');

    Route::get('recommendTree', 'ManageController@recommendTree');

    Route::get('login', 'ManageController@login');

    Route::get('posList', 'ManageController@PosList');

    Route::get('exportPos', 'ManageController@exportPos');//下载路由

    Route::get('getPosId', 'ManageController@getPosId');

    Route::get('findPosId', 'ManageController@findPosId');

    Route::get('bindPosId', 'ManageController@bindPosId');

    Route::get('changePosId', 'ManageController@changePosId');

    Route::get('getAllCountry', 'ManageController@getAllCountry');

    Route::get('getLegalCurrency', 'ManageController@getLegalCurrency');

    Route::get('getVirtualCurrency', 'ManageController@getVirtualCurrency');

    Route::get('getArea', 'ManageController@getArea');

    Route::get('getCharge', 'ManageController@getCharge');

    Route::post('delPosCharge', 'ManageController@delPosCharge');

    Route::get('getAllCharge', 'ManageController@getAllCharge');

    Route::post('delCharge', 'ManageController@delCharge');

    Route::get('recomList', 'ManageController@recomList');

    Route::get('downloadQrCode', 'ManageController@downloadQrCode');

    Route::get('companyType', 'ManageController@companyType');

    Route::post('editLoginPwd', 'ManageController@editLoginPwd');

});

Route::get('manage/getAllCountry', 'ManageController@getAllCountry');
Route::get('manage/getArea', 'ManageController@getArea');
Route::get('manage/getVirtualCurrency', 'ManageController@getVirtualCurrency');
Route::get('manage/getChild', 'ManageController@getChild');


// Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'analysis'], function () {
Route::group(['prefix' => 'analysis'], function () {
    //Route::group(['prefix' => 'analysis'], function () {
    Route::get('getAnalysisCurrency', 'AnalysisController@getCurrency');
//    Route::get('getAdminIncomeReport', 'AnalysisController@getAdminIncomeReport');
//    Route::get('serviceReport', 'AnalysisController@serviceReport');
//    Route::get('count', 'AnalysisController@count');
//    Route::get('downloadAdminDay', 'AnalysisController@download_admin_day');//下载路由
//    Route::get('downloadAdminMonth', 'AnalysisController@download_admin_month');//下载路由
//    Route::get('downloadAdminYear', 'AnalysisController@download_admin_year');//下载路由
//    Route::get('getGraph', 'AnalysisController@getGraph');
//    Route::get('businessReport', 'AnalysisController@businessReport');
//    Route::post('businessCount', 'AnalysisController@businessCount');
//    Route::post('businessFcCount', 'AnalysisController@businessFcCount');

});
//Route::get('analysis/getCurrency', 'AnalysisController@getCurrency');

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'feedback'], function () {
    Route::post('getQuestion', 'FeedbackController@getQuestion');
    Route::post('saveChat', 'FeedbackController@saveChat');
    Route::post('getChat', 'FeedbackController@getChat');
    Route::post('processed', 'FeedbackController@processed');
    Route::post('complaintList', 'FeedbackController@complaintList');
    Route::post('complainAction', 'FeedbackController@complainAction');
    Route::post('getAvatar', 'FeedbackController@getAvatar');
    Route::post('saveChatCount', 'FeedbackController@saveChatCount');
    Route::post('getChatCount', 'FeedbackController@getChatCount');

    Route::post('getCooperationList', 'FeedbackController@getCooperationList');
});


Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'product'], function () {
    Route::post('productAdd', 'ProductController@productAdd');
    Route::post('productList', 'ProductController@productList');
    Route::post('productDel', 'ProductController@productDel');
    Route::post('productEdit', 'ProductController@productEdit');
    Route::any('getBusinessOrder', 'ProductController@getBusinessOrder');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'wallet'], function () {
    Route::post('withdrawCash', 'WalletController@withdrawCash');
    Route::post('fcWithdrawCash', 'WalletController@fcWithdrawCash');
    Route::match(['get', 'post'], 'withdrawCashList', 'WalletController@withdrawCashList');
    Route::match(['get', 'post'], 'fcWithdrawCashList', 'WalletController@fcWithdrawCashList');
    Route::post('changeRate', 'WalletController@changeRate');
    Route::post('legalMoneyList', 'WalletController@legalMoneyList');
    Route::match(['get', 'post'], 'getUserList', 'WalletController@getUserList');
    Route::post('getBalance', 'WalletController@getBalance');
    Route::post('getFcBalance', 'WalletController@getFcBalance');
    Route::post('saveRechargeIntegral', 'WalletController@saveRechargeIntegral');
    Route::post('editRemark', 'WalletController@editRemark');
    Route::post('setBlackUser', 'WalletController@setBlackUser');
    Route::post('editUserLoginPwd', 'WalletController@editUserLoginPwd');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'mobile_recharge'], function () {
    Route::match(['get', 'post'], 'getList', 'MobileRechargeController@getList');
    Route::post('changeStatus', 'MobileRechargeController@changeStatus');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'message'], function () {
//    Route::group([ 'prefix' => 'message'], function () {
    Route::post('add', 'MessageController@add');
    Route::post('getMessageList', 'MessageController@getMessageList');
    Route::post('closeMaintenance', 'MessageController@closeMaintenance');
    Route::post('delMessage', 'MessageController@delMessage');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'currency'], function () {
//Route::group([ 'prefix' => 'currency'], function () {
    Route::post('getCurrency', 'CurrencyController@getCurrency');
    Route::post('addCurrency', 'CurrencyController@addCurrency');
    Route::post('updateCurrency', 'CurrencyController@updateCurrency');
    Route::post('delCurrency', 'CurrencyController@delCurrency');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'starbucks'], function () {
    Route::get('getTickets', 'StarbucksTicketController@get_data');

    Route::post('create', 'StarbucksTicketController@create');

    Route::post('delete', 'StarbucksTicketController@delete');

    Route::get('getCommodities', 'StarbucksTicketController@getCommodities');

    Route::get('getCommodity', 'StarbucksTicketController@getCommodity');

    Route::post('createCommodity', 'StarbucksTicketController@createCommodity');

    Route::post('starbucks/uploadImg', 'StarbucksTicketController@uploadImg');

    Route::post('starbucks/deleteImg', 'StarbucksTicketController@deleteImg');

    Route::post('updateCommodity', 'StarbucksTicketController@updateCommodity');

    Route::post('deleteCommodity', 'StarbucksTicketController@deleteCommodity');

    Route::get('commodityType', 'StarbucksTicketController@commodityType');

    Route::get('cooperation', 'StarbucksTicketController@cooperation');

    Route::post('setOpenTime', 'StarbucksTicketController@setOpenTime');

});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'information'], function () {
//Route::group(['prefix' => 'information'], function () {
    Route::post('addInformationFast', 'InformationController@addInformationFast');
    Route::post('getInformationFastList', 'InformationController@getInformationFastList');
    Route::post('getInformationFastDetails', 'InformationController@getInformationFastDetails');
    Route::post('delInformationFast', 'InformationController@delInformationFast');
    Route::post('editInformationFast', 'InformationController@editInformationFast');
    Route::post('getReportList', 'InformationController@getReportList');
});


Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'frozen'], function () {
    Route::post('frozenGroup', 'FrozenController@frozenGroup');
    Route::post('frozenCommunity', 'FrozenController@frozenCommunity');
    Route::post('frozenInformation', 'FrozenController@frozenInformation');
    Route::post('frozenInformationUsers', 'FrozenController@frozenInformationUsers');
    Route::post('frozenInformationComment', 'FrozenController@frozenInformationComment');
});

Route::group(['middleware' => ['admin', 'AdminCheckRole'], 'prefix' => 'helperCenter'], function () {
    Route::post('save', 'HelperCenterController@save');
    Route::post('getDetail', 'HelperCenterController@getDetail');
    Route::post('del', 'HelperCenterController@del');
    Route::post('getList', 'HelperCenterController@getList');
});
