<?php

namespace App\Http\Controllers\Member;

use App\Jobs\GenerateWalletAddress;
use App\Jobs\SendEmailCode;
use App\Mail\SendRegEmail;
use App\Models\Business\Language;
use App\Models\EmsCode;
use App\Models\Information\Information;
use App\Models\Information\InformationCategory;
use App\Models\MailCode;
use App\Models\Member\MemberLogLogin;
use App\Models\Message;
use App\Models\RegisterUsers;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\UsersWallet;
use Curl\Url;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;


if(isset($_SERVER['HTTP_ORIGIN'])){
    header("access-control-allow-origin: ".$_SERVER['HTTP_ORIGIN']);
    header("access-control-allow-headers: Origin, Content-Type, Cookie, X-CSRF-TOKEN, Accept, Authorization, X-XSRF-TOKEN");
    header("access-control-expose-headers: Authorization, authenticated");
    header("access-control-allow-methods: POST, GET, PATCH, PUT, OPTIONS");
    header("access-control-allow-credentials: true");
    header("access-control-max-age: 2592000");
}


/**
 * @group 用户信息
 * - author whm
 */

class UserController extends Controller
{


    // 获取csrftoken
    public function csrftoken(Request $request){

        $csrf_token = csrf_token();
        return response_json(200,trans('app.getDataSuccess'), array(
            '_token' => csrf_token(),
            'login_error' => Redis::get($csrf_token) ? : 0
        ));

    }

    // 登陆
    public function login(Request $request){
        $csrf_token = csrf_token();
        $validator = Validator::make($request->all(),[
            'email' => 'required|string|max:50',
            'password' => 'required|string|between:6,100',
        ]);
        if ($validator->fails()) {
            if(!empty(Redis::get($csrf_token))){
                Redis::incr($csrf_token);
            }else{
                Redis::setex($csrf_token, config('app.memberTokenExpired'), 1);
            }
            return response_json(402, $validator->errors()->first(), array(
                'login_error' => Redis::get($csrf_token) ? : 0
            ));
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $lang = $request->input('lang', 'cn');
        App::setLocale($lang);

        if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)){
            if(!empty(Redis::get($csrf_token))){
                Redis::incr($csrf_token);
            }else{
                Redis::setex($csrf_token, config('app.memberTokenExpired'), 1);
            }
            return response_json(402, trans('web.emailFormatError'), array(
                'login_error' => Redis::get($csrf_token) ? : 0
            ));
        }

        $user = User::select("id", "email", "language", "pin", "customer_type", "pin_error", "email_pin_error", "status", "login_error")
            ->where('email', $email)
            ->first();

