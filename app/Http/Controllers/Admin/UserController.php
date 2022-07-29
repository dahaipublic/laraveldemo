<?php

namespace App\Http\Controllers\Admin;

use App\Models\Information\Information;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use App\Models\RegisterUsers;
use App\Models\Business\User as BusinessUser;
use App\Models\User AS appUser;
use App\Models\Business\PosWallet;
use App\Models\Currency;
use App\Models\Admin\RoleUser;
use App\Models\Admin\Permission;
use App\Models\Business\Permission as BusinessPermission;
use App\Models\Admin\Menu;
use App;
use Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Jobs\EasemobCreateUser;
use Illuminate\Support\Facades\Redis;
use App\Models\Business\Pos;
use App\Models\Bank;
use App\Models\UsersWallet;
use App\Models\Recommend;
use App\Models\Admin\SystemOperationLog;
use App\Jobs\GenerateWalletAddress;
use App\Jobs\DeleteRedisToken;

//use App\Jobs\GenerateWalletAddress;

/**
 * @group 2超级管理员用户操作
 * - author llc
 *
 */
class UserController extends Controller
{


    // 修改登陆密码
    public function modifyPassword(Request $request)
    {

        $user = Auth::guard('admin')->user();
        $account_id = Auth::guard('admin')->id();

        $validator = Validator::make($request->all(), [
            'uid' => 'required|int',
            'new_password' => 'required|string',
            'remark' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $uid = $request->input('uid');
        $new_password = bcrypt(think_md5($request->input('new_password')));
        $remark = $request->input('remark', '');

        $app_user = appUser::select("id", "password")->where('id', $uid)->first();
        if(empty($app_user)){
            return response_json(402, trans('web.userNotFound'));
        }

        DB::beginTransaction();

        $result = appUser::where('id', $uid)->update([
            'password' => $new_password
        ]);
        if ($result) {
            $now_time = date('Y-m-d H:i:s');
            $data = array(
                'account_id' => $account_id,
                'uid' => $uid,
                'old_password' => $app_user->password,
                'new_password' => $new_password,
                'created_at' => $now_time,
                'updated_at' => $now_time,
                'remark' => $remark,
            );
            $insert = App\Models\ModifyUsersPassword::insert($data);
            if($insert){
                // 退出登陆
                DeleteRedisToken::dispatch($uid)->onQueue('delete_redis_token'.$uid);
                // 修改日志
                $msg = "管理员{$user->username}: 修改用户登陆密码, user_id: $uid";
                SystemOperationLog::add_log($account_id, $request, $msg);
                DB::commit();
                return response_json(200, trans('web.passwordChangeSuccess'));
            }else{
                DB::rollBack();
                return response_json(403, trans('web.passwordChangeFail'));
            }
        } else {
            DB::rollBack();
            return response_json(403, trans('web.passwordChangeFail'));
        }


    }

//    修改用户信息
    public function modifyUserinfo(Request $request)
    {
        $customer_type = $request->input('customer_type');
        $nickname = $request->input('nickname');
        $email = $request->input('email');
        $sex = $request->input('sex');
        $img_thumb = $request->input('img_thumb');
        $uid = $request->input('id');
        $admin = Auth::guard('admin')->user();
        $result = App\Models\User::where('id', $uid)->update([
            'customer_type' => $customer_type,
            'nickname' => $nickname,
            'email' => $email,
            'sex' => $sex,
            'img_thumb' => $img_thumb
        ]);
        if ($result) {
            $msg = "管理员{$admin->username}: 修改用户信息, user_id: $uid";
            SystemOperationLog::add_log($admin->id, $request, $msg);
            return response_json(200, trans('web.getDataSuccess'));
        } else {
            return response_json(403, trans('web.getDataFail'));
        }
    }

    public function uploadImg(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'image' => 'required|image',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $file = $request->file('image');
        $images = array(
            'pic' => upload_img_file($file, 0),
            'thumb' => upload_img_file($file, 1),
        );

        return response_json(200, trans('web.addSuccess'), $images);

    }

//    用户 公众号 官方账号列表/搜索
    public function getList(Request $request)
    {
        $pageSize = 10;
        $type = strtolower(trim($request->input('type')));
        $customer_type = $request->input('customer_type');//1为普通用户 3公众号 4为官方账号
        $field = ['id', 'username', 'email', 'created_at as updated_at', 'status', 'easemob_p', 'easemob_u',
                  'freeze_reason', 'group_mute_reason', 'can_group_chat', 'adm_check'];
        if ($type == 'search') {
            $keywords = $request->input('keywords');

            $user = App\Models\User::select($field)->where([['customer_type', '=', $customer_type]])->where(function ($query) use ($keywords) {
                $query->where('id', $keywords)->orWhere('username', 'like', "%$keywords%")->orWhere('email', 'like', "%$keywords%");
            });
            if($request->input('id')){
                $user->where('id', $request->input('id'));
            }
            return response_json(200, trans('web.getDataSuccess'), $user->orderBy('id', 'desc')->paginate($pageSize));

        }
        if (!in_array($customer_type, [1, 3, 4]))
            return response_json(403, trans('web.getDataFail'));
        $user = App\Models\User::select($field)->where('customer_type', $customer_type);
        if($request->input('id')){
            $user->where('id', $request->input('id'));
        }
        $result = $user->orderBy('id', 'desc')->paginate($pageSize);

        return response_json(200, trans('web.getDataSuccess'), $result);

    }

    //    添加账户
    public function addAccount(Request $request)
    {

        $messages = [
            'email.unique' => trans('web.reg_email_allready_registed'),
        ];
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:50|required_without:phone|unique:users,email',
            'username' => 'required|string',
            'password' => 'required|string',
            'customer_type' => 'required|int|min:1',
        ], $messages);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $email = $request->input('email');
        $username = $request->input('username');
        $password = $request->input('password');
        $customer_type = $request->input('customer_type');
        $admin = Auth::guard('admin')->user();
        // 验证邮箱
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            return response_json(403, trans('web.emailOrPhoneFormatError'));
        }
//      密码非空验证
        if (empty($password))
            return response_json(403, trans('web.fail'));
//        判断账号是否存在
        $count = App\Models\User::where([
            ['email', '=', $email]])->count();
        if ($count > 0) {
            return response_json(403, trans('web.emailExist'));
        }

