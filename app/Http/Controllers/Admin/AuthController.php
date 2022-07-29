<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin\AdminAcount;
use App\Models\Admin\Permission;
use App\Models\Admin\RolePermission;
use App\Models\Admin\RoleUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use Illuminate\Support\Facades\Redis;
use App\Models\Admin\AdminLogLogin;
use App\Models\Business\Language;
use App\Models\Business\Regions;
use App\Models\Admin\SystemOperationLog;
/**
 * @group 52超级管理员认证
 * - author llc
 */

class AuthController extends Controller
{

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */

    public function __construct()
    {

    }
    /**
     * 52.1获取csrftoken
     * **参数：**
    |参数名|必选|类型|说明|
    |:----          |:---    |:-----     |-----       |

     **返回示例**
    ```
    {
    "code": 200,
    "msg": "Get Data Successful",
    "data": "CnA3aAdScrYBfteJ52ekKERAvzSfQnRGDZHdPnKj"
    }
    ```

     **返回参数说明**
    |参数名|类型|说明|
    |:-----  |:-----|-----
     **/
    public function csrftoken(){
        return response_json(200,trans('app.getDataSuccess'), array(
            '_token' => csrf_token()
        ));
    }
    /**
     * 52.2超级管理员登录
     * **参数：**
    |参数名|必选|类型|说明|
    |:----          |:---    |:-----     |-----       |
    |username |否  |string |用户名   |
    |password |是  |string |密码   |
    |_token           |是      |string        |   csrftken      |
    |lang |否  |ENUM |语言选择   | 用户使用语言（en英语，cn简体中文，tw繁体中文，kr韩文，th泰文,ru,fr,de,vn,tr,nl,pt,it,pl,）

     **返回示例**
    ```
    {
    "code": 200,
    "msg": "Login success!",
    "data": {
    "access_token": "CnA3aAdScrYBfteJ52ekKERAvzSfQnRGDZHdPnKj",
    "token_type": "bearer",
    "expires_in": 7200
    }
    }
    ```

     **返回参数说明**
    |参数名|类型|说明|
    |:-----  |:-----|-----
     **/