        if(!empty($user) && $user->customer_type == 3){

            if(empty($user->status)){
                if(!empty(Redis::get($csrf_token))){
                    Redis::incr($csrf_token);
                }else{
                    Redis::setex($csrf_token, config('app.memberTokenExpired'), 1);
                }
                return response_json(402, trans('web.accountAreBlockLogin'), array(
                    'login_error' => Redis::get($csrf_token) ? : 0
                ));
            } else if($user->email_pin_error >= 3){
                if(!empty(Redis::get($csrf_token))){
                    Redis::incr($csrf_token);
                }else{
                    Redis::setex($csrf_token, config('app.memberTokenExpired'), 1);
                }
                return response_json(402, trans('web.pinErrorExceedThreeTimesLogin'), array(
                    'login_error' => Redis::get($csrf_token) ? : 0
                ));
            }

            if (Auth::guard('member')->attempt(['email' => $email, 'password' => think_md5($password)])) {
                // 用户单点登录
                $time = microtime(true);
                // md5 加密
                $singleToken = md5("member5$#dfsauj7bnccDDDcHcmn%1".$user->id . $time);
                // 当前 time 存入 Redis
                Redis::set('MEMBER_STRING_SINGLETOKEN_' . $user->id, $time);

                if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
                }else{
                    $ip = $request->getClientIp();
                }

                $userInfo = UserInfo::select('uid','last_ip')->where('uid',$user->id)->update(['last_ip'=>$ip]);
                // 登录日志
                $userLog = new MemberLogLogin();
                $userLog->ip = $ip;
                $userLog->userId = $user->id;
                $userLog->username = $user->email;
                $userLog->redistime = $time;
                $userLog->desc = $singleToken;
                $userLog->save();

                //登录设置语言
                if (!empty($lang)) {
                    $user->language = $lang;
                }
                //是否设置支付密码
                if (empty($user->pin)) {
                    $user->pin = 0;
                }else{
                    $user->pin =  1;
                }
                $user->login_error = 0;
                $rst = $user->save();

//                Redis::setex($csrf_token, config('app.memberTokenExpired'), 0);

                $rstData = ['code' => 200, 'msg' => trans('web.loginSuccess'), 'data' => [
                    'access_token' => $csrf_token,
                    'a' => $singleToken,
                    'user_id' => $user->id,
                    'token_type' => 'bearer',
                    'expires_in' => env('SESSION_LIFETIME')*60,
                ]];
                return response($rstData)->cookie('SECRETMEMBERLICATIONTOKEN', $singleToken, env('SESSION_LIFETIME'));

            }else{

                User::where('username', $request->input('username'))->increment("login_error");
                if(!empty(Redis::get($csrf_token))){
                    Redis::incr($csrf_token);
                }else{
                    Redis::setex($csrf_token, config('app.memberTokenExpired'), 1);
                }
                return response_json(402, trans('web.accountWrongOrPasswordWrong'), array(
                    'error' => 1,
                    'login_error' => $user->login_error + 1
                ));

            }
        }else{

            if(!empty($user)){
                User::where('username', $request->input('username'))->increment("login_error");
                return response_json(402, trans('web.isNotSubscriptions'), array(
                    'error' => 2,
                    'login_error' => $user->login_error + 1
                ));
            }else{
                if(!empty(Redis::get($csrf_token))){
                    Redis::incr($csrf_token);
                }else{
                    Redis::setex($csrf_token, 6000, 1);
                }
                return response_json(402, trans('web.isNotSubscriptions'), array(
                        'login_error' => Redis::get($csrf_token) ? : 0
                    )
                );
            }

        }

    }


    // 退出登陆
    public function logout(Request $request){

        $user = Auth::guard('member')->user();

        if (Auth::guard('member')->check()) {
            Auth::guard('member')->logout();
        }
        Auth::guard('member')->logout();

        return response_json(200, trans('web.logout'));

    }


    // 获取用户隐私信息
    public function getUserPrivacyInfo(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        // 用户类型， 1 普通用户， 2 企业用户
        return response_json(200, trans('web.getDataSuccess'), array(
            'user' => array(
                'uid' => intval($user->id),
                'user_type' => $user->user_type,
                'portRaitUri' => $user->portRaitUri,
                'ID_card' => $user->ID_card,
                'company_name' => $user->company_name,
                'license_code' => $user->license_code,
                'front_photo' => $user->front_photo ?  : '',
                'back_photo' => $user->back_photo ? : '',
                'license_thumb' => $user->license_thumb ? : '',
                'adm_check' => $user->adm_check,
            ),
        ));

    }


    /**
     * @param Request $request
     * @return \数组，laravel会自动转化为json
     * 获取用户信息
     */
    public function getUserInfo(Request $request){

        $uid = Auth::guard('member')->id();
        $user = new User();
        $userInfo = $user::getUser($uid);
        return response_json(200, trans('web.getDataSuccess'), array(
            'user' => array(
                'uid' => intval($userInfo->id),
                'username' => $userInfo->username,
                'portRaitUri' => url($userInfo->headimg_url),
                'email' => $userInfo->email,
                'sex' => $userInfo->sex,
                'ID_card' => $userInfo->ID_card,
                'address' => $userInfo->address,
                'recommend_code' => $userInfo->recommend_code,
            ),
        ));

    }

    // 修改用户私密信息
    public function editUserPrivacyInfo(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $portRaitUri = $request->input('portRaitUri', '');
        $ID_card = $request->input('ID_card', '');
        $company_name = $request->input('company_name', '');
        $license_code = $request->input('license_code', '');
        $front_photo = $request->input('front_photo', '');
        $back_photo = $request->input('back_photo', '');
        $license_thumb = $request->input('license_thumb', '');

        $data = array();
        if(!empty($portRaitUri)){
            $data['portRaitUri'] = $portRaitUri;
        }

        if(!empty($ID_card)){
            $data['ID_card'] = $ID_card;
        }

        if(!empty($portRaitUri)){
            $data['portRaitUri'] = $portRaitUri;
        }

        if(!empty($company_name)){
            $data['company_name'] = $company_name;
        }

        if(!empty($license_code)){
            $data['license_code'] = $license_code;
        }

        if(!empty($front_photo)){
            $data['front_photo'] = $front_photo;
        }

        if(!empty($back_photo)){
            $data['back_photo'] = $back_photo;
        }

        if(!empty($license_thumb)){
            $data['license_thumb'] = $license_thumb;
        }

        if(!empty($data)){
            User::where('id', $uid)->update($data);
            return response_json(200, trans('web.editSuccess'));
        }else{
            return response_json(403, trans('web.parameterEmpty'));
        }

    }


    // 修改用户信息
    public function editUserInfo(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $username = $request->input('username', '');
        $portRaitUri = $request->input('portRaitUri', '');
        $img_thumb = $request->input('img_thumb', '');
        $sex = $request->input('sex', '');// 1是男，2是女，3是保密
        $address = $request->input('address', '');
        $info_category = $request->input('info_category', '');
        $ID_card = $request->input('ID_card', '');

        $data = array();
        if(!empty($username)){
            $data['username'] = $username;
        }

        if(!empty($portRaitUri)){
            $data['portRaitUri'] = $portRaitUri;
        }

        if(!empty($img_thumb)){
            $data['img_thumb'] = $img_thumb;
        }

        if(!empty($sex) && in_array($sex, [1, 2, 3])){
            $data['sex'] = $sex;
        }

        if(!empty($address)){
            $data['address'] = $address;
        }

        if(!empty($ID_card)){
            $data['ID_card'] = $ID_card;
        }

        if(!empty($info_category)){
//            $info_category = array();
//            foreach ($info_category_arr as $category){
//                if($category['selected']){
//                    $info_category[] = $category['category_id'];
//                }
//            }
//            $info_category = implode(",", $info_category);
            $data['info_category'] = $info_category;
        }

        if(!empty($data)){
            User::where('id', $uid)->update($data);
            return response_json(200, trans('web.editSuccess'));
        }else{
            return response_json(403, trans('web.parameterEmpty'));
        }

    }


    // 发送邮件
    // type  1忘记pin码 2忘记密码 3注册 4修改 pos账号密码 5修改个人资料(重置手机号码) 6重置邮箱 7 认证邮箱 8 设置pin密码 9 提现
    public function sendMail(Request $request){

        $verify_status = 0;
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'email' => 'required|string',
            'lang' => 'nullable|string|min:2|max:5',
        ]);
        if ($validator->fails()) {
            return response_json(401,$validator->errors()->first());
        }
        if (Auth::guard('member')->user()) {
            $defaultLang = Auth::guard('member')->user()->member_language;
        }else{
            $defaultLang = 'cn';
        }
        $language = empty($request->lang) ? $defaultLang : $request->lang;
        $email = $request->input('email');
        $type = $request->input('type');

        if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)){
            return response_json(402, trans('web.emailFormatError'));
        }

        \App::setLocale($language);
        if($type==1){
            $types = MailCode::TYPE_FORGET_PIN;
        }elseif($type==2){
            $types = MailCode::TYPE_FORGET_PASSWORD;
        }elseif($type==3){
            $types = MailCode::TYPE_REG;
        }elseif($type==4){
            $types = MailCode::TYPE_CHANGE_POS;
        }elseif($type==5){
            $types = MailCode::TYPE_MODIFY_PROFILE;
        }elseif($type==6){
            $types = MailCode::TYPE_RESET_EMAIL;
        }elseif($type==7){
            $types = MailCode::TYPE_VERIFYEMAIL;
        }elseif($type==8){
            $types = MailCode::SET_PIN;
        }elseif ($type==9){
            $types = MailCode::CASH_WITHDRAWAL;
        }else{
            return  response_json(402, trans('web.parameterEmpty'));
        }

        //非注册操作都应该验证该郵箱是否注册过
        if($type!=3 && $type!=6){
            $user = User::select("id")->where('email', $email)->first();
            if (empty($user)) {
                return response_json(402,trans('web.emailNotFound'));
            }else{
                $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                    ->where('user_id',$user->id)
                    ->where('type', $types)
                    ->where('email', $email)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s'))
                    ->count();
                $uid = $user->id;
            }
        }elseif($type==3){
            $user = User::select("id")->where('email', $email)->first();
            if (!empty($user)) {
                return response_json(402,trans('web.regEmailAlreadyRegister'));
            }else{
                $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                    ->where('type', $types)
                    ->where('email', $email)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s'))
                    ->count();
                $uid = '';
            }
        }
        else{
            $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                ->where('type', $types)
                ->where('email', $email)
                ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                ->count();
            $uid = '';
        }