        //注册生成该APP用户的推荐码
        $newRecommendcode = randomkeys(4);

        while(appUser::select("id")->where('recommend_code', $newRecommendcode)->first()) {
            $newRecommendcode = randomkeys(4);
        }

        $u = new App\Models\User();
        $u->fill([
            'customer_type' => $customer_type,
            'username' => $username,
            'password' => bcrypt(think_md5($password)),
            'email' => $email,
            'email_status' => 2,
            'recommend_code' => $newRecommendcode,
            'recommender_id' => 0,
            'is_pc_create' => 2,
        ]);
        if ($u->save()){
            // 生成环信账号
            $user_id = $u->id;
            $easemob = RegisterUsers::select("user_id", "easemob_u", "easemob_p")->where('user_id', $user_id)->where('error', '')->first();
            if(!empty($easemob)){
                appUser::where('id', $user_id)->update([
                    'easemob_u' => $easemob->easemob_u,
                    'easemob_p' => $easemob->easemob_p,
                ]);
            }else{
                // 如果没有之前生成环信账号, 则现在生成
                $url = url()->current();
                if (IS_FORMAL_HOST){
                    $isEasemob = appUser::createEasemob($user_id);
                }else{
                    $isEasemob = appUser::createEasemob($user_id, 0, 2);
                }
                if($isEasemob['code'] != 200){
                    return response_json(402, $isEasemob['msg']);
                }else{
                    $register_users = array(
                        'user_id' => $user_id,
                        'easemob_u' => $isEasemob['data']['username'],
                        'easemob_p' => $isEasemob['data']['password'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    );
                    RegisterUsers::insert($register_users);
                }
            }
            // 生成钱包地址
            GenerateWalletAddress::dispatch($user_id)->onQueue('getnewaddress'.$user_id);
            $msg = "管理员{$admin->username}: 添加用户账户, user_id: $user_id";
            SystemOperationLog::add_log($admin->id, $request, $msg);
            return response_json(200, trans('web.getDataSuccess'));
        }else{
            return response_json(403, trans('web.getDataFail'));
        }

    }


    // 获取用户详情
    public function detail(Request $request)
    {

        $id = $request->input('uid');
        if (!isset($id)) return response_json(403, trans('web.getDataFail'));
        // $user = App\Models\User::with('userWallet.current')->where('id', $id)->first();
        $user = App\Models\User::with('userWallet.current')->where('id', $id)->first();
        if(empty($user)){
            return response_json(403, trans('web.userNotFound'));
        }

        unset($user->pin);
        unset($user->password);
        // 用户私密信息
        $privacy = array(
            'uid' => intval($user->id),
            'user_type' => $user->user_type,
            'portRaitUri' => $user->portRaitUri,
            'ID_card' => $user->ID_card,
            'company_name' => $user->company_name,
            'license_code' => $user->license_code,
            'front_photo' => $user->front_photo ? : '',
            'back_photo' => $user->back_photo ? : '',
            'license_thumb' => $user->license_thumb ?  : '',
        );
        $user->privacy = $privacy;

        return response_json(200, trans('web.getDataSuccess'), $user);

    }