    public function login(Request $request)
    {
        $inputP = $input=$request->all();
        $validator = Validator::make($request->all(),[
            'username' => 'required|string|min:2|max:30',
            'password' => 'required|string|min:6',
            'lang' => 'string|nullable',
        ]);
        //登录设置语言
        if (!empty($request->lang)) {
            \App::setLocale($request->lang);
        }
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        $user = AdminAcount::where('username', $request->input('username'))
            ->where('status', User::STATUS_ALLOW)
            ->first();

        $csrf_token = csrf_token();
        if (!$user) {
            if(!empty(Redis::get($csrf_token))){
                Redis::incr($csrf_token);
            }else{
                Redis::setex($csrf_token, 6000, 1);
            }
            return response_json(403,trans('web.loginFail'), array(
                'login_error' => Redis::get($csrf_token) ? : 0
                //'login_error' => 0
            ));
        }
        // 用户单点登录
        $time = microtime(true);
        if (Auth::guard('admin')->attempt(['username'=>$inputP['username'],'password'=>$inputP['password']])) {
            $user->last_time = date('Y-m-d H:i:s', $time);

            if (array_key_exists("HTTP_CF_CONNECTING_IP",$_SERVER))
            {
                $user->last_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];;
            }else{
                $user->last_ip = $request->getClientIp();
            }
            //登录设置语言
            if (!empty($request->input("lang"))) {
                $user->language = $request->input("lang");
            }
            $user->save();

            // md5 加密
            $singleToken = md5("5$#dfsauj7bnccDDDcHcmn%1".$user->id . $time);

            //超级管理员登录记录
            $adminlog = new AdminLogLogin;
            $adminlog->ip = $user->last_ip;
            $adminlog->userId = $user->id;
            $adminlog->username = $user->username;
            $adminlog->redistime = $time;
            $adminlog->path = $request->path();
            $adminlog->desc = $singleToken;
            $adminlog->save();

            //登录记录操作日志
            if ($user->language == 'cn'){
                $msg = '管理员'.$user->username.'登录';
            }elseif($user->language == 'en'){
                $msg = 'Administrators '.$user->username.' login';
            }elseif($user->language == 'hk'){
                $msg = '管理员'.$user->username.'登录';
            }
            SystemOperationLog::add_log($user->id,$request,$msg);
            // 当前 time 存入 Redis
            Redis::set('ADMIN_STRING_SINGLETOKEN_' . $user->id, $time);

            $rstData = ['code'=>200,'msg'=>trans('app.loginSuccess'),'data'=>[
                'access_token' => csrf_token(),
                'a'=>$singleToken,
                'token_type' => 'bearer',
                'expires_in' => env('SESSION_LIFETIME')*60,
                'memu_list' => $this->getUserMenu(false)
            ]];
            return response($rstData)->cookie('SECRETAPPLICATIONTOKEN', $singleToken, env('SESSION_LIFETIME'));

        } else {
            User::where('username', $request->input('username'))->increment("login_error");
            if(!empty(Redis::get($csrf_token))){
                Redis::incr($csrf_token);
            }else{
                Redis::setex($csrf_token, 6000, 1);
            }
            return response_json(402, trans('web.loginFail'), array(
                'login_error' => $user->login_error + 1
                //'login_error' => 0
            ));
        }

    }

    /**
     * 52.3超级管理员用户信息
     * **参数：**
    |参数名|必选|类型|说明|
    |:----          |:---    |:-----     |-----       |
    |cookie          |是      |string     |     |
    |_token           |是      |string        |         |

     **返回示例**
    ```
    {
    "code": 200,
    "msg": "成功",
    "data": {
    "id": 150,
    "username": "1591584450",
    "actual_name": "",
    "phone_number": "1591584450",
    "phone_status": 2,
    "email_status": 1,
    "email": "li@163.com",
    "address": "",
    "portRaitUri": "http://tradepost.com",
    "sex": 3,
    "birthday": null
    }
    }
    ```+

     **返回参数说明**
    |参数名|类型|说明|
    |:-----  |:-----|-----
     **/
    public function me()
    {

        $authid = Auth::guard('admin')->id();
        if (!empty($authid)) {
            $authuser = User::where('id',$authid)->first(['id','username','portRaitUri']);
            $authuser->portRaitUri = url($authuser->portRaitUri);

            if(!empty($authuser)){
                return response_json(200,'成功',$authuser);
            }else{
                return response_json(402,'失败');
            }
        }else{
            return response_json(402,'失败');
        }

        //return response()->json(auth(self::guard)->user());
    }


    /**
     * 52.4超级管理员推出登录
     * **参数：**
    |参数名|必选|类型|说明|
    |:----          |:---    |:-----     |-----       |
    |cookie          |是      |string     |     |
    |_token           |是      |string        |         |

     **返回示例**
    ```
    {
    "code": 200,
    "msg": "推出登录",
    }
    ```

     **返回参数说明**
    |参数名|类型|说明|
    |:-----  |:-----|-----
     **/
    public function logout(){
        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
        }
        Auth::guard('admin')->logout();
        return response_json(200,trans('app.logout'));
    }

    public function testreg(){
        $user = new User;
        $user->phone_status = 2;
        //默认用户名 为手机号码
        $user->portRaitUri = "img/defaultlogo.png";
        $user->username = "linlicai";
        $user->email = "linlicai@163.com";
        $user->phone_number = "15915844503";
        $user->area = "86";  //国家区号
        $user->password = bcrypt("linlicai");
        $user->country_id = "86";
        $rst = $user->save();
        $lastid = $user->id;
        dd($lastid);
    }

    public function allLanguage(){
        $allLang = Language::where('adm_status',1)->get();
        return response_json(200,$allLang);
    }

    public function allregion(){
        $allLang = Regions::where('is_reggift',1)->where('enable',1)->select('tw_country','region','current_id')->get();
        return response_json(200,$allLang);
    }

    /**
     * 获取后台管理员菜单
     */
    public function getUserMenu($response = true){
        $user_info = Auth('admin')->user();
        //获取所有菜单
        $all_menu = Permission::getList(
            [['is_menu', 1], ['is_show', 1], ['status', Permission::ENABLED]],
            ['id', trans('web.admin_catalory_name') . ' as name', 'order_id', 'pid', 'menu_url']
        );
        $white_menu_ids = [10];//白名单菜单列表，所有用户都有操作权限的菜单
        //过滤没有操作权限的菜单
        if (!$user_info->isRole('administrator')) {
            $all_menu = $all_menu->filter(function ($menu) use($user_info, $white_menu_ids) {
                foreach ($user_info->roles as $role) {
                    foreach ($role->permissions as $permission) {
                        if (in_array($menu['id'], $white_menu_ids) ||
                            $permission->id == $menu['id'] ||
                            $permission->pid == $menu['id']){
                            return true;
                        }
                    }
                }
                return false;
            });
        }
        $data = classify($all_menu->toArray(), 0, 0);
        if (!$response) return $data;
        return response_json(200, trans('web.getDataSuccess'), $data);
    }

}