//        if ($record>=3){
//            return  response_json(402, trans('web.emailSendTooQuickly'));
//        }

        $code = rand(100000, 999999);
        $mailCode = new MailCode();
        $mailCode->user_id = $uid;
        $mailCode->email = $email;
        $mailCode->expire_time = date('Y-m-d H:i:s', time()+300);
        $mailCode->code = $code;
        $mailCode->verify_status = $verify_status;
        $mailCode->type = $types;
        $mailCode->status = MailCode::STATUS_UNVERIFY;
        $mailCode->save();

        SendEmailCode::dispatch($mailCode)->onQueue('mail');

        return response_json(200, trans('web.emailSendSuccess'));

    }


    // 重置PIN密码
    public function resetPin(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(),[
            // 'old_pin' => 'required|string|min:32|max:50',
            'pin' => 'required|string|min:32|max:50',
            'r_pin' => 'required|string|min:32|max:50',
            'code' => 'required|string|min:6|max:6',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $code = $request->input('code');
        // old_pin
//        $old_pin = $request->input('old_pin');

//        if(empty($old_pin)){
//            return response_json(402, trans('web.OldpasswordIsNotEmpty'));
//        }
        if ($request->input('pin') != $request->input('r_pin')){
            return response_json(402, trans('web.passwordConfirmError'));
        }
//        else if(!Hash::check(think_md5($request->input('old_pin')), $user->pin)){
//            return response_json(402, trans('web.OldpasswordIsError'));
//        }


        // 验证邮件验证码
        $record = MailCode::where('email', $user->email)
            ->where('expire_time', '>', date('Y-m-d H:i:s'))
            ->where('user_id', $uid)
            ->where('type', MailCode::SET_PIN)
            ->where('status', MailCode::STATUS_UNVERIFY)
            ->where('verify_status', 0)
            ->orderBy('expire_time', 'desc')
            ->first();
        if (!$record) {
            return response_json(402, trans('web.youdonotsendtheemail'));
        }elseif ($record->code != $code){
            return response_json(402, trans('web.codeUndefined'));
        }

        $user->pin = bcrypt(think_md5($request->input('pin')));
        $user->pin_error = 0;
        $rst = $user->save();

        if ($rst) {
            DB::commit();
            return response_json(200, trans('web.pinChangeSuccess'));
        }else{
            DB::rollBack();
            return response_json(403, trans('web.pinChangeFail'));
        }

    }


    // 获取用户管理的可切换语言
    public function getAllLanguage(){

        $allLang = Language::select("lang as lang_name", "lang_s as lang")
            ->where('status', 1)
            ->get()
            ->toArray();
        return response_json(200, trans('web.getDataSuccess'), array(
            'lang' => $allLang
        ));

    }

    // 设置后台语言
    public function setLang(Request $request){

        $user = Auth::guard('member')->user();
        $validator = Validator::make($request->all(),[
            'lang' => 'string|required',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        $lang = $request->input('lang');
        if(in_array($request->input('lang'), ['cn', 'en'])){
            $user->member_language = $lang;
            $rst = $user->save();
            if ($rst) {
                App::setLocale($lang);
                return response_json(200, trans('web.changeLanguageSuccess'));
            }else{
                return response_json(403, trans('web.changeLanguageFail'));
            }
        }else{
            return response_json(402, trans('web.changeLanguageFail'));
        }

    }

    // 忘记密码
    public function forgetLoginPwd(Request $request){

        $validator = Validator::make($request->all(),[
            'email' => 'required|string',
            'password' => 'required|string|min:32|max:50',
            'code' => 'required|string|min:6|max:6',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        // email
        $email = $request->input('email');
        $code = $request->input('code');

        if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)){
            return response_json(402, trans('web.emailFormatError'));
        }


        $user = User::select("id", "last_ip", "email", "language")->where('email', $email)->first();
        if (empty($user)) {
            return response_json(402,trans('web.emailNotFound'));
        }
        $lang = $request->input('lang', $user->language);
        App::setLocale($lang);

        // 验证邮件验证码
        $record = MailCode::where('email', $email)
            ->where('expire_time', '>', date('Y-m-d H:i:s'))
            ->where('type', MailCode::TYPE_FORGET_PASSWORD)
            ->where('status', MailCode::STATUS_UNVERIFY)
            ->where('verify_status', 0)
            ->orderBy('expire_time', 'desc')
            ->first();
        if (!$record) {
            return response_json(402, trans('web.youdonotsendtheemail'));
        }elseif ($record->code != $code){
            return response_json(402, trans('web.codeUndefined'));
        }

        $record->status = 1;
        $record->save();

        $user->password = bcrypt(think_md5($request->input('password')));
        $user->status = 1;
        $user->email_pin_error = 0;
        $rst = $user->save();
        if ($rst) {
            DB::commit();
            //存入一个退出app登录的redis
            $checkUidKey = $user->id.md5($user->id).'member';

            $needLoginTtl = config('app.needloginttl')*60;
            $needLoginTtl = empty($needLoginTtl)?2592000:$needLoginTtl;
            Redis::setex($checkUidKey, $needLoginTtl, $checkUidKey);
            return response_json(200, trans('web.resetPasswordSuccess'));
        }else{
            DB::rollBack();
            return response_json(403, trans('web.resetPasswordFail'));
        }


    }


    // 重置邮箱
    public function resetEmail(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $messages = [
            'new_email.unique' => trans('web.emailAlreadyExit'),
        ];
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:6|max:6',
            'new_email' => 'required|string|unique:users,email',
        ], $messages);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        $new_email = $request->input("new_email");
        $code = $request->input('code');

        try {

            DB::beginTransaction();

            $powerc = config('web.powerc');
            //start万能验证码
            $powercdie = config('web.powercdie');
            if ($request->input('code')!==$powerc || $powercdie==1){

                $mailCode = MailCode::where('email',  $new_email)
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('code', $code)
                    ->where('type', MailCode::TYPE_RESET_EMAIL)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->orderBy('expire_time', 'desc')
                    ->first();
                if (!$mailCode || $request->input('code') != $mailCode->code) {
                    return response_json(402,trans('web.codeValidateFail'));
                }
                $mailCode->status = MailCode::STATUS_VERIFYED;
                $mailCode->save();

            }//end 万能验证码

            $user->email = $new_email;
            $user->email_status = 2;
            $result = $user->save();

            if ($result) {
                DB::commit();
                return response_json(200,trans('web.emailResetSuccess'));
            }else{
                DB::rollBack();
                return response_json(402,trans('web.emailResetFail'));
            }

        } catch (\Exception $exception) {

            DB::rollBack();
            Log::useFiles(storage_path('resetEmail.log'));
            Log::info('user_id:'.$uid.', new_email:'.$new_email.',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());
            return response_json(402, trans('web.emailResetFail'));

        }


    }


    // 注册
    public function register(Request $request){


        $lang = $request->input('lang', 'cn');
        \App::setLocale($lang);

        $messages = [
            'email.unique' => trans('web.emailAlreadyExit'),
            'code.required' => trans('web.codeUndefined'),
            'password.required' => trans('web.pinTip'),
            'ID_card.required' => trans('web.IDCardCanNotBeEmpty'),
            'front_photo.required' => trans('web.pleaseUploadPictures'), // pleaseUploadPictures
            'back_photo.required' => trans('web.pleaseUploadPictures'), // pleaseUploadPictures
            'license_thumb.required' => trans('web.pleaseUploadPictures'), // pleaseUploadPictures
            'info_category.required' => trans('web.categoryNotExist'),
            'company_name.required' => trans('web.companyNameCanNotBeEmpty'),
            'license_code.required' => trans('web.licenseCodeCanNotBeEmpty'),
        ];
        $user_type = $request->input('user_type', 1);
        $validator_arr = [
            'email' => 'required|string|unique:users,email',
            'code' => 'required|string|min:6|max:6',
            'password' => 'required|string|between:6,100',
            'ID_card' => 'required|string',
            'front_photo' => 'required|string',
            'back_photo' => 'required|string',
            'info_category' => 'required|string',
            'recommend' => 'nullable|string|min:4|max:4',
            'lang' => 'nullable|string',
            'user_type' => 'required|int|min:1|max:2',
        ];
        if($user_type == 2){
            $validator_arr[] = [
                'company_name' => 'required|string',
                'license_thumb' => 'required|string',
                'license_code' => 'required|numeric',
            ];
        }
        $validator = Validator::make($request->all(), $validator_arr, $messages);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $info_category = $request->input('info_category', '');
        $ID_card = $request->input('ID_card', '');
        $front_photo = $request->input('front_photo', '');
        $back_photo = $request->input('back_photo', '');
        $recommend = $request->input('recommend', '');
        $company_name = $request->input('company_name', '');
        $license_thumb = $request->input('license_thumb', '');
        $license_code = $request->input('license_code', '');

        // 验证邮件格式
        if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)){
            return response_json(402, trans('web.emailFormatError'));
        }

        $redisKey = "reg_user_".$email;
        //注册加锁
        if (Redis::command('set', ['reg_lock_'.$redisKey, true, 'NX', 'EX', 10])){

            try{

                // 资讯分类
                $info_category_ids = explode(',', $info_category);
                $info_category_ids = InformationCategory::select("id")
                    ->whereIn('id', $info_category_ids)
                    ->where('enabled', 1)
                    ->get()
                    ->toArray();
                if(!empty($info_category_ids)){
                    $info_category_ids = array_column($info_category_ids, 'id');
                    $info_category = implode(',', $info_category_ids);
                }else{
                    return response_json(403, trans('web.parameterError'));
                }

                //  1 版本更新，2 活动通知， 3 通知，4 公告 ，5 维护, 6 广告
                $message = Message::where('type', 5)
                    ->select("id", "title", "content", "type","start_time","end_time")
                    ->where('lang', $lang)
                    ->orderBy('id', 'desc')
                    ->first();
                $now_time = time();
                if (!empty($message)){
                    if ($now_time>strtotime($message->start_time) && $now_time<strtotime($message->end_time)) {
                        if (!empty($message->end_time)) {
                            return response_json(499, trans('web.appismaintain'));
                        } else {
                            return response_json(499, trans('web.appismaintain'));
                        }
                    }
                }

                DB::beginTransaction();

                $code = MailCode::where('email', $request->input('email'))
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('type', MailCode::TYPE_REG)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->orderBy('expire_time', 'desc')
                    ->first();
                if (!$code) {
                    DB::rollBack();
                    Redis::del('reg_lock_'.$redisKey);
                    return response_json(402,trans('web.youdonotsendtheemail'));
                }
                if ($request->input('code') != $code->code) {
                    DB::rollBack();
                    Redis::del('reg_lock_'.$redisKey);
                    return response_json(402,trans('web.codeUndefined'));
                }

                // 注册生成该APP用户的推荐码
                $newRecommendcode = randomkeys(4);
                while(User::select("id")->where('recommend_code', $newRecommendcode)->first()) {
                    $newRecommendcode = randomkeys(4);
                }

                $recommender_id = 0;
                // 验证推荐码，正确入库
                if($recommend){
                    if ($originRecommendUser = User::select("id")->where('recommend_code', $recommend)->first()) {
                        $recommender_id = $originRecommendUser->id;   //推荐人的用户id
                    } else{
                        DB::rollBack();
                        Redis::del('reg_lock_'.$redisKey);
                        return response_json(402,trans('web.recommendCodeUndefined'));
                    }
                }

                $data = array(
                    'portRaitUri' => 'storage/img/defaultlogo.png',
                    'email' => $email,
                    'email_status' => 2,
                    'username' => $email,
                    'password' => bcrypt(think_md5($password)),
                    'info_category' => $info_category,
                    'front_photo' => $front_photo,
                    'ID_card' => $ID_card,
                    'back_photo' => $back_photo,
                    'country_id' => 0,
                    'recommend_code' => $newRecommendcode,
                    'recommender_id' => $recommender_id,
                    'user_type' => $user_type,
                    'language' => $lang,
                    'member_language' => $lang,
                    'customer_type' => 3,
                    'company_name' => $company_name,
                    'license_thumb' => $license_thumb,
                    'license_code' => $license_code,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'is_pc_create' => 1
                );
                $lastid = User::insertGetId($data);

                //生成环信账号
//                $url = url()->current();
//                // 不是正式服，注册test账号
//                if (strpos($url, 'chain-chat.app')){
//                    $isEasemob = User::createEasemob($lastid);
//                }else{
//                    $isEasemob = User::createEasemob($lastid, 0, 2);
//                }
//                if($isEasemob['code'] != 200){
//                    DB::rollBack();
//                    Redis::del('reg_lock_'.$redisKey);
//                    return response_json(402, $isEasemob['msg']);
//                }
                // 生成钱包地址
                // GenerateWalletAddress::dispatch($lastid)->onQueue('getnewaddress');

                $easemob = RegisterUsers::select("user_id", "easemob_u", "easemob_p")->where('user_id', $lastid)->where('error', '')->first();
                if(!empty($easemob)){
                    (new User())->where('id', $lastid)->update([
                        'easemob_u' => $easemob->easemob_u,
                        'easemob_p' => $easemob->easemob_p,
                    ]);
                }else{
                    // 如果没有之前生成环信账号, 则现在生成
                    $url = url()->current();
                    if (IS_FORMAL_HOST){
                        $isEasemob = User::createEasemob($lastid);
                    }else{
                        $isEasemob = User::createEasemob($lastid, 0, 2);
                    }
                    if($isEasemob['code'] != 200){
                        DB::rollBack();
                        Redis::del('reg_lock_'.$redisKey);
                        return response_json(402,$isEasemob['msg']);
                    }else{
                        $register_users = array(
                            'user_id' => $lastid,
                            'easemob_u' => $isEasemob['data']['username'],
                            'easemob_p' => $isEasemob['data']['password'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        );
                        RegisterUsers::insert($register_users);
                    }
                }

                Redis::del('reg_lock_'.$redisKey);
                DB::commit();

                return response_json(200, trans('web.regSuccess'));

            }  catch (\Exception $exception) {

                DB::rollBack();
                Redis::del('reg_lock_'.$redisKey);
                Log::useFiles(storage_path('memberRegister.log'));
                $input = $request->all();
                Log::info("Member Register fail:".', input:'.json_encode($input, JSON_UNESCAPED_UNICODE)."APP Register fail: exeception".',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());
                return response_json(402, trans('web.regFail'), array(
                    'error' => 1
                ));

            }

        }else{

            return response_json(402, trans('web.accessFrequent'));

        }

    }


    // 生成验证码
    public function captcha(Request $request){

        //获取版本号
        $lang = $request->input('lang', 'cn');

        App::setLocale($lang);
        $source = imagecreatefrompng(public_path('captcha/banner.png'));
        $mask =imagecreatefrompng(public_path('captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png'));
        $xSize_picture = imagesx($source);
        $ySize_picture = imagesy($source);
        $xSize = imagesx($mask);
        $ySize = imagesy($mask);
        $rand_x = rand($xSize_picture/2,$xSize_picture-$xSize);
        $rand_y = rand($ySize,$ySize_picture-$ySize);

        $insert_data = array(
            'img_background' => public_path('captcha/banner.png'),
            'img_small' =>  public_path('captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png'),
            'x'  => $rand_x,
            'y'  =>  $rand_y,
            'creation_at'   =>  date('Y-m-d H:i:s'),
            'updated_at'    =>  date('Y-m-d H:i:s'),
        );

        $id = DB::table('img_captcha')->insertGetId($insert_data);
        if($id){

            $token = Crypt::encryptString('img_id:'.$id);
            $id = Crypt::encryptString('jiami_id:'.$id);
            $data['y'] = $rand_y;
            $data['token'] = $id;
            $data['background'] = url('api/user/getBackground?token='.$token);
            $data['small'] = url('api/user/small?token='.$token);

            return response_json(200, trans('web.getDataSuccess'), $data);

        }else{

            return response_json(403, trans('web.getDataFail'));

        }

    }


    // 检验验证码
    public function checkCaptcha(Request $request){

        $validator =Validator::make($request->all(),[
            'token' => 'required',
            'x' => 'required',
        ]);
        if($validator->fails()){
            return response_json(402,$validator->errors()->first());
        }
        //设置语言
        $lang = $request->input('lang','cn');
        $token = $request->input('token');
        $x = $request->input('x');

        App::setLocale($lang);

        $token = Crypt::decryptString($token);
        $id = str_replace('jiami_id:','',$token);
        $data = DB::table('img_captcha')->where('id',$id)->first();
        $now_date = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $md5_x[] = md5('x_md5'.$data->x);
        for ($i=1;$i<=3;$i++){
            $jia=$data->x+$i;
            $jian=$data->x-$i;
            $md5_x[]= md5('x_md5'.$jia);
            $md5_x[]= md5('x_md5'.$jian);
        }

        if($data->captcha==0){
            if($now_date < $data->creation_at){
                if(in_array($x,$md5_x)){
                    DB::table('img_captcha')
                        ->where('id', $id)
                        ->update(['captcha' => 1,'updated_at'=>date('Y-m-d H:i:s')]);
                    return response_json(200,trans('app.codeValidateSuccessfully'));
                }else{
                    DB::table('img_captcha')
                        ->where('id', $id)
                        ->update(['captcha' => 2,'updated_at'=>date('Y-m-d H:i:s')]);
                    return response_json(403,trans('app.codeValidateFail'));
                }
            }else{
                DB::table('img_captcha')
                    ->where('id', $id)
                    ->update(['captcha' => 3,'updated_at'=>date('Y-m-d H:i:s')]);
                return response_json(403,trans('app.codeValidateFail'));
            }
        }else{
            return response_json(403,trans('app.doNotSubmitAgain'));
        }

    }







}