    // 用户通过/取消验证
    public function checkUser(Request $request){

        $validator = Validator::make($request->all(),[
            'uid' => 'required|int',
            'adm_check' => 'required|int|min:0|max:2',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $uid = $request->input('uid');
        $adm_check = $request->input('adm_check', 0);

        $user = appUser::select("id")->where('id', $uid)->first();
        if(empty($user)){
            return response_json(403, trans('web.userNotFound'));
        }

        appUser::where('id', $uid)->update([
           'adm_check' => $adm_check
        ]);

        return response_json(200, trans('web.success'));

    }


    //
    public function __construct()
    {

    }

    /**
     * 2.1.该超级管理员创建的子用户列表
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     * |page |是  |int |页码,默认为1   |
     * |count |否  |int |页码,默认为15   |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "成功",
     * "data": {
     * "current_page": 1,
     * "data": [
     * {
     * "id": 1,
     * "username": "admin",
     * "portRaitUri": null
     * },
     * {
     * "id": 2,
     * "username": "linlicai",
     * "portRaitUri": null
     * },
     * {
     * "id": 3,
     * "username": "mengyawei",
     * "portRaitUri": null
     * }
     * ],
     * "first_page_url": "http://tradepost.com/adm/aduserlist?page=1",
     * "from": 1,
     * "last_page": 1,
     * "last_page_url": "http://tradepost.com/adm/aduserlist?page=1",
     * "next_page_url": null,
     * "path": "http://tradepost.com/adm/aduserlist",
     * "per_page": 15,
     * "prev_page_url": null,
     * "to": 3,
     * "total": 3
     * }
     * }
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * |page|int|当前页码|
     * |total|int|总记录数|
     * |path|string|url地址|
     * |prev_page|string|上一页url地址|
     * |current_page|string|当前页url地址|
     * |next_page|string|下一页url地址|
     * |last_page|int|总页码|
     * */
    public function userList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'name'     =>'nullable|string',
            'page' => 'nullable|integer',
            'count' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $page = intval($request->input('page', 1));
        $count = intval($request->input('count', 15));
        //只有超级管理员主账号可以创建子用户
        $id = Auth::guard('admin')->user()->id;

        $paginate = User::with('permissions')->select(['id', 'username', 'portRaitUri'])->paginate($count);
        //dd($paginate);


        if (!empty($paginate->items())) {
            foreach ($paginate as $k => $user) {
                $temp["id"] = $user->id;
                $temp["username"] = $user->username;
                $temp["portRaitUri"] = $user->portRaitUri;
                $rst[] = $temp;
            }

            foreach ($paginate as $k => $subuser) {
                $ptemp[] = '';
                if ($subuser->isRole('administrator')) {
                    $rst[$k]['permission'] = "All Permission";
                    $rst[$k]['roleName'][] = $subuser->roles[0]->name;
                    $rst[$k]['roleidId'][] = $subuser->roles[0]->id;

                } else {
                    if (!empty($subuser->roles)) {
                        foreach ($subuser->roles as $key => $role) {

                            //为了给前端好做，去掉父菜单
                            foreach ($role->permissions as $kk => &$value) {
                                if ($value->pid == 0 && $value->pcnum !== 0) {
                                    $temp = 0;
                                    foreach ($role->permissions as $kkk => $v) {
                                        if ($v->is_menu == 2 && $v->pid == $value->id) {
                                            $temp += 1;
                                        }
                                    }
                                    if ($temp < $value->pcnum) {
                                        $value->is_menu = 0;
                                    }
                                }
                            }

                            foreach ($role->permissions as $permission) {
                                if ($permission->is_menu == 2) {
                                    $ptemp[] = $permission->name_cn;
                                }

                            }
                            $ptemp = array_filter($ptemp);
                            $rst[$k]['roleName'][] = $role->name;
                            $rst[$k]['roleidId'][] = $role->id;
                            $rst[$k]['permission'] = implode(",", $ptemp);
                            unset($ptemp);
                        }

                    }
                }

            }
        } else {
            $rst = $paginate;
        }
        // $list = $paginate['data'];
        // $current_page = $paginate['current_page'];
        // $first_page = $paginate['first_page_url'];
        // $last_page = $paginate['last_page'];
        // $next_page = $paginate['next_page_url'];
        // $total = $paginate['total'];
        // $path = $paginate['path'];
        // $prev_page = $paginate['prev_page_url'];
        return response_json(200, trans('web.success'), $rst);

    }


    /**
     * 2.2.添加用户（只有主账号可以创建子用户）
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     * |roleid |是  |string |角色id    |
     * |name |是  |string |用户名称    |
     * |password |是  |string |子用户密码    |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "成功",
     * "data": {
     * "permission": "控制台,客户管理",
     * "name": "admin1",
     * "id": 9
     * }
     * }
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    public function addUser(Request $request)
    {
        //只有超级管理员主账号可以创建子用户
        $id = Auth::guard('admin')->user()->id;

        //  新建管理员信息
        $data = $request->only(['name', 'roleid', 'password']);
        $validator = Validator::make($request->all(), [
            'name' => 'required|unique:admin_accounts,username',
            'roleid' => 'required',
            'password' => 'required|string|between:6,12',
            'country_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = new user;
            $user->password = bcrypt($request->input('password'));
            $user->username = $request->input('name');
            $user->actual_name = $request->input('name');
            if (!empty($request->country_id)) {
                $user->country_id = $request->input('country_id');
            }
            $rst = $user->save();
            $userid = $user->id;
            $roleuser = new RoleUser;
            $roleuser->role_id = $request->input('roleid');
            $roleuser->user_id = $userid;
            $rst = $roleuser->save();
            if (!$user->roles->isempty()) {
                foreach ($user->roles as $role) {
                    foreach ($role->permissions as $permission) {
                        $permissions[] = $permission->name_cn;
                    }
                }
                if (!empty($permissions)) {
                    $userRst['permission'] = implode(',', $permissions);
                } else {
                    $userRst['permission'] = null;
                }

            } else {
                $userRst['permission'] = null;
            }
            $userRst['name'] = $user->username;
            $userRst['id'] = $user->id;
            //生成环信账号
            dispatch(new EasemobCreateUser($user->id, 1))->onQueue('getnewaddress');

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("Home Admin AddUser fail:" . $request->input('token') . "Home Admin AddUser: exeception" . $exception);
            return response_json(402, trans('web.adduserFail'));
        }

        if ($rst) {
            DB::commit();
            $admin = Auth('admin')->user();
            //记录日志，管理员添加子用户
            if ($admin->language == 'cn') {
                $msg = '管理员' . $admin->username . "添加子用户：" . $request->input('name');;
            } elseif ($admin->language == 'en') {
                $msg = 'Administrators ' . $admin->username . " add user：" . $request->input('name');;
            } elseif ($admin->language == 'hk') {
                $msg = '管理员' . $admin->username . "添加子用户：" . $request->input('name');;
            }
            SystemOperationLog::add_log($admin->id, $request, $msg);
            return response_json(200, trans('web.success'), $userRst);
        } else {
            return response_json(402, trans('web.getDataFail'));
        }

    }

    /**
     * 2.3.获取超级管理员子用户 详情
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     * |id |是  |string |用户id    |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "获取数据成功",
     * "data": {
     * "permission": "控制台,客户管理",
     * "name": "admin1",
     * "id": 9
     * }
     * }
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    public function getUserDetail(Request $request)
    {
        $inputP = $input = $request->all();
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',

        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = User::where('id', $request->input('id'))->first();
        if (empty($user)) {
            return response_json(402, trans('web.getDataFail'));
        }
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[] = $permission->name_cn;
            }
        }
        $userRst['permission'] = implode(',', $permissions);
        $userRst['name'] = $user->username;
        $userRst['id'] = $user->id;
        return response_json(200, trans('web.getDataSuccess'), $userRst);

    }

    /**
     * 2.4.修改超级管理员子用户 个人信息
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     * |id |是  |string |子用户用户id    |
     * |roleid |否  |string |角色id    |
     * |name |否  |string |用户名称    |
     * |password |否  |string |子用户密码    |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "获取数据成功",
     * "data": {
     * "permission": "控制台,交易信息,POS机管理,新建角色,角色列表,角色修改",
     * "name": "admin2",
     * "id": 5
     * }
     * }
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    public function userupdate(Request $request)
    {
        $inputP = $input = $request->all();
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'name' => 'nullable',
            'roleid' => 'nullable|string',
            'password' => 'nullable|string',
            'country_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //只有超级管理员主账号可以创建子用户
        $id = Auth::guard('admin')->user()->id;

        //  新建管理员信息
        $data = $request->only(['id', 'name', 'roleid', 'password']);

        try {
            DB::beginTransaction();
            $user = User::where("id", $request->input('id'))->first();
            if (empty($user)) {
                DB::rollBack();
                return response_json(402, trans('web.updateFail'));
            }

            if ($request->input('password')) {
                $user->password = bcrypt($request->input('password'));
            }
            if ($request->input('name')) {
                $user->username = $request->input('name');
                $user->actual_name = $request->input('name');
            }
            if (!empty($request->country_id)) {
                $user->country_id = $request->input('country_id');
            }
            $rst1 = $user->save();
            if (empty($request->input('password')) || empty($request->input('name'))) {
                $rst1 = 1;
            }

            $userid = $user->id;
            $user1 = User::where("id", $request->input('id'))->first();
            foreach ($user1->roles as $role) {
                if (!$role->permissions->isempty()) {
                    foreach ($role->permissions as $permission) {
                        $permissions[] = $permission->name_cn;
                    }
                    $userRst['permission'] = implode(',', $permissions);
                } else {
                    $permissions[] = null;
                }
            }

            $userRst['name'] = $user->username;
            $userRst['id'] = $user->id;

            if ($request->input('roleid')) {
                $rst2 = RoleUser::where("user_id", $userid)->update(['role_id' => $request->input('roleid')]);
            } else {
                $rst2 = 1;
            }

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("Home Admin UpdateUser fail:" . $request->input('token') . "Home Admin AddUser: exeception" . $exception);
            return response_json(402, trans('web.updateFail'));
        }

        if ($rst1 && $rst2) {
            DB::commit();
            $admin = Auth('admin')->user();
            //记录日志，管理员修改子用户信息
            if ($admin->language == 'cn') {
                $msg = '管理员' . $admin->username . "更新子用户：" . $user1->username;
            } elseif ($admin->language == 'en') {
                $msg = 'Administrators ' . $admin->username . " update user：" . $user1->username;
            } elseif ($admin->language == 'hk') {
                $msg = '管理员' . $admin->username . "更新子用户：" . $user1->username;
            }
            SystemOperationLog::add_log($admin->id, $request, $msg);
            return response_json(200, trans('web.updateSuccess'), $userRst);
        } else {
            return response_json(402, trans('web.updateFail'));
        }

    }

    /**
     * 2.5.删除超级管理员子用户
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     * |id |是  |string |子用户用户id    |
     **返回示例**
     *
     * ```
     *
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    public function delUser(Request $request)
    {
        $inputP = $input = $request->all();
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //只有超级管理员主账号可以创建子用户
        $id = Auth::guard('admin')->user()->id;

        //  新建管理员信息
        $data = $request->only(['id']);

        try {
            DB::beginTransaction();
            $rst1 = User::where("id", $request->input('id'))->delete();

            $rst2 = RoleUser::where("user_id", $request->input('id'))->delete();

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("Home Admin DeleteUser fail:" . $request->input('token') . "Home Admin AddUser: exeception" . $exception);
            return response_json(402, trans('web.deleteFail'));
        }

        if ($rst1 && $rst2) {
            DB::commit();
            $admin = Auth('admin')->user();
            //记录日志，管理员修改子用户信息
            if ($admin->language == 'cn') {
                $msg = '管理员' . $admin->username . "删除子用户：" . $request->input('id');
            } elseif ($admin->language == 'en') {
                $msg = 'Administrators ' . $admin->username . " del user：" . $request->input('id');
            } elseif ($admin->language == 'hk') {
                $msg = '管理员' . $admin->username . "删除子用户：" . $request->input('id');
            }
            SystemOperationLog::add_log($admin->id, $request, $msg);
            return response_json(200, trans('web.deleteSuccess'));
        } else {
            return response_json(402, trans('web.deleteFail'));
        }
    }
    /**
     * 2.6获取超级管理员子用户 所有权限
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     **返回示例**
     *
     * ```
     *
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    //->where('is_menu','2')
    public function getAllPermition()
    {

        $permission = Permission::where('is_show', 1)->where('status', '1')->where('is_p', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name', 'name_cn', 'name_hk', 'pid', 'http_path']);

        $permission = $this->buildTree($permission);
        return response_json(200, trans('app.getDataSuccess'), $permission);
    }

    /**
     * 2.7获取超级管理员子用户 所有菜单栏
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     **返回示例**
     *
     * ```
     *
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    public function getAllMenu()
    {
        $permissions = array();
        $myLang = Auth::guard('admin')->user()->language;
        $myLang = empty($myLang) ? 'cn' : $myLang;
        if (Auth::guard('admin')->check() && Auth::guard('admin')->user()->isRole('administrator')) {
            if ($myLang == 'cn') {
//               $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_menu', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name_cn', 'pid', 'menu_url', 'menu_icon','is_p']);
                $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_p', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name_cn', 'pid', 'menu_url', 'menu_icon', 'is_p', 'http_path']);
            } elseif ($myLang == 'en') {
//               $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_menu', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name as name_cn', 'pid', 'menu_url', 'menu_icon','is_p']);
                $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_p', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name as name_cn', 'pid', 'menu_url', 'menu_icon', 'is_p', 'http_path']);

            } elseif ($myLang == 'hk') {
//               $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_menu', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name_hk as name_cn', 'pid', 'menu_url', 'menu_icon','is_p']);
                $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_p', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name_hk as name_cn', 'pid', 'menu_url', 'menu_icon', 'is_p', 'http_path']);

            } else {
//               $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_menu', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name', 'name_cn', 'name_hk', 'pid', 'menu_url', 'menu_icon','is_p']);
                $permissions = Permission::where('is_show', 1)->where('status', '1')->where('is_p', '2')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name', 'name_cn', 'name_hk', 'pid', 'menu_url', 'menu_icon', 'is_p', 'http_path']);

            }

            $menus = $this->buildTree($permissions);
        } else if (Auth::guard('admin')->check()) {
            $user = Auth::guard('admin')->user();
            foreach ($user->roles as $role) {
                foreach ($role->permissions as $permission) {
                    if ($permission->is_menu == 2) {

                        if ($myLang == 'cn') {

                            $permissions[] = ['id' => $permission->id, 'name' => $permission->name, 'name_cn' => $permission->name_cn, 'menu_url' => $permission->menu_url, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];
                        } elseif ($myLang == 'en') {

                            $permissions[] = ['id' => $permission->id, 'name_cn' => $permission->name, 'menu_url' => $permission->menu_url, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];
                        } elseif ($myLang == 'hk') {
                            $permissions[] = ['id' => $permission->id, 'name_cn' => $permission->name_hk, 'menu_url' => $permission->menu_url, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];

                        } else {
                            $permissions[] = ['id' => $permission->id, 'name' => $permission->name, 'name_cn' => $permission->name_cn, 'menu_url' => $permission->menu_url, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];

                        }
                    } else {
                        if ($permission->is_p == 2 && $permission->is_icon == 2) {    //商家列表单独处理

                            if ($myLang == 'cn') {

                                $permissionThird[] = ['id' => $permission->id, 'name' => $permission->name, 'name_cn' => $permission->name_cn, 'menu_url' => $permission->http_path, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];
                            } elseif ($myLang == 'en') {

                                $permissionThird[] = ['id' => $permission->id, 'name_cn' => $permission->name, 'menu_url' => $permission->http_path, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];
                            } elseif ($myLang == 'hk') {
                                $permissionThird[] = ['id' => $permission->id, 'name_cn' => $permission->name_hk, 'menu_url' => $permission->http_path, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];

                            } else {
                                $permissionThird[] = ['id' => $permission->id, 'name' => $permission->name, 'name_cn' => $permission->name_cn, 'menu_url' => $permission->http_path, 'menu_icon' => $permission->menu_icon, 'pid' => $permission->pid, 'order_id' => $permission->order_id];

                            }
                        }
                    }
                }
            }

            // $permissions = collect($permissions);
            $menus = $this->buildArrayTree($permissions);
//            if (!empty($permissionThird)){
//                foreach($menus as $kk=>&$vv){
//                    if ($vv['id']==2){
//                        $vv['businesslist']=$permissionThird;
//                    }
//                }
//            }

//           dd($menus);
//     dd($menus[0]);
        } else {
            return response_json(402, trans('app.notLogin'));
        }


        return response_json(200, trans('app.getDataSuccess'), $menus);
    }


    public function buildArrayTree($actions, $pid = 0)
    {
        $returnValue = array();
        foreach ($actions as $action) {

            if ($action['pid'] == $pid) {

                $children = $this->buildArrayTree($actions, $action['id']);
                if ($children) {
                    $action['children'] = $children;
                }
                $returnValue[] = $action;

            }
        }

        return collect($returnValue);
//        return $returnValue;
    }

    public function buildTree($actions, $pid = 0)
    {
        $returnValue = array();
        foreach ($actions as $action) {

            if ($action->pid == $pid) {

                $children = $this->buildTree($actions, $action->id);
                if ($children) {
                    $action->children = $children;
                }
                $returnValue[] = $action;

            }
        }

        return collect($returnValue);
    }


    /**
     * 2.8邮箱发送验证码
     *
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |否  |string |登录token   |
     * |type |是  |string |邮箱验证码类型 |
     * |email |是  |string |邮箱 |  |1忘记pin码2忘记密码3注册4修改pos账号密码5修改超级管理员资料(修改手机号码) 6重置邮箱
     * |返回示例|
     * |:-----  |
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *```
     *{
     *    "code": 200,
     *    "msg": "The email was sent successfully.<br>please check it",
     *    "data": []
     *}
     *```
     */
    public function sendMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'email' => 'required|string|email',
        ]);
        if ($validator->fails()) {
            return response_json(401, $validator->errors()->first());
        }
        if ($request->input('type') == 1) {
            $types = MailCode::TYPE_FORGET_PIN;
        } elseif ($request->input('type') == 2) {
            $types = MailCode::TYPE_FORGET_PASSWORD;
        } elseif ($request->input('type') == 3) {
            $types = MailCode::TYPE_REG;
        } elseif ($request->input('type') == 4) {
            $types = MailCode::TYPE_CHANGE_POS;
        } elseif ($request->input('type') == 5) {
            $types = MailCode::TYPE_MODIFY_PROFILE;
        } elseif ($request->input('type') == 6) {
            $types = MailCode::TYPE_RESET_EMAIL;
        } else {
            return response_json(402, trans('app.emailUndefineType'));
        }
        $email = $request->input("email");

        //非注册操作都应该验证该郵箱是否注册过
        if ($request->input('type') != 3 && $request->input('type') != 6) {
            $user = User::where('email', $email)->first();
            if (empty($user)) {
                return response_json(402, trans('app.emailNotFound'));
            } else {
                $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                    ->where('user_id', $user->id)
                    ->where('type', $types)
                    ->where('email', $email)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->count();
                $userid = $user->id;
            }
        } else {
            $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                ->where('type', $types)
                ->where('email', $email)
                ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                ->count();
            $userid = '';
        }

        if ($record >= 3) return response_json(402, trans('app.emailSendTooQuickly'));
        $code = rand(100000, 999999);
        $mailCode = new MailCode();
        $mailCode->user_id = $userid;
        $mailCode->email = $email;
        $mailCode->expire_time = date('Y-m-d H:i:s', time() + 300);
        $mailCode->code = $code;
        $mailCode->type = $types;
        $mailCode->status = MailCode::STATUS_UNVERIFY;
        $mailCode->save();

        // $this->dispatch((new SendEmailCode($mailCode))->onQueue('emails'));
        SendEmailCode::dispatch($mailCode)->onQueue('mail');
        return response_json(200, trans('app.emailSendSuccess'));
    }

    /**
     * 2.9手机发送验证码
     *
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |否  |string |登录token   |
     * |area |是  |string |地区编号   |
     * |mobile |是  |string |手机号码   |
     * |type |是  |string |手机验证码类型   |1忘记pin码    2忘记密码    3注册认证手机号码   4重置手机号码  5修改超级管理员资料，
     * |返回示例|
     * |:-----  |
     *```
     *{
     *    "code": 200,
     *    "msg": "SMS sent successfully.<br>please check and enter",
     *    "data": []
     *}
     *```
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *
     */
    public function sendMsg(Request $request)
    {
        //注册新用户用到发送短信接口，注册用户未登录|登录的用户也会用到发送短信接口
        //所以该接口未用到登录中间件

        $validator = Validator::make($request->all(), [
            'area' => 'required|string|min:1|max:5',
            'mobile' => 'required|string|min:6',
            'type' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        if ($request->input('type') == 1) {
            $types = EmsCode::TYPE_FORGET_PIN;
        } elseif ($request->input('type') == 2) {
            $types = EmsCode::TYPE_FORGET_PASSWORD;
        } elseif ($request->input('type') == 3) {
            $types = EmsCode::TYPE_REGISTER;
        } elseif ($request->input('type') == 4) {
            $types = EmsCode::TYPE_RESETPHONE;
        } elseif ($request->input('type') == 5) {
            $types = EmsCode::TYPE_UPDATEPROFILE;
        } else {
            return response_json(402, trans('app.phoneUndefineType'));
        }
        $area = str_replace('+', '', $request->input('area'));
        if ($area !== '86') {
            $area = '00' . $area;
        }
        $mobile = $area . $request->input('mobile');

        //非注册操作都应该验证该手机号码是否注册过
        if ($request->input('type') != 3 && $request->input('type') != 4) {
            $user = User::where('phone_number', $request->input('mobile'))
                ->where('area', $area)
                ->first();

            if (empty($user)) {
                return response_json(402, trans('app.phoneNumberNotFound'));
            } else {
                $result = AdminEms::singleSend($mobile, 'cn', $types, $user->id);
            }
        } else {
            $result = AdminEms::singleSend($mobile, 'cn', $types);
        }

        return json_decode($result)->result == 0 ? response_json(200, trans('app.emsSendSuccess')) : response_json(402, trans('app.emsSendFail'));
    }


    /**
     * 2.11设置语言
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |lang |是  |ENUM |语言选择   | 用户使用语言（en英语，cn简体中文）
     * |返回示例|
     * |:-----  |
     * ```
     * {
     * "code": 200,
     * "msg": "The language was sent successfully.<br>please check it",
     * "data": []
     * }
     * ```
     *
     *
     */
    public function setlang(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lang' => 'string|required',
        ]);

        //只有超级管理员主账号可以创建子用户
        $user = Auth::guard('admin')->user();
        if (!empty($user->language)) {
            $user->language = $request->input("lang");
            $rst = $user->save();
            if ($rst) {
                App::setLocale($request->input("lang"));
                return response_json(200, trans('app.changeLanguageSucess'));
            } else {
                return response_json(402, trans('app.changeLanguageFail'));
            }


        } else {
            Log::debug("BusinessUserController token: ");
            return response_json(405, trans('app.tokenInvalide'));
        }

    }

    /**
     * 2.12超级管理员登录到商家
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |id |是  |id |语言选择   | 商家id
     * |用户使用语言（en英语，cn简体中文，hk繁体中文，jp日文，kr韩文，th泰文）
     * |返回示例|
     * |:-----  |
     * ```
     * {
     * "code": 200,
     * "msg": "The language was sent successfully.<br>please check it",
     * "data": []
     * }
     * ```
     *
     *
     */

    public function loginBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'area' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect('https://backoffice.rapidz.io/client/page/blankohgfhg&777&.html?language=en&token=' . csrf_token());
        }
        $area = str_replace('+', '', $request->input('area'));
        $user = BusinessUser::where("phone_number", $request->input("id"))->where('area', $area)->first();

        if (empty($user)) {
            return redirect('https://backoffice.rapidz.io/client/page/blankohgfhg&777&.html?language=en&token=' . csrf_token());
        }
        Auth::guard('business')->logout();
        //Auth::guard('business')->loginUsingId($request->input("id"));
        Auth::guard('business')->login($user);
        $lastLoginTimestamp = Redis::get('BUSINESS_STRING_SINGLETOKEN_' . $user->sellerId);
        $redisSingleToken = md5($user->sellerId . $lastLoginTimestamp . ".r2213doFGKpjkfg4");
        //return redirect('/home/info');
//        return redirect('https://backoffice.rapidz.io/client/page/index.html')->withCookie('APPLICATIONTOKEN', $redisSingleToken, 120);
        return redirect('https://backoffice.rapidz.io/client/page/blankohgfhg&777&.html?language=' . $user->language . '&token=' . csrf_token())->withCookie('APPLICATIONTOKEN', $redisSingleToken, 120);
    }

    public function postmanagerloginBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string',
            'area' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect('https://backoffice.rapidz.io/client/page/blank32132ohgfhg7454322334277&.html?language=en&token=' . csrf_token());
        }
        $area = str_replace('+', '', $request->input('area'));
        $user = BusinessUser::where("phone_number", $request->input("id"))->where('area', $area)->first();

        if (empty($user)) {
            return redirect('https://backoffice.rapidz.io/client/page/blank32132ohgfhg7454322334277&.html?language=en&token=' . csrf_token());
        }
        Auth::guard('business')->logout();
        //Auth::guard('business')->loginUsingId($request->input("id"));
        Auth::guard('business')->login($user);
        $lastLoginTimestamp = Redis::get('BUSINESS_STRING_SINGLETOKEN_' . $user->sellerId);
        $redisSingleToken = md5($user->sellerId . $lastLoginTimestamp . ".r2213doFGKpjkfg4");
        //return redirect('/home/info');
//        return redirect('https://backoffice.rapidz.io/client/page/index.html')->withCookie('APPLICATIONTOKEN', $redisSingleToken, 120);
        return redirect('https://backoffice.rapidz.io/client/page/blank32132ohgfhg7454322334277&.html?language=' . $user->language . '&token=' . csrf_token())->withCookie('APPLICATIONTOKEN', $redisSingleToken, 120);
    }

    /**
     * 2.13获取商家子用户 所有菜单栏
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |cookie |是  |string |登录成功产生的cookie   |
     * |_token |是  |string |CSRF TOKEN    |
     **返回示例**
     *
     * ```
     *
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * */
    public function getBusinessAllMenu()
    {
        $permission = BusinessPermission::where('is_show', 1)->where('status', '1')->where('is_menu', '2')->where('status', '1')->orderBy('id', 'asc')->orderBy('order_id', 'desc')->get(['id', 'name', 'name_cn', 'name_hk', 'pid', 'menu_url', 'menu_icon']);
        $permission = $this->buildTree($permission);

        return response_json(200, trans('app.getDataSuccess'), $permission);
    }

}
