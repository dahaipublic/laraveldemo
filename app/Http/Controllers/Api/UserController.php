<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\LoginController;
//use App\Jobs\SendRegEmail;
use App\Jobs\SendEmailCode;
use App\Libs\Ems;
use App\Libs\Monyunems;
//use App\Models\Bank;
use App\Models\Currency;
use App\Models\EmsCode;
use App\Models\MailCode;
use App\Models\AppRecommend;
use App\Models\User;
use App\Models\RegisterUsers;
use App\Models\Appcheckpinfail;
use App\Models\FcmTokenInfo;
use App\Models\Api\Candy;
use App;
//use Storage;
use App\Models\Business\Regions;
use App\Libs\Common;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
//use App\Models\UsersWallet;
use App\Jobs\GenerateWalletAddress;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Storage;
use App\Models\UsersWallet;
use App\Models\Api\CandyOrder;
use App\Models\Order;
use App\Models\Api\GiveRpzx;
use App\Models\Api\Captcha;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Crypt;
use App\Models\Api\ImgCaptcha;
use App\Jobs\DeleteRedisToken;
use App\Models\UserLogLogout;

/**
 * @group 2用戶操作API
 * - author linlicai
 * - 登录用户的个人信息接口
 */
class UserController extends Controller
{

    protected $header = 'authorization';
    protected $prefix = 'bearer';
    public $_data;

    public function __construct()
    {
        $this->_data = Common::makeVerificationApi();
    }

    public function index()
    {
        return view('user.profile', [
            'route' => Common::getSideBar()
        ]);
    }


    /**
     * 用户个人信息接口
     */
    public function userInfo()
    {
        $authid = auth('api')->id();
        $authuser = User::from("users as a")
            ->join('users_info as b', 'a.id', '=', 'b.uid')
            ->select('a.id', 'a.username', 'a.phone', 'a.phone_status', 'a.email_status', 'a.email', 'a.headimg_url', 'a.headimg_thumb', 'a.sex', 'a.birthday', 'a.area', 'b.address')
            ->where(['a.id' => $authid])
            ->lockForUpdate()
            ->first();
        if (!empty($authuser->headimg_url)) {
            $authuser->headimg_url = url($authuser->headimg_url);
        } else {
            $authuser->headimg_url = url('storage/img/defaultlogo.png');
        }
        if (empty($authuser->recommend_code)) {
            $recommend_code = randomkeys(4);
            $user_re = User::where('id', $authid)->first();
            $user_re->recommend_code = $recommend_code;
            $user_re->save();
            $authuser->recommend_code = $recommend_code;
        }
        if (empty($authuser->img_thumb)) {
            $authuser->img_thumb = url('storage/img/defaultlogo.png');
        } else {
            $authuser->img_thumb = url($authuser->img_thumb);
        }

        if (!empty($authuser)) {
            return response_json(200, trans('app.success'), $authuser);
        } else {
            return response_json(402, trans('app.fail'));
        }
    }

    /**
     *  退出登录
     */
    public function logout(Request $request)
    {
        $myapitoken = $this->parse($request);
        $userId = Auth::guard('api')->id();

        $tokenarrays = explode('.', $myapitoken);
        if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } else {
            $ip = $request->getClientIp();
        }
        //app用户登录日志
        $mirotime = Redis::get('API_STRING_SINGLETOKEN_' . $userId);
        $agent = $request->header('User-Agent');
        $userLog = new UserLogLogout();
        $userLog->ip = $ip;
        $userLog->userId = $userId;
        // $userLog->username = $user->username;
        $userLog->redistime = $mirotime ?: time() . str_random(4);
        $userLog->sigle_token = $tokenarrays[1];
        $userLog->login_token = $tokenarrays[0];
        $userLog->device = $agent;
        $userLog->save();
        Redis::del($tokenarrays[0]);

        // DeleteRedisToken::dispatch($userId)->onQueue('delete_redis_token');
        $list = (new User())->getRedisToken($userId);
        if (!empty($list)) {
            foreach ($list as $item) {
                Redis::del($item['redis_key']);
            }
        }
        // 删除redis中的token
        Redis::del('API_STRING_SINGLETOKEN_' . $userId);

        $vo = array(
            'code' => 200,
            'msg' => trans('app.logout'),
        );

        return $vo;
    }

    /**
     * Try to parse the token from the request header.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return null|string
     */
    public function parse($request)
    {
        $header = $request->headers->get($this->header) ?: $this->fromAltHeaders($request);

        if ($header && preg_match('/' . $this->prefix . '\s*(\S+)\b/i', $header, $matches)) {
            return $matches[1];
        }
    }

    /**
     * Attempt to parse the token from some other possible headers.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return null|string
     */
    protected function fromAltHeaders(Request $request)
    {
        return $request->server->get('HTTP_AUTHORIZATION') ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION');
    }


    /**
     * 2.1修改用户普通个人信息
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |username |否  |string |用户昵称   |
     * |actual_name |否  |string |用户真实姓名   |
     * |address |否  |string | 地址  |
     * |sex |否  |int |性别   |1为男，2为女，3为保密
     * |face_image |否  |image |用户头像   |mimes:jpg,png
     * |address |否  |string | 地址  |
     * |birthday |否  |string | 生日  | 时间戳
     *
     */
    public function update(Request $request)
    {

        $user = Auth('api')->user();
        $validator = Validator::make($request->all(), [
            'sex' => 'nullable|in:1,2,3', // 1为男，2为女，3为保密
            'username' => 'nullable|string|min:2|max:50',
            'actual_name' => 'nullable|string|min:2|max:15',
            'address' => 'nullable|string|max:50',
            'area' => 'nullable|numeric|min:1',
            'bank_name' => 'nullable|string|min:4|max:50',
            'card_number' => 'nullable|string|min:10|max:30',
            'cardholder_name' => 'nullable|string|max:30',
            //'btc_self_address' => 'nullable|string|min:26|max:34',
            //'rpz_self_address' => 'nullable|string|min:26|max:34',
            'face_image' => 'nullable',//|image|mimes:jpeg,png,jpg,gif,svg|max:8048
            'birthday' => 'nullable|string',
            'signature' => 'nullable|string',
            // 'swift_code' => 'nullable|string|min:3|max:15',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }

        //author llc   图片改成用ftp
        if ($request->file('face_image')) {

//            $face_image =  $request->file('face_image')->store('storage/face_image/'.date('Y-m-d',time()),'ftp');
//            //缩略图
//            $arr            = explode('.', $face_image);
//            $filetype       = end($arr);
//            $thumb_nameFtp     = basename($face_image, '.'.$filetype) . '_thumb' . '.' . "jpg";
//            $imgArrThumbFtp = Image::make($request->file('face_image'))->encode('jpg', 5);
//            Storage::disk('public')->put('storage/face_image/'.date('Y-m-d',time()) . '/'.$thumb_nameFtp, $imgArrThumbFtp);
//            $face_image_thumb  = $arr[0].'_thumb.'.'jpg';

            // $user->portRaitUri = $face_image;
            // $user->img_thumb = $face_image_thumb;
            $face_image = upload_img_file($request->file('face_image'), 0);
            $face_image_thumb = upload_img_file($request->file('face_image'), 1);

        } else {
            $face_image = '';
            $face_image_thumb = '';
        }

        if ($request->input('actual_name')) {
            $user->actual_name = $request->input('actual_name');
        }
        if ($request->input('username')) {
            $user->username = $request->input('username');
            $user->is_edit_nickname = 1;

        }
        if ($request->input('sex')) {
            $user->sex = $request->input('sex');
        }

        if ($request->input('birthday')) {
            $user->birthday = $request->input('birthday');
        }

        if ($request->input('area')) {
            $user->area = str_replace('+', '', $request->input('area'));
        }
        if ($request->input('address')) {
            $user->address = $request->input('address');
        }
        // 个性签名
        if ($request->input('signature')) {
            $user->signature = $request->input('signature');
        }

        if ($face_image) {
            $user->portRaitUri = $face_image;
            $user->img_thumb = $face_image_thumb;
        }
        $rst = $user->save();
        // authuser
        $authuser = User::where('id', $user->id)->first(['id', 'username', 'actual_name',
            'phone_number', 'img_thumb', 'phone_status', 'email_status', 'email', 'address', 'portRaitUri', 'sex', 'birthday', 'pin', 'easemob_u', 'easemob_p',
            'sellerId', 'area', 'fingerprint', 'customer_type', 'signature']);

        if ($rst) {
            return response_json(200, trans('app.userUpdateSuccess'), ['portRaitUri' => url($user->portRaitUri), 'img_thumb' => url($user->img_thumb), 'user' => $authuser]);
        } else {
            return response_json(402, trans('app.userUpdateFail'));
        }

    }


    /**
     * 2.2重置用户私密信息，
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |phone |否  |string |手机号码   |required_without:email
     * |email |否  |string |邮箱   |required_without:phone
     * |lock_pin |否  |string |APP屏幕锁   |
     * |pin |否  |string |支付密码   |
     * |password |否  |string |登录密码   |
     * |code |是  |string |验证码   |
     * |area |是  |string |地区编号   | required_without:email
     * |verifytype |是  |string |验证码类型  |1为手机验证码（sendmsg  的type为2）,2为邮箱验证码（sendemail  的type为2）
     * |entrance |是  |int |忘记密码入口  |1为未登录时在首页忘记密码2为在会员中心重置密码
     *|lang |否  |string |语言类型  |  cn 中文,en 英文,后面可能会加：fa泰文,es西班牙语,fr法国，hk繁体，kr韩语，ru俄罗斯语，de德语，vn越南语，tr土耳其语，nl荷兰语，pt葡萄牙语，it意大利语,pl波兰语
     * |返回示例|
     * |:-----  |
     * ```
     *   {
     *        "code": 200,
     *        "msg": "user update success",
     *        "data": []
     *    }
     * ```
     **备注**
     * - 修改用户信息需要验证码，意味着调用这个接口需要先调用2.6手机发送验证码（该接口的type参数要为2）  或者 调用2.8邮箱发送验证码（该接口的type参数要为2）
     * -每次只能修改lock_pin|pin|password其中一个
     * - entrance为1不需要2.17，，，entrance为2,调用该接口前需要调用2.17重置用户私密信息验证码验证，否则提示验证码 验证失败
     */
    public function resetPin(Request $request)
    {

        $validator = Validator::make($request->all(), [
            //'email' => 'required|max:100|required_without:phone',
            'email' => 'required|max:100',
            //'phone' => 'nullable|numeric|required_without:email',
            //'area'    => 'nullable|string|required_without:email',
            'old_pin' => 'nullable|string|between:6,100',
            'pin' => 'nullable|string|min:6',
            'r_pin' => 'nullable|string|min:6',
            'lock_pin' => 'nullable|string|min:6',
            'password' => 'nullable|string|between:8,32',
            'repassword' => 'nullable|string|between:8,32',
            'verifytype' => 'required|string',
            'code' => 'nullable|string|min:6|max:6',
            'entrance' => 'required|integer',
            'lang' => 'nullable|string',
        ]);
        //添加滑块校验
        if (!empty($request->input('captcha')) || !empty($request->input('key'))) {
            if (!in_array($request->input('email'), ['15113993100@163.com', '15113993101@163.com', '15113993102@163.com', '1262638533@qq.com'])) {
                $LoginController = new LoginController();
                $ischeckimg = $LoginController->checkimg($request->key, $request->captcha);
                if (!$ischeckimg) {
                    return response_json(402, trans('app.verifyFailPleaseRetry'));
                }
            }
        }

        if ($request->input('password') != $request->input('repassword')) {
            return response_json(402, trans('app.duplicatepassword'));
        }

        $input = $request->all();
        Log::useFiles(storage_path('resetPin.log'));
        Log::info(', input:' . json_encode($input, JSON_UNESCAPED_UNICODE));
        /**
         *  param int verifytype    发送什么类型的手机验证码,    //1忘记pin码      2忘记密码    //3注册认证手机号码   //4重置手机号码  //5修改个人资料   //
         */
        //2为在会员中心重置密码
        if ($request->input('entrance') == 2) {
            $verify_status = 0;
            //商家不可以修改密码
            $authUser = Auth::guard('api')->user();
            if ($authUser->customer_type == 2) {
                return response_json(402, trans('app.selleraccountcannodo'));
            }
            $language = $authUser->language;
            if (!empty($language)) {
                \App::setLocale($language);
            } else {
                return response_json(402, trans('app.editFail'));
            }
        } else {//1为未登录时在首页忘记密码
            $verify_status = 0;
            $language = empty($request->lang) ? 'cn' : $request->lang;
            if (!empty($language)) {
                \App::setLocale($language);
            }
        }
        if ($validator->fails()) {
            $error = [
                'error' => $validator->errors()->first(),
            ];
            return response_json(402, '', $error);
        }
        DB::beginTransaction();

        $powerc = config('app.powerc');
        //start万能验证码
        $powercdie = config('app.powercdie');
        if ($request->input('code') !== $powerc || $powercdie == 1) {

            $rst2 = true;
            //start万能验证码
            switch ($request->input('verifytype')) {
                case 2:
                    $user = User::where('email', $request->input('email'))->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    //商家不可以修改密码
                    if ($user->customer_type == 2) {
                        return response_json(402, trans('app.selleraccountcannodo'));
                    }
                    if ($user->email_status == 1) {
                        return response_json(402, trans('app.emailNotValify'), ['email_status' => 1]);
                    }

                    if (!empty($request->input('password'))) {
                        $record = MailCode::where('email', $request->input('email'))
                            ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                            ->whereIn('type', [MailCode::TYPE_FORGET_PASSWORD, MailCode::SET_PIN])// 2 or 8
                            ->where('status', MailCode::STATUS_UNVERIFY)
                            ->where('verify_status', 0)
                            ->orderBy('expire_time', 'desc')
                            ->first();

                        if (!$record) {
                            return response_json(402, trans('app.codeUndefined'));
                        } elseif ($record->code != $request->input('code')) {
                            return response_json(402, trans('app.codeUndefined'));
                        }
                    }
                    if (!empty($request->input('password'))) {
                        if ($request->input('code') != $record->code) {
                            return response_json(402, trans('app.codeValidateFail'));
                        }

                        if ($request->input('verifytype') == 2) {
                            $record->status = MailCode::STATUS_VERIFYED;
                            $rst2 = $record->save();
                        } else {
                            $record->status = EmsCode::STATUS_VERIFYED;
                            $rst2 = $record->save();
                        }
                    }
                    break;
                case 3:
                    $user = User::where('email', $request->input('email'))->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    //商家不可以修改密码
                    if ($user->customer_type == 2) {
                        return response_json(402, trans('app.selleraccountcannodo'));
                    }
                    if ($user->email_status == 1) {
                        return response_json(402, trans('app.emailNotValify'), ['email_status' => 1]);
                    }

                    if (!empty($request->input('pin'))) {
                        $record = MailCode::where('email', $request->input('email'))
                            ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                            ->whereIn('type', [MailCode::TYPE_FORGET_PASSWORD, MailCode::SET_PIN])// 2 or 8
                            ->where('status', MailCode::STATUS_UNVERIFY)
                            ->where('verify_status', 0)
                            ->orderBy('expire_time', 'desc')
                            ->first();
                        if (!$record) {
                            return response_json(402, trans('app.codeUndefined'));
                        } elseif ($record->code != $request->input('code')) {
                            return response_json(402, trans('app.codeUndefined'));
                        }
                    }
                    //邮箱修改支付密码
                    if (!empty($request->input('pin'))) {
                        if ($request->input('code') != $record->code) {
                            return response_json(402, trans('app.codeValidateFail'));
                        }

                        if ($request->input('verifytype') == 3) {
                            $record->status = MailCode::STATUS_VERIFYED;
                            $rst2 = $record->save();
                        } else {
                            $record->status = EmsCode::STATUS_VERIFYED;
                            $rst2 = $record->save();
                        }
                    }
                    break;
                case 4:
                    $user = User::where('email', $request->input('email'))->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    //商家不可以修改密码
                    if ($user->customer_type == 2) {
                        return response_json(402, trans('app.selleraccountcannodo'));
                    }
                    if ($user->email_status == 1) {
                        return response_json(402, trans('app.emailNotValify'), ['email_status' => 1]);
                    }
                    break;
                default;
            }

        } else {

            $rst2 = true;
            if ($request->input('verifytype') == 2) {

                $user = User::where('email', $request->input('email'))->first();
                if (empty($user)) {
                    return response_json(402, trans('app.emailNotRegister'));
                } elseif ($request->input('pin') && $user->email_pin_error >= 3) {
                    // 修改pin密码且之前错了三次
                    return response_json(402, trans('app.pinErrorExceedThreeTimesForget'));
                }

                //商家不可以修改密码
                if ($user->customer_type == 2) {
                    return response_json(402, trans('app.selleraccountcannodo'));
                }
                if ($user->email_status == 1) {
                    return response_json(402, trans('app.emailNotValify'), ['email_status' => 1]);
                }

            } else {

                $phone = $request->input('phone');
                $phone = str_replace(' ', '', $phone);
                //台湾地区以0开头的手机号码，发送短信需要去掉
                $firstm = substr($phone, 0, 1);
                if ($firstm == 0) {
                    $phone = substr($phone, 1);
                }

                $area = str_replace('+', '', $request->input('area'));
                $area = str_replace(' ', '', $area);

                $user = User::where('phone_number', $phone)
                    ->where('area', $area)
                    ->first();
                if (empty($user)) {
                    return response_json(402, trans('app.phoneNumberNotFound'));
                }

                //商家不可以修改密码
                if ($user->customer_type == 2) {
                    return response_json(402, trans('app.selleraccountcannodo'));
                }
                if ($user->phone_status == 1) {
                    return response_json(402, trans('app.phoneNotVerify'), ['phone_status' => 1]);
                }

            }
        }//end 万能验证码
        switch ($request->input('verifytype')) {
            case 2:
                $rstMsgNo = trans('app.passwordChangeFail');
                $rstMsgYes = trans('app.passwordChangeSuccess');
                break;
            case 3:
                $rstMsgNo = trans('app.passwordChangeFail');
                $rstMsgYes = trans('app.passwordChangeSuccess');
                break;
            case 4:
                $rstMsgNo = trans('app.passwordSetFail');
                $rstMsgYes = trans('app.passwordSetSuccess');
                break;
            default;
        }

        try {

            if ($request->input('lock_pin')) {
                $rstMsgYes = trans('app.lockpinChangeSuccess');
                $rstMsgNo = trans('app.lockpinChangeFail');
                $user->lock_pin = bcrypt(think_md5($request->input('lock_pin')));
            } elseif ($request->input('pin')) {
                if ($request->input('pin') == $request->input('r_pin')) {
                    $user->pin = bcrypt(think_md5($request->input('pin')));
                    $user->pin_error = 0;
                    $user->email_pin_error = 0;
                    //修改支付密码关闭指纹人脸支付的
                    $user->fingerprint_pay_status = 1;
                    $user->face_pay_status = 1;
                } else {
                    DB::rollBack();
                    return response_json(403, $rstMsgNo);
                }

            } elseif ($request->input('password')) {
                $rstMsgYes = trans('app.passwordChangeSuccess');
                $rstMsgNo = trans('app.passwordChangeFail');
                $user->password = bcrypt(think_md5($request->input('password')));
                // $user->pin_error = 0;
                $user->status = 1;
                $user->email_pin_error = 0;
                //修改登录密码关闭指纹人脸支付登录
                $user->fingerprint_login_status = 1;
                $user->face_login_status = 1;
                DeleteRedisToken::dispatch($user->id)->onQueue('delete_redis_token');
            } else {
                DB::rollBack();
                return response_json(403, $rstMsgNo);
            }

            Appcheckpinfail::where('userId', $user->id)->delete();
            $rst1 = $user->save();

            //return response_json(200,trans('app.userUpdateSuccess'));
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::info('Reset pin fail message:' . $exception->getMessage() . " ,trace " . $exception->getTraceAsString());
            return response_json(403, $rstMsgNo);
        }
        Log::info('rst11111:' . $rst1 . ', rst22222222' . $rst2);

        if ($rst1 && $rst2) {
            DB::commit();
            return response_json(200, $rstMsgYes);
        } else {
            DB::rollBack();
            return response_json(403, $rstMsgNo);
        }

    }

    /**
     * 发送密码重置通知。
     *
     * @param  string $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     *  2.3修改密码
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |old_pin |否  |string |老的支付密码   |
     * |new_pin |否  |string |新的支付密码   |
     * |confirm_pin |否  |string |确认支付密码   |
     * |old_lock_pin |否  |string |老的锁屏密码   |
     * |new_lock_pin |否  |string |新的锁屏密码   |
     * |confirm_lock_pin |否  |string |确认锁屏密码   |
     * |old_password |否  |string |老的登录密码   |
     * |new_password |否  |string |新的登录密码   |
     * |confirm_password |否  |string |确认登录密码   |
     * |返回示例|
     * |:-----  |
     *
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误/405未登录
     *  ```
     *{
     *    "code": 200,
     *    "msg": "Successfully modified the pin code",
     *    "data": []
     *}
     *```
     *
     **/
    public function changePin(Request $request)
    {

        $input = $request->all();
        Log::useFiles(storage_path('changePin.log'));
        Log::info(', input:' . json_encode($input, JSON_UNESCAPED_UNICODE));

        // Auth::guard('api')->onceUsingId($this->auth->manager()->getPayloadFactory()->buildClaimsCollection()->toPlainArray()['sub']);
        $validator = Validator::make($request->all(), [
            'old_pin' => 'nullable|string|min:6|max:100',
            'new_pin' => 'nullable|string|min:6|max:100',
            //'confirm_pin' => 'nullable|string|min:6|max:6',

            'old_lock_pin' => 'nullable|string|min:6|max:100',
            'new_lock_pin' => 'nullable|string|min:6|max:100',
            //'confirm_lock_pin' => 'nullable|string|min:6|max:6',

            'old_password' => 'nullable|string|between:6,100',
            'new_password' => 'nullable|string|between:6,100',
            //'confirm_password' => 'nullable|string|between:6,12',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = Auth('api')->user();
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        if ($request->input('old_pin') && $request->input('new_pin')) {
            if (!Hash::check(think_md5($request->input('old_pin')), $user->pin)) {
                $email_pin_error = $user->email_pin_error + 1;
                $pin_error = $user->pin_error + 1;
                User::where('id', $user->id)->update([
                    'email_pin_error' => $email_pin_error,
                    'pin_error' => $pin_error,
                ]);
                DB::commit();
                if ($email_pin_error == 3) {
                    DeleteRedisToken::dispatch($user->id)->onQueue('delete_redis_token');
                    return response_json(405, trans('app.pinErrorExceedThreeTimesLogin'));
                }
                return response_json(402, trans('app.OldpasswordIsError'));
            }
            // if ($request->input('new_pin') != $request->input('confirm_pin')) {
            //     return response_json(402,trans('app.pinConfirmError'));
            // }
            $user->pin = bcrypt(think_md5($request->input('new_pin')));
            $result = $user->save();

            //存入一个退出app登录的redis
//            $checkUidKey = $user->id . md5($user->id) . 'member';
//            $arr = [
//                '$checkUidKey' => $checkUidKey,
//            ];
//            Log::info('app', $arr);
//            $needLoginTtl = config('app.needloginttl') * 60;
//            $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;
//            Redis::setex($checkUidKey, $needLoginTtl, $checkUidKey);


            return $result ? response_json(200, trans('app.pinChangeSuccess')) : response_json(403, trans('app.pinChangeFail'));
        } else if ($request->input('old_lock_pin') && $request->input('new_lock_pin')) {
            if (!Hash::check(think_md5($request->input('old_lock_pin')), $user->lock_pin)) {
                return response_json(401, trans('app.oldPinError'));
            }
            if ($request->input('new_lock_pin') != $request->input('confirm_lock_pin')) {
                return response_json(402, trans('app.pinConfirmError'));
            }
            $user->lock_pin = bcrypt(think_md5($request->input('new_lock_pin')));
            // $user->pin_error = 0;   //恢复用户支付功能
            $user->email_pin_error = 0;   //恢复用户支付功能

            $result = $user->save();
            //删掉check pin 记录
            Appcheckpinfail::where('userId', $user->id)->delete();

            //存入一个退出app登录的redis
            $checkUidKey = $user->id . md5($user->id) . 'member';
            $arr = [
                '$checkUidKey' => $checkUidKey,
            ];
            Log::info('app222222', $arr);
            $needLoginTtl = config('app.needloginttl') * 60;
            $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;
            Redis::setex($checkUidKey, $needLoginTtl, $checkUidKey);


            return $result ? response_json(200, trans('app.pinChangeSuccess')) : response_json(403, trans('app.pinChangeFail'));
        } else if ($request->input('old_password') && $request->input('new_password')) {
            if (!Hash::check(think_md5($request->input('old_password')), $user->password)) {
                return response_json(401, trans('app.OldpasswordError'));
            }
            if ($request->input('new_password') != $request->input('confirm_password')) {
                return response_json(402, trans('app.passwordConfirmError'));
            }
            $user->password = bcrypt(think_md5($request->input('new_password')));
            // $user->pin_error = 0;
            $user->email_pin_error = 0;
            $result = $user->save();
            DeleteRedisToken::dispatch($user->id)->onQueue('delete_redis_token');

            //存入一个退出app登录的redis
//            $checkUidKey = $user->id . md5($user->id) . 'member';
//            $arr = [
//                '$checkUidKey' => $checkUidKey,
//            ];
//            Log::info('app3333333333', $arr);
//            $needLoginTtl = config('app.needloginttl') * 60;
//            $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;
//            Redis::setex($checkUidKey, $needLoginTtl, $checkUidKey);

            return $result ? response_json(200, trans('app.passwordChangeSuccess')) : response_json(403, trans('app.passwordChangeFail'));
        } else {
            return response_json(403, trans('app.passwordChangeFail'));
        }

    }

    /**
     *  2.4重置手机号码
     *  -该接口需要调用2.6手机发送验证码type为4
     *  -该接口需要调用2.8邮箱发送验证码type为5
     *  -该接口需要调用2.18先验证验证码：重置手机号码或者邮箱号码
     *
     *
     * -请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |ccode |是  |string |上一步的邮箱验证码   |
     * |code |是  |string |手机验证码   |
     * |phone |是  |string |新的手机号码   |
     * |area |是  |string |地区编码   |
     * |verifytype |是  |string |验证码类型  |1为手机验证码（sendmsg  的type为4）,2为邮箱验证码（sendemail  的type为5）
     *
     * |返回示例|
     * |:-----  |
     *```
     *{
     *    "code": 200,
     *    "msg": "Phone Number Reset Successfully",
     *    "data": []
     *}
     *```
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *
     **/
    public function resetPhone(Request $request)
    {
        $messages = [
            'phone.unique' => trans('app.phoneAllreadyExit'),
        ];
        $validator = Validator::make($request->all(), [
            'area' => 'required|string|min:1|max:5',
            'code' => 'required|string|min:6|max:6',
            'ccode' => 'required|string|min:6|max:6',
            'phone' => 'required|string|min:6|unique:users,phone_number',
            // 'email' => 'nullable|string|max:50',

        ], $messages);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //$phone = str_replace($request->input('area'), '', $request->input('phone'));
        $phone = $request->input('phone');

        $phone = str_replace(' ', '', $phone);
        //台湾地区以0开头的手机号码，发送短信需要去掉
        $firstm = substr($phone, 0, 1);
        if ($firstm == 0) {
            $phone = substr($phone, 1);
        } else {
            $phone = $phone;
        }
        $area = str_replace('+', '', $request->input('area'));
        $area = str_replace(' ', '', $area);

        //邮箱验证码
        //if($request->input('verifytype')==2){
        $user = auth('api')->user();
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        // if ($user->email!==$request->input('email')) {
        //     return response_json(402,trans('app.emailNotReg'));
        // }
        //$user = User::where('email',$request->input('email'))->first();
        //dd($user);
        if ($user->email_status == 1) {
            return response_json(402, trans('app.emailNotVerify'));
        }
        try {
            DB::beginTransaction();


            $powerc = config('app.powerc');

            //start万能验证码
//            if ($request->input('code')!==$powerc) {
            $powercdie = config('app.powercdie');
            if ($request->input('code') !== $powerc || $powercdie == 1) {

                $mailCode = MailCode::where('email', $user->email)
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('code', $request->input('ccode'))
                    ->where('type', MailCode::TYPE_MODIFY_PROFILE)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->where('verify_status', MailCode::STATUS_VERIFYED)
                    ->orderBy('expire_time', 'desc')
                    ->first();
//            if (!$mailCode) {
//                return response_json(402,trans('app.youdonotsendtheemail'));
//            }
                if (!$mailCode || $request->input('ccode') != $mailCode->code) {
                    return response_json(402, trans('app.lastEmailCodeInvalide'));
                }
                $mailCode->status = MailCode::STATUS_VERIFYED;
                $mailCode->save();
                // }else{

//            if ($area !=='86') {
//                $area = '00'.$area;
//            }
                // $user = User::where('phone_number',$phone)
                //     ->where('area',$area)
                //     ->first();
                // dd($user);
                // if (empty($user)) {
                //     return response_json(402,trans('app.phoneNumberNotFound'));
                // }
                $record = EmsCode::where('mobile', $area . $phone)
                    ->where('code', $request->input('code'))
                    ->where('status', EmsCode::STATUS_UNVERIFY)
                    //->where('verify_status', EmsCode::STATUS_VERIFYED)
                    ->where('type', EmsCode::TYPE_RESETPHONE)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->orderBy('updated_at', 'desc')
                    ->first();
                // }
                // var_dump($email_status);die();
//        if (!$record) {
//            return response_json(402,trans('app.youdonotsendthesms'));
//        }
                if (!$record || $request->input('code') != $record->code) {
                    return response_json(402, trans('app.codeValidateFail'));
                }
                //$user = Auth('api')->user();
                $record->status = EmsCode::STATUS_VERIFYED;
                $record->save();


            }//end 万能验证码


            $user->phone_number = $phone;
            $user->area = $area;
            $user->phone_status = 2;
            $result = $user->save();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("APP resetPhone fail:" . $exception);
            return response_json(402, trans('app.regFail'));
        }
        if ($result) {
            DB::commit();
            return response_json(200, trans('app.phoneResetSuccess'));
        }
        return response_json(402, trans('app.phoneResetFail'));
    }

    // 验证验证码
    public function verifyCode(Request $request)
    {

        $id = Auth('api')->id();
        $user = Auth('api')->user();

        $validator = Validator::make($request->all(), [
            'type' => 'required|int',
            'code' => 'required|string|min:6|max:6',
            'email' => 'required|string|email',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $type = $request->input('type', 1);
        $email = $request->input('email', 1);
        $record = MailCode::where('email', $user->email)
            ->where('status', MailCode::STATUS_UNVERIFY)
            //->where('verify_status', MailCode::STATUS_VERIFYED)
            ->where('type', $type)
            ->where('email', $email)
            ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
            ->orderBy('updated_at', 'desc')
            ->first();
        if (!$record || $request->input('code') != $record->code) {
            return response_json(402, trans('app.codeValidateFail'));
        }
        MailCode::destroy($record->id);
        return response_json(200, trans('app.success'));

    }


    /**
     * 2.5重置邮箱
     * -该接口需要调用2.6手机发送验证码type为5
     * -该接口需要调用2.8邮箱发送验证码type为6
     * -该接口需要调用2.18先验证验证码：重置手机号码或者邮箱号码
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |code |是  |string |邮箱验证码   |1为手机验证码（sendmsg  的type为5）,2为邮箱验证码（sendemail  的type为6）
     * |ccode |是  |string |上一步的手机验证码   |
     * |new_email |是  |string |新的邮箱   |
     * |返回示例|
     * |:-----  |
     *
     * ```
     * {
     * "code": 200,
     * "msg": "email Reset Successfully",
     * "data": []
     * }
     * ```
     *
     *
     */
    public function resetEmail(Request $request)
    {

        $user = Auth('api')->user();

        $messages = [
            'new_email.unique' => trans('app.emailAllreadyExit'),
        ];
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:6|max:6',
//            'ccode' => 'required|string|min:6|max:6',
            'new_email' => 'required|string|unique:users,email',
//            'pin' => 'required|string|min:6|max:100',
        ], $messages);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }

        $new_email = $request->input("new_email");
        //校验邮箱是否已经注册
        $userInfo = User::where(['email'=>$new_email])->first();;
        if (!empty($userInfo)) {
            return response_json(402, trans('app.theMailboxHasBeenRegistered'));
        }
//        $pin = $request->input("pin");

        try {

            DB::beginTransaction();

            $powerc = config('app.powerc');
            //start万能验证码
            $powercdie = config('app.powercdie');
            if ($request->input('code') !== $powerc || $powercdie == 1) {

//                if (empty($user->pin)) {
//                    DB::rollBack();
//                    return response_json(402, trans('app.paymentPasswordNotSet'));
//                } else if (!Hash::check(think_md5($pin), $user->pin)) {
//                    DB::rollBack();
//                    return response_json(402, trans('app.oldPinError'));
//                }
//                $record = MailCode::where('email', $user->email)
//                    ->where('status', EmsCode::STATUS_UNVERIFY)
//                    // ->where('verify_status', EmsCode::STATUS_VERIFYED)
//                    ->where('type', EmsCode::TYPE_UPDATEPROFILE)
//                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
//                    ->orderBy('updated_at', 'desc')
//                    ->first();
//                if (!$record || $request->input('ccode') != $record->code) {
//                    DB::rollBack();
//                    return response_json(402, trans('app.codeValidateFail'), array(
//                        'error' => 0
//                    ));
//                }
                $mailCode = MailCode::where('email', $request->input('new_email'))
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('type', MailCode::TYPE_RESET_EMAIL)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    //->where('verify_status', MailCode::STATUS_VERIFYED)
                    ->orderBy('expire_time', 'desc')
                    ->first();
                if (!$mailCode || $request->input('code') != $mailCode->code) {
                    DB::rollBack();
                    return response_json(402, trans('app.codeValidateFail'), array(
                        'error' => 1
                    ));
                }
                $mailCode->status = MailCode::STATUS_VERIFYED;
                $mailCode->save();

//                $record->status = EmsCode::STATUS_VERIFYED;
//                $record->save();

            }//end 万能验证码
            // 如果用户没设置邮件,则用新的邮件昵称
//            if ($user->username == $user->email) {
//                $user->username = $new_email;
//            }
            $user->email = $new_email;
            $user->email_status = 2;
            $user->email_pin_error = 0;
            $result = $user->save();

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("APP resetEmail fail:" . $exception);
            return response_json(402, trans('app.emailResetFail'));
        }
        if ($result) {
            DB::commit();
            return response_json(200, trans('app.emailResetSuccess'));
        } else {
            DB::rollBack();
            return response_json(402, trans('app.emailResetFail'));
        }

    }


    /**
     * 2.7注册邮箱认证
     * -该接口需要调用2.8邮箱发送验证码type为3
     * -请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   ||
     * |code |是  |string |验证码   |
     * |email |是  |email |邮箱   |
     *|lang |否  |string |语言类型  |  cn 中文,en 英文,后面可能会加：fa泰文,es西班牙语,fr法国，hk繁体，kr韩语，ru俄罗斯语，de德语，vn越南语，tr土耳其语，nl荷兰语，pt葡萄牙语，it意大利语,pl波兰语
     * |返回示例|
     * |:-----  |
     *
     * ```
     * {
     * "code": 200,
     * "msg": "The email was sent successfully.<br>please check it",
     * "data": []
     * }
     * ```
     *
     *
     */
    public function verifyMail(Request $request)
    {
//        $a = Hash::check(think_md5('55f3c6ed016b72fa1591134304a47cedc5ba7f80'), '$2y$10$lTgILBJP3ZW1lX6JKBJE5.AjbWLC8TtDYNhsVaz1lfo0dKBAnnyYG');
//      $a = Hash::check(think_md5('50cf5a049abfd95489b687f1cdea727b01bab162'), '$2y$10$s8LHJuiPgRq84OSFv5RlEe9l75nfgoffok48pM0v9cf3dHOzYH7qy');
//dd($a);
//         $a = $this->reggift(886,38357,12345);
//        dd($a);

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'code' => 'required',
            'lang' => 'nullable|string|min:1|max:6',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = User::where('email', $request->input('email'))->first();
//        if($user->area!=='86'){
//            echo "sadfasfda";
//            dd($user->area);
//        }else{
//            dd($user->area);
//        }
        if (empty($user)) {
            return response_json(402, trans('app.emailNotVerify'));
        }
        if ($user->email_status == 2) {
            return response_json(402, trans('app.emailalreadyverify'));
        }
        $code = $request->code;
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        $powerc = config('app.powerc');

        $returnMsg = trans('app.emailVerifySuccess');
        $powercdie = config('app.powercdie');
        if ($request->input('code') !== $powerc || $powercdie == 1) {
            //start万能验证码
//        }else{
            $mail = MailCode::where('email', $request->input('email'))
                ->where('code', $request->input('code'))
                ->where('status', MailCode::STATUS_UNVERIFY)
                ->where('type', MailCode::TYPE_VERIFYEMAIL)
                ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                ->first();
            if (!$mail) {
                return response_json(402, trans('app.codeUndefined'));
            }
            $mail->status = 1;
            $mail->save();


        }//end 万能验证码

        $user->email_status = 2;
        $rst = $user->save();
        if ($user->is_reggift !== 1 && $user->phone_status == 2 && $user->area !== '86') {  ////不可能给国内用户送金

            $a = $this->reggift($user->area, $user->id, $code);
//                dump($a);
            if ($a['code'] == 200) {
                $user->is_reggift = 1;
                $user->save();
                $returnMsg = trans('app.emailreggiftsuccess');
            }
        }
//        dump(strtotime($user->created_at));
//        dump($user);
        //双重认证后才赠送rpzx
        $isSendRecommanderGift = 1;
        if (strtotime($user->created_at) > 1561615881) {   //当前该被推荐人应该要在2019-06-27 14:11:21之后注册才会送
            $ruid = $user->recommender_id;
            $originRecommendUser = User::where('id', $ruid)->first();
//            dd($user);
            if (!empty($ruid) && $user->is_recommgit == 0 && $user->is_recharge_btc == 1 && $user->phone_status == 2 && $originRecommendUser->email_status == 2 && $originRecommendUser->phone_status == 2) {
//                dump($ruid);
                $type = 23;
                $give_fee = 50;
                $orderSn = create_order_sn('RG');
//                $originRecommendUser = User::where('id',$ruid)->first();
                if (!empty($originRecommendUser)) {
                    $give = GiveRpzx::regGiveRpzx($originRecommendUser, $orderSn, $give_fee, '', $type);
                    if ($give) {
                        $user->is_recommgit = 1;
                        $user->save();
                        $returnMsg = trans('app.emailrecommgiftsuccess');
                        $isSendRecommanderGift = 2;
                    }
                }
            }
        }


        //推荐人双重认证后才赠送rpzx
        if ($isSendRecommanderGift == 1) {
            //if (strtotime($user->created_at)>1560334710){
            //查这个人有没有推荐了别人而且 那个被推荐人还没拿到奖励的
            $beRecommanders = User::where('recommender_id', $user->id)->where('phone_status', 2)->where('email_status', 2)->where('is_recommgit', 0)->where('is_recharge_btc', 1)->get();

//                dump($beRecommanders->isEmpty());

            if (!$beRecommanders->isEmpty()) {
                $tempuser = '';
                foreach ($beRecommanders as $k => $v) {
                    $ruid = $user->id;
                    //推荐人是否已经认证过了


                    if (!empty($ruid) && $v->is_recommgit == 0 && $v->is_recharge_btc == 1) {
                        $type = 23;
                        $give_fee = 50;
                        $orderSn = create_order_sn('RG');
                        $originRecommendUser = User::where('id', $v->id)->first();
                        if (!empty($originRecommendUser) && strtotime($v->created_at) > 1561615881) {  //2019-06-27 14:11:21被推荐人要在这个时间点注册才有效
                            $give = GiveRpzx::regGiveRpzx($user, $orderSn, $give_fee, '', $type);
                            if ($give) {
                                //更改当前这个被推荐人 为已认证用户

                                $originRecommendUser->is_recommgit = 1;
                                $originRecommendUser->save();
                                $returnMsg = trans('app.Recommanderphonerecommgiftsuccess');
                                $isSendRecommanderGift = 2;
                                $tempuser .= $v->username . " ";
                            }
                        }
                    }
                }//end foreach
                if ($isSendRecommanderGift == 2) {
                    $returnMsg = trans('app.Recommanderphonerecommgiftsuccess') . " " . $tempuser;
                }
            }  //看看他有没有推荐人还没双重认证的

            // }
        }


        if ($rst) {
            return response_json(200, $returnMsg);
        } else {
            return response_json(402, trans('app.emailVerifyFail'));
        }

    }

    /**
     * 2.19注册手机号码认证
     * -该接口需要调用2.6手机发送验证码type为3
     * -请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |code |是  |string |验证码   |
     * |phone |是  |numeric |手机号码   |
     * |area |是  |string |区号   |
     *|lang |否  |string |语言类型  |  cn 中文,en 英文,后面可能会加：fa泰文,es西班牙语,fr法国，hk繁体，kr韩语，ru俄罗斯语，de德语，vn越南语，tr土耳其语，nl荷兰语，pt葡萄牙语，it意大利语,pl波兰语
     * |返回示例|
     * |:-----  |
     *
     * ```
     * {
     * "code": 200,
     * "msg": "电话号码认证成功"
     * }
     * ```
     *
     *
     */
    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'phone' => 'required|string|numeric',
            'area' => 'required|string',
            'lang' => 'nullable|string|min:1|max:5',

        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //$phone = str_replace($request->input('area'), '', $request->input('phone'));
        $phone = $request->input('phone');
        $phone = str_replace(' ', '', $phone);
        //台湾地区以0开头的手机号码，发送短信需要去掉
        $firstm = substr($phone, 0, 1);
        if ($firstm == 0) {
            $phone = substr($phone, 1);
        }
        $area = str_replace('+', '', $request->input('area'));
        $area = str_replace(' ', '', $area);

        $user = User::where('phone_number', $phone)->first();
        if (empty($user)) {
            return response_json(402, trans('app.phoneStringGetUserError'));
        }
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        if ($user->phone_status == 2) {
            return response_json(402, trans('app.phonealreadyverify'));
        }
        $powerc = config('app.powerc');
        $code = $request->code;
        $returnMsg = trans('app.phoneVerifySuccessfully');
        //start不是万能验证码
//        if ($request->input('code')!==$powerc) {
        $powercdie = config('app.powercdie');
        if ($request->input('code') !== $powerc || $powercdie == 1) {
            $record = EmsCode::where('mobile', $area . $phone)
                ->where('code', $request->input('code'))
                ->where('status', EmsCode::STATUS_UNVERIFY)
                //->where('verify_status', EmsCode::STATUS_VERIFYED)
                ->where('type', EmsCode::TYPE_VERIFYPHONE)
                ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                ->orderBy('updated_at', 'desc')
                ->first();
            if (!$record) {
                return response_json(402, trans('app.codeUndefined'));
            }
            $record->status = 1;
            $record->save();


        }//end 万能验证码
        $user->phone_status = 2;
        $rst = $user->save();


        if ($user->is_reggift !== 1 && $user->email_status == 2 && $user->area !== '86') {   //不可能给国内用户送金
            $a = $this->reggift($user->area, $user->id, $code);
//            dump($a);
            if ($a['code'] == 200) {
                $user->is_reggift = 1;
                $user->save();
                $returnMsg = trans('app.phonereggiftsuccess');
            }
        }


        //被推荐人双重认证后才赠送rpzx
        $isSendRecommanderGift = 1;
//        if (strtotime($user->created_at)>1560334710){
        if (strtotime($user->created_at) > 1561615881) {   //当前该被推荐人应该要在2019-06-27 14:11:21之后注册才会送
            $ruid = $user->recommender_id;
            //推荐人是否已经认证过了
            $originRecommendUser = User::where('id', $ruid)->first();

            if (!empty($ruid) && $user->is_recommgit == 0 && $user->is_recharge_btc == 1 && $user->email_status == 2 && $originRecommendUser->email_status == 2 && $originRecommendUser->phone_status == 2) {
                $type = 23;
                $give_fee = 50;
                $orderSn = create_order_sn('RG');
//                $originRecommendUser = User::where('id',$ruid)->first();
                if (!empty($originRecommendUser)) {
                    $give = GiveRpzx::regGiveRpzx($originRecommendUser, $orderSn, $give_fee, '', $type);
                    if ($give) {
                        $user->is_recommgit = 1;
                        $user->save();
                        $returnMsg = trans('app.phonerecommgiftsuccess');
                        $isSendRecommanderGift = 2;
                    }
                }
            }
        }

        //推荐人双重认证后才赠送rpzx
        if ($isSendRecommanderGift == 1) {
//            if (strtotime($user->created_at)>1560334710){
            //查这个人有没有推荐了别人而且 那个被推荐人还没拿到奖励的
            $beRecommanders = User::where('recommender_id', $user->id)->where('phone_status', 2)->where('email_status', 2)->where('is_recommgit', 0)->where('is_recharge_btc', 1)->get();

//                dump($beRecommanders->isEmpty());

            if (!$beRecommanders->isEmpty()) {
                $tempuser = '';
                foreach ($beRecommanders as $k => $v) {
                    $ruid = $user->id;
                    //推荐人是否已经认证过了


                    if (!empty($ruid) && $v->is_recommgit == 0 && $v->is_recharge_btc == 1 && strtotime($v->created_at) > 1561615881) {  //2019-06-27 14:11:21被推荐人要在这个时间点注册才有效
                        $type = 23;
                        $give_fee = 50;
                        $orderSn = create_order_sn('RG');
                        $originRecommendUser = User::where('id', $v->id)->first();
                        if (!empty($originRecommendUser)) {
                            $give = GiveRpzx::regGiveRpzx($user, $orderSn, $give_fee, '', $type);
                            if ($give) {
                                //更改当前这个被推荐人 为已认证用户

                                $originRecommendUser->is_recommgit = 1;
                                $originRecommendUser->save();
                                $returnMsg = trans('app.Recommanderphonerecommgiftsuccess');
                                $isSendRecommanderGift = 2;
                                $tempuser .= $v->username . " ";
                            }
                        }
                    }
                }//end foreach
                if ($isSendRecommanderGift == 2) {
                    $returnMsg = trans('app.Recommanderphonerecommgiftsuccess') . " " . $tempuser;
                }
            }  //看看他有没有推荐人还没双重认证的

//            }
        }

        if ($rst) {
            return response_json(200, $returnMsg);
        } else {
            return response_json(402, trans('app.phoneVerifyFail'));
        }

    }


    /**
     * 2.8 邮箱发送验证码
     *
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |否  |string |登录token   |
     * |type |是  |string |邮箱验证码类型 |
     * |email |是  |string |邮箱 |  | 1忘记pin码 2忘记密码 3注册 4修改 pos账号密码 5修改个人资料(重置手机号码) 6重置邮箱 7 认证邮箱 8 设置pin密码
     * |lang |否  |string |语言类型  |  cn 中文,en 英文,后面可能会加：fa泰文,es西班牙语,fr法国，hk繁体，kr韩语，ru俄罗斯语，de德语，vn越南语，tr土耳其语，nl荷兰语，pt葡萄牙语，it意大利语,pl波兰语
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
        $verify_status = 0;
        $email = $request->post('email');
        if (!in_array($email, ['1509760688@qq.com', '229659522@qq.com', '627296936@qq.com', '15113993102@163.com', '181229184@qq.com', '1262638533@qq.com', '810045253@qq.com'])) {
            $messages = [
                'captcha.required' => trans('app.captchaisrequire'),
                'key.required' => trans('app.captchaisrequire'),
            ];
            $validator = Validator::make($request->all(), [
                'type' => 'required|string',
                'email' => 'required|string',
                'lang' => 'nullable|string|min:1|max:5',
                'captcha' => 'nullable|string',
                'key' => 'nullable|string',
            ], $messages);

            if ($validator->fails()) {
                return response_json(401, $validator->errors()->first());
            }
        }
        if (Auth::guard('api')->user()) {
            $defaultLang = Auth::guard('api')->user()->language;
        } else {
            $defaultLang = 'cn';
        }
        $language = empty($request->lang) ? $defaultLang : $request->lang;


        \App::setLocale($language);
        if ($request->input('type') == 1) {
            $types = MailCode::TYPE_FORGET_PIN;
        } elseif ($request->input('type') == 2) {
            $types = MailCode::TYPE_FORGET_PASSWORD; // 登陆密码
        } elseif ($request->input('type') == 3) {
            $types = MailCode::TYPE_REG;
            if (!in_array($email, ['1509760688@qq.com', '229659522@qq.com', '627296936@qq.com', '2723799321@qq.com', '15113993183@qq.com', '15113993100@163.com', '15113993102@163.com', '181229184@qq.com', '1262638533@qq.com', '810045253@qq.com'])) {
                if (empty($request->captcha) || empty($request->key)) {
                    return response_json(402, trans('app.captchaisrequire'));
                }
                $ischeckimg = $this->emailcheckimg($request->key, $request->captcha);
                if (!$ischeckimg) {
                    return response_json(402, trans('app.verifyFailPleaseRetry'));
                }
            }
        } elseif ($request->input('type') == 4) {
            $types = MailCode::TYPE_CHANGE_POS;
        } elseif ($request->input('type') == 5) {
            $types = MailCode::TYPE_MODIFY_PROFILE;
        } elseif ($request->input('type') == 6) {
            $types = MailCode::TYPE_RESET_EMAIL;
        } elseif ($request->input('type') == 7) {
            $types = MailCode::TYPE_VERIFYEMAIL;
        } elseif ($request->input('type') == 8) {
            $types = MailCode::SET_PIN;
        } else {
            return response_json(402, trans('app.emailUndefineType'));
        }
        $email = $request->input("email");

        //验证邮箱号
        if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
            return response_json(402, trans('web.emailFormatError'));
        }

        //非注册操作都应该验证该郵箱是否注册过
        if ($request->input('type') != 3 && $request->input('type') != 6) {
            $user = User::select("id")->where('email', $email)->first();
            if (empty($user)) {
                return response_json(402, trans('app.emailNotRegister'));
            } else {
                $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                    ->where('user_id', $user->id)
                    ->where('type', $types)
                    ->where('email', $email)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->count('id');
                $userid = $user->id;
            }
        } elseif ($request->input('type') == 3) {
            $user = User::select("id")->where('email', $email)->first();
            if (!empty($user)) {
                return response_json(402, trans('app.reg_email_allready_registed'));
            } else {
                $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                    ->where('type', $types)
                    ->where('email', $email)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->count('id');
                $userid = '';
            }
        } elseif ($request->input('type') == 6) {
            $user = User::select("id")->where('email', $email)->first();
            if (!empty($user)) {
                return response_json(402, trans('app.reg_email_allready_registed'));
            } else {
                $record = MailCode::where('status', MailCode::TYPE_RESET_EMAIL)
                    ->where('type', $types)
                    ->where('email', $email)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->count('id');
                $userid = '';
            }
        } else {
            $record = MailCode::where('status', MailCode::STATUS_UNVERIFY)
                ->where('type', $types)
                ->where('email', $email)
                ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                ->count('id');
            $userid = '';
        }

        // TODO TODO TODO
        if ($record >= 3) {
            return response_json(402, trans('app.emailSendTooQuickly'));
        }

        $code = rand(100000, 999999);
        // $code = 123456;
        $mailCode = new MailCode();
        $mailCode->user_id = $userid;
        $mailCode->email = $email;
        $mailCode->expire_time = date('Y-m-d H:i:s', time() + 300);
        $mailCode->code = $code;
        $mailCode->verify_status = $verify_status;
        $mailCode->type = $types;
        $mailCode->status = MailCode::STATUS_UNVERIFY;
        $mailCode->save();

        SendEmailCode::dispatch($mailCode)->onQueue('mail');

        return response_json(200, trans('app.emailSendSuccess'));

    }

    /**
     * 2.6手机发送验证码
     *
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |否  |string |登录token   |
     * |area |是  |string |地区编号   |
     * |mobile |是  |string |手机号码   |
     * |type |是  |string |手机验证码类型   |1忘记pin码    2忘记密码    3注册认证手机号码   4重置手机号码  5修改个人资料（修重置邮箱），
     * |lang |否  |string |语言类型  |  cn 中文,en 英文,后面可能会加：fa泰文,es西班牙语,fr法国，hk繁体，kr韩语，ru俄罗斯语，de德语，vn越南语，tr土耳其语，nl荷兰语，pt葡萄牙语，it意大利语,pl波兰语
     * |返回示例|
     * |:-----  |
     *```
     *{
     *    "code": 200,
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
        $messages = [
            'captcha.required' => trans('app.captchaisrequire'),
            'key.required' => trans('app.captchaisrequire'),
        ];
        $validator = Validator::make($request->all(), [
            'area' => 'required|string|min:1|max:20',
            'mobile' => 'required|string|min:6',
            'type' => 'required|string|min:1|max:5',
            'lang' => 'nullable|string|min:1|max:5',
            'captcha' => 'nullable|string',
            'key' => 'nullable|string',
        ], $messages);
        if (Auth::guard('api')->user()) {
            $defaultLang = Auth::guard('api')->user()->language;
        } else {
            $defaultLang = 'cn';
        }

        $language = empty($request->lang) ? $defaultLang : $request->lang;
        \App::setLocale($language);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        if ($request->input('type') == 1) {
            $types = EmsCode::TYPE_FORGET_PIN;
        } elseif ($request->input('type') == 2) {
            $types = EmsCode::TYPE_FORGET_PASSWORD;
        } elseif ($request->input('type') == 3) {
            if (empty($request->captcha) || empty($request->key)) {
                return response_json(402, trans('app.captchaisrequire'));
            }
            $version = $request->header('Version', 1.18);
            $version = floatval($version);

            if ($version > 1.18) {
                $ischeckimg = $this->phonecheckimg($request->key, $request->captcha);
                if (!$ischeckimg) {
                    return response_json(402, trans('app.verifyFailPleaseRetry'));
                }
            } else {
                if (captcha_api_check(trim($request->captcha), trim($request->key))) {
                    $captchas = Captcha::where('key', trim($request->key))->where('code', trim($request->captcha))->first();
//            dump(trim($request->key));
//                dump(trim($request->captcha));
//                dd($captchas);
                    if (empty($captchas)) {
                        return response_json(402, trans('app.captchaiswrong'));
                    } else {
                        if ($captchas->mobile_captcha == 0) {
                            $captchas->mobile_captcha = 1;
                            $captchas->save();
                        } else {
                            return response_json(402, trans('app.captchaallreadyverify'));
                        }
                    }
                } else {
                    return response_json(402, trans('app.captchaiswrong'));
                }
            }

            $types = EmsCode::TYPE_REGISTER;
        } elseif ($request->input('type') == 4) {
            $types = EmsCode::TYPE_RESETPHONE;
        } elseif ($request->input('type') == 5) {
            $types = EmsCode::TYPE_UPDATEPROFILE;
        } elseif ($request->input('type') == 7) {
            $types = EmsCode::TYPE_VERIFYPHONE;
        } else {
            return response_json(402, trans('app.phoneUndefineType'));
        }
        //$phone = str_replace($request->input('area'), '', $request->input('mobile'));
        $phone = $request->input('mobile');
        $phone = str_replace(' ', '', $phone);
        $phoneTrue = str_replace(' ', '', $phone);
        //台湾地区以0开头的手机号码，发送短信需要去掉
        $firstm = substr($phone, 0, 1);
        if ($firstm == 0) {
            $phone = substr($phone, 1);
        }
        $area = str_replace('+', '', $request->input('area'));
        $areaTrue = str_replace(' ', '', $area);
        if ($areaTrue == '86') {
            $lang = 'cn';
        } elseif ($areaTrue == '886' || $areaTrue == '852') {   //繁体
            $area = "00" . $areaTrue;
            $lang = 'hk';
        } else {     //英文
            $area = "00" . $areaTrue;
            $lang = 'sg';
        }
        //台湾地区以0开头的手机号码，发送短信需要去掉
        $firstm = substr($phone, 0, 1);
        if ($firstm == 0) {
            $phoneNoZero = substr($phone, 1);
        } else {
            $phoneNoZero = $phone;
        }

        $mobile = $area . $phoneNoZero;

        //非注册操作都应该验证该手机号码是否注册过

        if ($request->input('type') != 3 && $request->input('type') != 4 && $request->input('type') != 5) {
            $user = User::where('phone_number', $phone)
                ->where('area', $areaTrue)
                ->first();

            if (empty($user)) {
                return response_json(402, trans('app.phoneNumberNotFound'));
            } else {
                if ($areaTrue == '62') {
                    $result = Monyunems::singleSend($phoneTrue, $areaTrue, $types, '', $language);
                } else {
                    $result = Ems::singleSend($mobile, $lang, $types, $user->id, $language);
                }
            }
        } elseif ($request->input('type') == 3) {
            $user = User::where('phone_number', $phone)
                ->where('area', $areaTrue)
                ->first();
            if (!empty($user)) {
                return response_json(402, trans('app.reg_phone_allready_registed'));
            } else {
                //印尼62要单独发送手机验证码
                if ($areaTrue == '62') {
                    $result = Monyunems::singleSend($phoneTrue, $areaTrue, $types, '', $language);
                } else {
                    $result = Ems::singleSend($mobile, $lang, $types, '', $language);
                }
            }
        } else {
            //印尼62要单独发送手机验证码
            if ($areaTrue == '62') {
                $result = Monyunems::singleSend($phoneTrue, $areaTrue, $types, '', $language);
            } else {
                $result = Ems::singleSend($mobile, $lang, $types, '', $language);
            }
        }

        return json_decode($result)->result ? response_json(200, trans('app.emsSendSuccess')) : response_json(402, trans('app.emsSendFail'));

    }


    /**
     *  2.9屏锁密码认证
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |lock_pin |是  |string |屏幕锁密码   |
     * |返回示例|
     * |:-----  |
     *
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *
     **/
    public function locklogin(Request $request)
    {

        // Auth::guard('api')->onceUsingId($this->auth->manager()->getPayloadFactory()->buildClaimsCollection()->toPlainArray()['sub']);

        $validator = Validator::make($request->all(), [
            'lock_pin' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = Auth('api')->user();
        if (!Hash::check(think_md5($request->input('lock_pin')), $user->lock_pin)) {
            return response_json(403, trans('loginFail'));
        }
        return response_json(200, trans('loginSuccess'));
    }

    /**
     * 2.10个人生成分享链接和分享二维码
     *
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |:-----   |
     * |token |是  |string |登录token   |
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *
     * ```
     * {
     * "code": 200,
     * "msg": "成功",
     * "data": {
     * "qrcode": "http://tradepost.com/qrcodes/ recommend/4PSUHR795Z95RDPNNQ8AQG86SVA52LK8.png",
     * "link": "http://tradepost.com/api/auth/ register?recommendcode=4PSUHR795Z95RDPNNQ8AQG86SVA52LK8"
     * }
     * }
     * ```
     */
    public function genRecommend(Request $request)
    {
        $uid = Auth::guard('api')->user()->id;
        $uname = Auth::guard('api')->user()->username;
        if (empty($uid)) {
            Log::error("methodLoginButNotLogin.JWTWrong,客户端发过来的token是：" . $request->input('token'));
            return response_json(403, '未登录');
        }
        $recommend = new AppRecommend;
        $recommendcode = randomkeys(32);
        $recommend->recommend_code = $recommendcode;
        $recommend->recommend_id = $uid;
        $recommend->recommender = $uname;
        $recommend->save();

        $rid = $recommend->id;
        $register = url('api/auth/register?recommendcode=' . $recommendcode);
        \QrCode::format('png')->size(295)->generate($register, public_path('qrcodes/recommend/' . $recommendcode . '.png'));
        $path = asset("/qrcodes/recommend/" . $recommendcode . ".png");
        //$path = App::make('url')->to('/').'/public/'.'qrcodes/recommend/'.$recommendcode.'.png';
        return response_json(200, '成功', ['qrcode' => $path, 'link' => $register]);

    }


    /**
     * 2.11 设置支付密码 / 忘记支付密码
     * 每次登陆都重新设置PIN，
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |new_pin |是  |string |支付密码   |
     * |返回示例|
     * |:-----  |
     *
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *
     *```
     *{
     *    "code": 200,
     *    "msg": "Successfully modified the pin code",
     *    "data": []
     *}
     *```
     */
    public function setPin(Request $request)
    {

        $user = Auth('api')->user();

        if (empty($user->pin)) {
            // pin_error 超三次只能忘记, 不能修改
            if ($user->pin_error >= 3) {
                return response_json(403, trans('web.pinErrorExceedThreeTimes'));
            }
            $validator = Validator::make($request->all(), [
                'new_pin' => 'required|string|min:6',
                'r_new_pin' => 'required|string|min:6',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|min:6',
                'new_pin' => 'required|string|min:6',
                'r_new_pin' => 'required|string|min:6',
            ]);
        }

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        if ($user->email_status == 1) {//邮箱未认证
            return response_json(402, trans('app.emailNotValify'), ['email_status' => 1]);
        }
        if ($request->input('new_pin') != $request->input('r_new_pin')) {
            return response_json(402, trans('app.pinConfirmError'));
        }

        // $code
        if (!empty($user->pin)) {
            $code = MailCode::select("id", "code", "status")
                ->where('email', $user->email)
                ->where('expire_time', '>', date('Y-m-d H:i:s'))
                ->where('type', MailCode::SET_PIN)
                ->where('status', MailCode::STATUS_UNVERIFY)
                ->orderBy('expire_time', 'desc')
                ->first();
            if (empty($code)) {
                return response_json(402, trans('app.youdonotsendthesms'));
            } else {
                if ($request->input('code') != $code->code) {
                    return response_json(402, trans('app.codeUndefined'));
                }
            }
            $code->status = 1;
            $code->save();
        }

        $user->pin = bcrypt(think_md5($request->input('new_pin')));
        $user->pin_error = 0;
        $user->email_pin_error = 0;
        $user->save();

        return response_json(200, trans('app.setPinSucess'));

    }

    /**
     * 2.12设置指纹登录|人脸登录
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |fingerprint |否  |string |用户指纹   |required_without:facelogin
     * |facelogin |否  |string |用户人脸   |required_without:fingerprint
     * |identify |是  |string |指纹支付关联设备号   |
     * |p |是  |string |type為1p为登录密码，type=2,p为支付密码   |
     * |type |是  |string |操作类型   |type=1为开启指纹登录，type=2为开启指纹支付，type=3删除指纹,关闭指纹登录关闭指纹支付，type=4 为关闭指纹登录，type=5为关闭指纹支付
     * |返回示例|
     * |:-----  |
     *
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
    public function setFingerLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fingerprint' => 'string|nullable',
            'facelogin' => 'string|nullable',
            'type' => 'string|nullable',
            'facetype' => 'string|nullable',
            'identify' => 'string|nullable',
            'p' => 'string|required',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = auth('api')->user();

        if (empty($user)) {
            return response_json(402, trans('app.userNotLogin'));
        }
        if ($request->input('type') == 1) {
            if (!Hash::check(think_md5($request->input('p')), $user->password)) {
                return response_json(402, trans('app.pinError'));
            }
        } elseif ($request->input('type') == 2) {
            if (!Hash::check(think_md5($request->input('p')), $user->pin)) {
                return response_json(402, trans('app.pinError'));
            }
        }
        if ($request->input('facetype') == 1) {
            if (!Hash::check(think_md5($request->input('p')), $user->password)) {
                return response_json(402, trans('app.pinError'));
            }
        } elseif ($request->input('facetype') == 2) {
            if (!Hash::check(think_md5($request->input('p')), $user->pin)) {
                return response_json(402, trans('app.pinError'));
            }
        }
        $result = [];
        //指纹
        if (!empty($request->input('type'))) {
            if ($request->input('type') == 1 && $request->input('fingerprint') && $request->input('identify')) {  //type=1为开启指纹登录
                if ($request->input('fingerprint')) {
                    $user->fingerprint = bcrypt(think_md5($request->input('fingerprint')));
                    $user->fingerprint_login_status = 2;
                    $user->fingerprint_id = $request->input('identify');
                    $result = $user->save();
                    return response_json(200, trans('app.fingerprintLoginSetSucess'));
                } else {
                    return response_json(402, trans('app.fingerprintLoginSetError'));
                }
            } elseif ($request->input('type') == 2 && $request->input('fingerprint') && $request->input('identify')) {  //type=2为开启指纹支付
                if ($request->input('fingerprint')) {
                    Log::useFiles(storage_path('fingerprint.log'));
                    Log::info('input:', $request->all());
                    $user->fingerprintpay = bcrypt(think_md5($request->input('fingerprint')));
                    $user->fingerprint_pay_status = 2;
                    $user->fingerprint_id = $request->input('identify');
                    $user->finger_pay_error = 0;//重置指纹支付失败次数
                    $result = $user->save();
                    return response_json(200, trans('app.fingerprintPaySetSuccess'));
                } else {
                    return response_json(402, trans('app.fingerprintPaySetError'));
                }
            } elseif ($request->input('type') == 3) {  //type=3删除指纹,关闭指纹登录关闭指纹支付
                // if ($request->input('fingerprint')) {
                $user->fingerprintpay = '';
                $user->fingerprint = '';
                $user->fingerprint_pay_status = 1;
                $user->fingerprint_login_status = 1;
                $user->finger_pay_error = 0;//重置指纹支付失败次数
                $user->fingerprint_id = '';
                $result = $user->save();
                return response_json(200, trans('app.delFingerSucess'));
                // }else{
                //     return response_json(402,trans('app.delFingerFail'));
                // }
            } elseif ($request->input('type') == 4) {  //type=4为关闭指纹登录
                //if ($request->input('fingerprint')) {
                // $user->fingerprint = bcrypt(think_md5($request->input('fingerprint')));
                $user->fingerprint_login_status = 1;
                //$user->fingerprint_id = $request->input('identify');
                $result = $user->save();
                return response_json(200, trans('app.closeFingerLoginSucess'));
                // }else{
                //     return response_json(402,trans('app.closeFingerLoginFail'));
                // }
            } elseif ($request->input('type') == 5) {  //type=5为关闭指纹支付
                //if ($request->input('fingerprint')) {
                // $user->fingerprint = '';
                $user->fingerprint_pay_status = 1;
                //$user->fingerprint_id = '';
                $result = $user->save();
                return response_json(200, trans('app.closeFingerPaySucess'));
                // }else{
                //     return response_json(402,trans('app.closeFingerPayFail'));
                // }
            } else {
                return response_json(402, trans('app.parameWrong'));
            }
        }


        //人脸
        if (!empty($request->input('facetype'))) {
            if ($request->input('facetype') == 1 && $request->input('face') && $request->input('identify')) {
                //facetype=1为开启人脸登录
                if ($request->input('face')) {
                    $user->facelogin = bcrypt(think_md5($request->input('face')));
                    $user->face_login_status = 2;
                    $user->fingerprint_id = $request->input('identify');
                    $result = $user->save();
                    return response_json(200, trans('app.faceLoginSetSucess'));
                } else {
                    return response_json(402, trans('app.faceLoginSetError'));
                }
            } elseif ($request->input('facetype') == 2 && $request->input('face') && $request->input('identify')) {
                //facetype=2为开启人脸支付
                if ($request->input('face')) {
                    $user->facepay = bcrypt(think_md5($request->input('face')));
                    $user->face_pay_status = 2;
                    $user->fingerprint_id = $request->input('identify');
                    $user->face_pay_error = 0; //重置支付登录失败次数
                    $result = $user->save();
                    return response_json(200, trans('app.facePaySetSucess'));
                } else {
                    return response_json(402, trans('app.facePaySetError'));
                }
            } elseif ($request->input('facetype') == 3) {
                // if ($request->input('fingerprint')) {
                $user->facepay = '';
                $user->facelogin = '';
                $user->face_pay_status = 1;
                $user->face_login_status = 1;
                $user->face_pay_error = 0; //重置支付登录失败次数
                $user->fingerprint_id = '';
                $result = $user->save();
                return response_json(200, trans('app.delFaceSucess'));
                // }else{
                //     return response_json(402,trans('app.delFaceFail'));
                // }
            } elseif ($request->input('facetype') == 4) {
                //facetype=4为关闭人脸登录
                //if ($request->input('fingerprint')) {
                // $user->fingerprint = bcrypt(think_md5($request->input('fingerprint')));
                $user->face_login_status = 1;
                //$user->fingerprint_id = $request->input('identify');
                $result = $user->save();
                return response_json(200, trans('app.closeFaceLoginSucess'));
                // }else{
                //     return response_json(402,trans('app.closeFaceLoginFail'));
                // }
            } elseif ($request->input('facetype') == 5) {
                //facetype=5为关闭指人脸支付
                //if ($request->input('fingerprint')) {
                // $user->fingerprint = '';
                $user->face_pay_status = 1;
                //$user->fingerprint_id = '';
                $result = $user->save();
                return response_json(200, trans('app.closeFacePaySucess'));
                // }else{
                //     return response_json(402,trans('app.closeFacePayFail'));
                // }
            } else {
                return response_json(402, trans('app.parameWrong'));
            }
        }


        return $result ? response_json(200, trans('app.fingerprintSetSucess')) : response_json(403, trans('app.fingerprintSetError'));
    }

    /**
     * 2.13刷新钱包地址
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |返回示例|
     * |:-----  |
     *
     * ```
     * {
     * "code": 200,
     * "msg": "The WalletAddress was sent successfully.<br>please check it",
     * "data": []
     * }
     * ```
     *
     *
     */
    public function genrateWallet(Request $request)
    {

        //注册生成比特币、莱特币、比特币现金、锐币收款地址
        $userid = Auth('api')->user()->id;

        GenerateWalletAddress::dispatch($userid)->onQueue('getnewaddress');

        // $address = new UsersWallet;
        // $address->add_address($userid);
        return response_json(200, trans('app.generateAddressSucess'));
    }

    /**
     * 2.14设置语言
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |lang |是  |ENUM |语言选择   | 用户使用语言（en英语，cn简体中文，hk繁体中文，jp日文，kr韩文，th泰文）
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

        $user = Auth('api')->user();

        $validator = Validator::make($request->all(), [
            'lang' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        if (!empty($user->language)) {
            $lang = $request->input("lang");
            if (!in_array($lang, ['cn', 'en'])) {
                return response_json(402, trans('app.changeLanguageFail'), array(
                    'error' => 0
                ));
            }
            $user->language = $lang;
            $rst = $user->save();
            if ($rst) {
                App::setLocale($request->input("lang"));
                $version = trans('android.version');
                $langlist['version'] = $version;
                $allLangList = trans('android');
                $temp = array();
                if (is_array($allLangList)) {
                    foreach ($allLangList as $k => $v) {
                        $temp[] = array(
                            'key' => $k,
                            'value' => $v
                        );
                    }
                }
                $langlist['langList'] = $temp;
                return response_json(200, trans('app.changeLanguageSucess'), $langlist);
            } else {
                return response_json(402, trans('app.changeLanguageFail'), array(
                    'error' => 1
                ));
            }
        } else {
            return response_json(405, trans('app.tokenInvalide'));
        }

    }


    /**
     * 2.15获取制定手机号码是否注册了会员
     *请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |phonestring |是  |string |手机号码用逗号隔开   | 15815844502,15915844501
     * |返回示例|
     * |:-----  |
     * ```
     * {
     * "code": 200,
     * "msg": "Phone Get User Successfully",
     * "data": [
     * {
     * "id": 2,
     * "photo": "img/defaultlogo.png",
     * "username": "15915844503",
     * "phonenumber": "15915844503"
     * }
     * ]
     * }
     * ```
     */
    public function phoneGetUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'phonestring' => 'required|string',

        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $phonestring = $request->input("phonestring");
        $phonearr = explode(",", $phonestring);

        /*
         * 查询一千个用户
         */
        /*
        $user_phone=DB::table('users')->select('phone_number')->offset(0)->limit(8000)->get();
        foreach ($user_phone as $value){
            $phonearr[]=$value->phone_number;
        }
        */
        $phones = array();
        foreach ($phonearr as $value) {
            $phones[] = $value;
            $phones[] = substr($value, -9);
            $phones[] = '0' . substr($value, -9);
            $phones[] = substr($value, -11);
        }
        //dd($phones);
        $usersArr = DB::table('users')->select('id', 'portRaitUri as photo', 'username', 'phone_number', 'is_chat')->whereIn('phone_number', $phones)->get();
        //$usersArr=DB::table('users')->select('id','portRaitUri as photo','username','phone_number','is_chat')->get();
        //dd($usersArr);
        $user_array = array();
        foreach ($usersArr as $key => $value) {
            if (in_array($value->phone_number, $phonearr)) {
                $user_array[] = $value;
            } elseif (in_array(substr($value->phone_number, -9), $phonearr)) {
                $user_array[] = $value;
            } elseif (in_array('0' . substr($value->phone_number, -9), $phonearr)) {
                $user_array[] = $value;
            } elseif (in_array(substr($value->phone_number, -11), $phonearr)) {
                $user_array[] = $value;
            }

        }
        //dd($user_array);
        if (!empty($user_array)) {
            return response_json(200, trans('app.phoneStringGetUserSuccess'), $user_array);
        } else {
            return response_json(200, trans('app.phoneStringGetUserSuccess'));
        }

    }

    public function phoneGetUser_bk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phonestring' => 'required|string',

        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $phonestring = $request->input("phonestring");
        $phonearr = explode(",", $phonestring);

        /*
         * 查询一千个用户
         */
        /*
        $user_phone=DB::table('users')->select('phone_number')->offset(0)->limit(1000)->get();
        foreach ($user_phone as $value){
            $phonearr[]=$value->phone_number;
        }
        dd(implode(",", $phonearr));
        */
        $phones = array();
        foreach ($phonearr as $value) {
            /*
            if(strlen($value)>11){
                $phones[]=substr($value,-11);
                //dd($value);
            }

            $phones[]=$value;

            if(strlen($value)<11){
                $phones[]=substr($value,-9);
                $phones[]='0'.substr($value,-9);
            }
            */
            $phones[] = $value;
            $phones[] = substr($value, -9);
            $phones[] = '0' . substr($value, -9);
            $phones[] = substr($value, -11);
        }
        //dd($phones);
        $usersArr = DB::table('users')->select('id', 'portRaitUri as photo', 'username', 'phone_number', 'is_chat')->whereIn('phone_number', $phones)->get();
        /*
        foreach ($phonearr as $key => $value) {
            $user = User::where('phone_number',$value)->first();
            if (!empty($user)) {
                $userarr['id']=$user->id;
                $userarr['photo']=$user->portRaitUri;
                $userarr['username']=$user->username;
                $userarr['phone_number']=$user->phone_number;
                $usersArr[]=$userarr;
            }

        }
        */

        foreach ($phonearr as $value) {
            foreach ($usersArr as $item) {
                if ($item->phone_number === $value) {

                } else {
                    if (substr($value, -11) === $item->phone_number) {
                        $item->phone_number = $value;
                    } else {
                        if (substr($value, -9) === $item->phone_number) {
                            $item->phone_number = $value;
                        } else {
                            if ('0' . substr($value, -9) === $item->phone_number) {
                                $item->phone_number = $value;
                            } else {

                            }
                        }
                    }

                }
            }
        }
        dd($usersArr);
        if (!empty($usersArr)) {
            return response_json(200, trans('app.phoneStringGetUserSuccess'), $usersArr);
        } else {
            return response_json(200, trans('app.phoneStringGetUserSuccess'));
        }

    }

    /**
     * 2.16获取地区列表
     * **参数：**
     * |参数名|必选|类型|说明|
     * |:----          |:---    |:-----     |-----       |
     **返回示例**
     * ```
     * {
     * "code": 200,
     * "msg": "Get Data Successful",
     * "data": [
     * {
     * "country_id": 214,
     * "country": "中国",
     * "en_country": "China",
     * "region": "86",
     * "area": "亚洲",
     * "created_at": null,
     * "updated_at": null,
     * "tw_country": "中國"
     * },
     * ]
     * }
     * ```
     **返回参数说明**
     * |参数名|类型|说明|
     * |:-----  |:-----|-----
     **/
    public function getarea()
    {
        $region = Regions::all()->toarray();
        return response_json(200, trans('app.getDataSuccess'), $region);
    }

    /**
     * 2.17重置用户私密信息验证码验证
     * **参数：**
     * |参数名|必选|类型|说明|
     * |:----          |:---    |:-----     |-----       |
     * |phone |否  |string |手机号码   |required_without:email
     * |email |否  |string |邮箱   |required_without:phone
     * |code |是  |string |验证码   |
     * |area |是  |string |地区编号   | required_without:email
     * |verifytype |是  |string |验证码类型  |1为手机验证码（sendmsg  的type为2）,2为邮箱验证码（sendemail  的type为2）
     * |返回示例|
     * |:-----  |
     * ```
     * {
     * "code": 200,
     * "msg": "验证码验证成功"
     * }
     * ```
     **备注**
     * - 修改用户信息需要验证码，该接口验证2.6手机发送验证码（该接口的type参数要为2）  或者 调用2.8邮箱发送验证码（该接口的type参数要为2） 的验证码是否正确
     * - 验证码只能在这个接口验证成功一次，第二次验证会提示验证失败
     **返回参数说明**
     * |参数名|类型|说明|
     * |:-----  |:-----|-----
     **/
    public function msgVerifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|max:100|required_without:phone',
            'phone' => 'nullable|numeric|required_without:email',
            'area' => 'nullable|string|required_without:email',
            'verifytype' => 'required|string',
            'code' => 'required|string|min:6|max:6',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        /**
         *  param int verifytype    发送什么类型的手机验证码,    //1忘记pin码      2忘记密码    //3注册认证手机号码   //4重置手机号码  //5修改个人资料   //
         */
        $powerc = config('app.powerc');
        //start万能验证码
//        if ($request->input('code')!==$powerc) {
        $powercdie = config('app.powercdie');
        if ($request->input('code') !== $powerc || $powercdie == 1) {

            if ($request->input('verifytype') == 2) {

                $user = User::where('email', $request->input('email'))->first();
                //dd($user);
                if (empty($user)) {
                    return response_json(402, trans('app.emailNotRegister'));
                }
                $record = MailCode::where('email', $request->input('email'))
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('code', $request->input('code'))
                    ->where('type', MailCode::TYPE_FORGET_PASSWORD)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->where('verify_status', MailCode::STATUS_UNVERIFY)
                    ->orderBy('expire_time', 'desc')
                    ->first();
            } else {
                //$phone = str_replace($request->input('area'), '', $request->input('phone'));
                $phone = $request->input('phone');
                $phone = str_replace(' ', '', $phone);
                //台湾地区以0开头的手机号码，发送短信需要去掉
                $firstm = substr($phone, 0, 1);
                if ($firstm == 0) {
                    $phone = substr($phone, 1);
                } else {
                    $phone = $phone;
                }
                $area = str_replace('+', '', $request->input('area'));
                $area = str_replace(' ', '', $area);

                $user = User::where('phone_number', $phone)
                    ->where('area', $area)
                    ->first();
                // dd($user);
                if (empty($user)) {
                    return response_json(402, trans('app.phoneNumberNotFound'));
                }
                $record = EmsCode::where('mobile', $area . $phone)
                    ->where('code', $request->input('code'))
                    ->where('status', EmsCode::STATUS_UNVERIFY)
                    ->where('verify_status', MailCode::STATUS_UNVERIFY)
                    ->where('type', EmsCode::TYPE_FORGET_PASSWORD)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->orderBy('updated_at', 'desc')
                    ->first();
            }

            if (!$record || $request->input('code') != $record->code) {
                return response_json(402, trans('app.codeValidateFail'));
            }
            if ($request->input('verifytype') == 2) {
                $record->verify_status = MailCode::STATUS_VERIFYED;
                $rst = $record->save();
            } else {
                $record->verify_status = EmsCode::STATUS_VERIFYED;
                $rst = $record->save();
            }


        } else {
            $rst = true;
        }//end 万能验证码


        if ($rst) {
            return response_json(200, trans('app.codeValidateSuccessfully'));
        } else {
            return response_json(402, trans('app.codeValidateFail'));
        }

    }


    /**
     * 2.18先验证验证码：重置手机号码或者邮箱号码
     * **参数：**
     * |参数名|必选|类型|说明|
     * |:----          |:---    |:-----     |-----       |
     * |Authorization  Bearer Token |是  |string |登录token   |
     * |phone |否  |string |手机号码   |required_without:email
     * |email |否  |string |邮箱   |required_without:phone
     * |code |是  |string |验证码   |
     * |area |否  |string |地区编号   | required_without:email
     * |verifytype |是  |string |验证码类型  |1为手机验证码（sendmsg  的type为2）,2为邮箱验证码（sendemail  的type为2）
     * |返回示例|
     * |:-----  |
     * ```
     * {
     * "code": 200,
     * "msg": "验证码验证成功"
     * }
     * ```
     **备注**
     * - 重置手机号码或者邮箱号码需要验证码，该接口验证2.6手机发送验证码（该接口的type参数要为2）  或者 调用2.8邮箱发送验证码（该接口的type参数要为2） 的验证码是否正确
     * - 验证码只能在这个接口验证成功一次，第二次验证会提示验证失败
     **返回参数说明**
     * |参数名|类型|说明|
     * |:-----  |:-----|-----
     **/
    public function resetphoneVerifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|max:100|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
            'area' => 'nullable|string|required_without:email',
            'verifytype' => 'required|string',
            'code' => 'required|string|min:6|max:6',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $authid = auth('api')->id();
        $authUser = Auth('api')->user();
        //商家不可以修改密码
        if ($authUser->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        /**
         *  param int verifytype    发送什么类型的手机验证码,    //1忘记pin码      2忘记密码    //3注册认证手机号码   //4重置手机号码  //5修改个人资料   //
         */


        $powerc = config('app.powerc');

        //start万能验证码
//        if ($request->input('code')!==$powerc) {
        $powercdie = config('app.powercdie');
        if ($request->input('code') !== $powerc || $powercdie == 1) {
            if ($request->input('verifytype') == 2) {

                $user = User::where('email', $request->input('email'))
                    ->where('id', $authid)
                    ->first();
                //dd($user);
                if (empty($user)) {
                    return response_json(402, trans('app.emailNotRegister'));
                }
                $record = MailCode::where('email', $request->input('email'))
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('code', $request->input('code'))
                    //->where('type', MailCode::TYPE_RESET_EMAIL)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->where('verify_status', MailCode::STATUS_UNVERIFY)
                    ->orderBy('expire_time', 'desc')
                    ->first();
            } else {
                //$phone = str_replace($request->input('area'), '', $request->input('phone'));
                $phone = $request->input('phone');
                $phone = str_replace(' ', '', $phone);
                //台湾地区以0开头的手机号码，发送短信需要去掉
                $firstm = substr($phone, 0, 1);
                if ($firstm == 0) {
                    $phone = substr($phone, 1);
                } else {
                    $phone = $phone;
                }
                $area = str_replace('+', '', $request->input('area'));
                $area = str_replace(' ', '', $area);


                $user = User::where('phone_number', $phone)
                    ->where('area', $area)
                    ->where('id', $authid)
                    ->first();

                if (empty($user)) {
                    return response_json(402, trans('app.phoneNumberNotFound'));
                }
                $record = EmsCode::where('mobile', $area . $phone)
                    ->where('code', $request->input('code'))
                    ->where('status', EmsCode::STATUS_UNVERIFY)
                    ->where('verify_status', MailCode::STATUS_UNVERIFY)
                    // ->where('type', EmsCode::TYPE_RESETPHONE)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->orderBy('updated_at', 'desc')
                    ->first();
            }

            if (!$record || $request->input('code') != $record->code) {
                return response_json(402, trans('app.codeValidateFail'));
            }
            if ($request->input('verifytype') == 2) {
                $record->verify_status = MailCode::STATUS_VERIFYED;
                $rst = $record->save();
            } else {
                $record->verify_status = EmsCode::STATUS_VERIFYED;
                $rst = $record->save();
            }


        } else {
            $rst = true;
        }//end 万能验证码


        if ($rst) {
            return response_json(200, trans('app.codeValidateSuccessfully'));
        } else {
            return response_json(402, trans('app.codeValidateFail'));
        }

    }


    /**
     *  2.21替换手机号码
     *  -调用2.8邮箱发送验证码（该接口的type参数要为5）
     *
     *
     * -请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |code |是  |string |邮箱验证码   |
     * |phone |是  |string |新的手机号码   |
     * |area |是  |string |地区编码   |
     * |email |是  |email |注册时认证的邮箱   |
     *
     * |返回示例|
     * |:-----  |
     *```
     *{
     *    "code": 200,
     *    "msg": "Phone Number Reset Successfully",
     *    "data": []
     *}
     *```
     * return code 200成功/403修改或查询数据库失败/402参数错误/401认证错误，
     *
     **/
    public function tihuanPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'area' => 'required|string|min:1|max:5',
            'code' => 'required|string|min:6|max:6',
            'phone' => 'required|string|min:6|unique:users,phone_number',
            'email' => 'nullable|string|max:50',

        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //$phone = str_replace($request->input('area'), '', $request->input('phone'));
        $phone = $request->input('phone');
        $phone = str_replace(' ', '', $phone);
        //台湾地区以0开头的手机号码，发送短信需要去掉
        $firstm = substr($phone, 0, 1);
        if ($firstm == 0) {
            $phone = substr($phone, 1);
        } else {
            $phone = $phone;
        }
        $area = str_replace('+', '', $request->input('area'));
        $area = str_replace(' ', '', $area);

        //邮箱验证码
        //if($request->input('verifytype')==2){
        $user = auth('api')->user();
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        // if ($user->email!==$request->input('email')) {
        //     return response_json(402,trans('app.emailNotReg'));
        // }
        //$user = User::where('email',$request->input('email'))->first();
        //dd($user);
        if ($user->email_status == 1) {
            return response_json(402, trans('app.emailNotVerify'));
        }
        try {
            DB::beginTransaction();
            $powerc = config('app.powerc');
            //start万能验证码
//            if ($request->input('code')!==$powerc) {
            $powercdie = config('app.powercdie');
            if ($request->input('code') !== $powerc || $powercdie == 1) {

                $mailCode = MailCode::where('email', $user->email)
                    ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                    ->where('code', $request->input('code'))
                    ->where('type', MailCode::TYPE_MODIFY_PROFILE)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->orderBy('expire_time', 'desc')
                    ->first();
                if (!$mailCode || $request->input('code') != $mailCode->code) {
                    return response_json(402, trans('app.codeValidateFail'));
                }
                $mailCode->status = MailCode::STATUS_VERIFYED;
                $mailCode->save();


            }//end 万能验证码


            $user->phone_number = $phone;
            $user->area = $area;
            $user->phone_status = 1;
            $result = $user->save();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("APP tihuanPhone fail:" . $exception);
            return response_json(402, trans('app.phoneChangeFail'));
        }
        if ($result) {
            DB::commit();
            return response_json(200, trans('app.phoneChangeSuccessfully'));
        }
        return response_json(402, trans('app.phoneChangeFail'));
    }

    /**
     * 2.22替换邮箱
     * -该接口需要调用2.6手机发送验证码type为5
     * 请求参数
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |登录token   |
     * |code |是  |string |手机验证码   |
     * |new_email |是  |string |新的邮箱   |
     * |返回示例|
     * |:-----  |
     *
     * ```
     * {
     * "code": 200,
     * "msg": "email Reset Successfully",
     * "data": []
     * }
     * ```
     *
     *
     */
    public function tihuanEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|min:6|max:6',
            'new_email' => 'required|string|unique:users,email',
            'phone' => 'nullable|string|numeric',
            'area' => 'nullable|string|',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = Auth('api')->user();
        //商家不可以修改密码
        if ($user->customer_type == 2) {
            return response_json(402, trans('app.selleraccountcannodo'));
        }
        $area = str_replace('+', '', $user->area);

        // if ($user->phone_number!==$request->input('phone')) {
        //     response_json(402,trans('app.phoneNotReg'));
        // }
        $new_email = $request->input("new_email");
        // $email_status = Auth('api')->user()->phone_status;
        if ($user->phone_status == 1) {
            return response_json(402, trans('app.phoneNotVerify'));
        }
        try {
            DB::beginTransaction();

            $powerc = config('app.powerc');
            //start万能验证码
//            if ($request->input('code')!==$powerc) {
            $powercdie = config('app.powercdie');
            if ($request->input('code') !== $powerc || $powercdie == 1) {

                $record = EmsCode::where('mobile', $area . $user->phone_number)
                    ->where('code', $request->input('code'))
                    ->where('status', EmsCode::STATUS_UNVERIFY)
                    ->where('type', EmsCode::TYPE_UPDATEPROFILE)
                    ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                    ->orderBy('updated_at', 'desc')
                    ->first();

                if (!$record || $request->input('code') != $record->code) {
                    return response_json(402, trans('app.codeValidateFail'));
                }

                $record->status = EmsCode::STATUS_VERIFYED;
                $record->save();

            }//end 万能验证码

            $user->email = $new_email;
            $user->email_status = 1;
            $user->email_pin_error = 0;
            $result = $user->save();

        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error("APP tihuanEmail fail:" . $exception);
            return response_json(402, trans('app.emailChangeFail'));
        }
        if ($result) {
            DB::commit();
            return response_json(200, trans('app.emailChangeSuccessfully'));
        } else {
            DB::rollBack();
            return response_json(402, trans('app.emailChangeFail'));
        }

    }


    /**
     * 2.23 验证登录密码
     * **参数：**
     * |参数名|必选|类型|说明|
     * |:----          |:---    |:-----     |-----       |
     * |bearer Token | 是|string|登录token
     * |password |是  |string |密码   |
     **返回示例**
     * ```
     *
     * ```
     **返回参数说明**
     * |参数名|类型|说明|
     * |:-----  |:-----|-----
     **/
    public function verifyPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|max:100',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = auth('api')->user();

        if (empty($user)) {
            return response_json(402, trans('app.verifyPasswordFail'));
        }
        if (!Hash::check(think_md5($request->input('password')), $user->password)) {
            return response_json(402, trans('app.verifyPasswordFail'));
        } else {
            return response_json(200, trans('app.verifyPasswordSuccess'));
        }


    }


    /**
     * 2.23 验证支付密码
     * **参数：**
     * |参数名|必选|类型|说明|
     * |:----          |:---    |:-----     |-----       |
     * |bearer Token | 是|string|登录token
     * |pcode |是  |string |支付密码   |
     **返回示例**
     * ```
     *
     * ```
     **返回参数说明**
     * |参数名|类型|说明|
     * |:-----  |:-----|-----
     **/
    public function verifyPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pcode' => 'required|string|min:6|max:100',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $user = auth('api')->user();
        $pay_pwd=['pin'=>$request->input('pcode')];
        $check_pay_password = check_pay_password($user, 1, $pay_pwd);
        if($check_pay_password['code'] != 200){
            return response_json($check_pay_password['code'], $check_pay_password['msg']);
        }
        return response_json(200, trans('app.verifyPasswordSuccess'));


    }

    /**
     * 2.24免密金额转换
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |用户登录token  |
     * |current_id |是  |int |货币id     |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "获取数据成功",
     * "data": {
     * "money": "3392.25",
     * "symbol": "￥"
     * }
     * }
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * |money |decimal   |转换后的金额  |
     * |symbol|string|货币符号|
     * */
    public function changeMoney(Request $request)
    {
        $uid = Auth('api')->id();
        $user = User::where('id', $uid)->first();
        if ($user) {
            $no_secret_money = $user->no_secret_money;
            //用户国家法定货币
            if (!empty($user->fc_current_id)) {
                $user_current_id = $user->fc_current_id;
            } else {
                $user_current_id = Regions::where('region', $user->area)->value('current_id');
            }
        }
        //return $user_current_id;
        $current_id = $user_current_id;
        $symbol = Currency::where('current_id', $current_id)->select('symbol')->first();
        $old_current_id = 8001;
        $money = floor(Currency::transform($old_current_id, $current_id, 500));
        $money = substr($money, 0, strlen($money) - 1) . '0';
        //$no_secret_money = Currency::transform($old_current_id,$current_id,$no_secret_money);
        //设置的免密支付币种id
        $usd_rate = Currency::where('current_id', $user->secret_current_id)->value('rate');
        //当前币种汇率
        $now_rate = Currency::where('current_id', $current_id)->value('rate');
        if ($user_current_id == $user->secret_current_id) {
            $no_secret_money = $no_secret_money;
        } else {
            $no_secret_money = bcdiv(bcmul($no_secret_money, $usd_rate, 10), $now_rate, 10);
        }
        $data = array(
            'money' => $money,
            'symbol' => $symbol->symbol,
            'no_secret_money' => round($no_secret_money)
        );
        return response_json(200, trans('web.getDataSuccess'), $data);
    }

    /**
     * 2.25用户设置免密支付金额
     * **请求方式：**
     * - POST
     **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |用户登录token   |
     * |money |是  |string | 免密支付金额    |
     * |current_id     |是  |string | 币种id    |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "设置成功"
     * }
     * ```
     * */
    public function setSecretMoney(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'money' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $uid = Auth('api')->id();
        $user = User::where('id', $uid)->first();
        //$user_current_id = Regions::where('country_id',$user->country_id)->value('current_id');
        //用户国家法定货币
        if (!empty($user->fc_current_id)) {
            $user_current_id = $user->fc_current_id;
        } else {
            $user_current_id = Regions::where('region', $user->area)->value('current_id');
        }
        $money = trim($request->input('money'));
        $current_id = trim($request->input('current_id', $user_current_id));
        //美元汇率
        $usd_rate = Currency::where('current_id', 8001)->value('rate');
        //当前币种汇率
        $now_rate = Currency::where('current_id', $current_id)->value('rate');
        $tran_money = bcdiv(bcmul($money, $now_rate, 10), $usd_rate, 10);
        //$tran_money = $money;
        if ($tran_money < 50 || $tran_money > 500) {
            return response_json(402, trans('app.noSecretMoneyNeedsToBeBetween$50And$500'));
        }
        //$user->no_secret_money = $tran_money;
        $user->no_secret_money = $money;
        $user->secret_current_id = $current_id;
        if (!$user->save()) {
            return response_json(403, trans('app.setFail'));
        }
        return response_json(200, trans('app.setSuccess'));
    }

    /**
     * 2.26隐私管理-添加好友是否需要验证
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |用户登录token  |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "获取数据成功",
     * "data": {
     * "verify": 0
     * }
     * }
     * ```
     **返回参数说明**
     *
     * |参数名|类型|说明|
     * |:-----  |:-----|-----                           |
     * |verify |int   |0为添加好友不需要验证，1为需要验证  |
     * */
    public function verifyState()
    {
        $uid = Auth('api')->id();
        $verify = User::where('id', $uid)->value('friend_validation');
        $data['verify'] = $verify;
        return response_json(200, trans('app.getDataSuccess'), $data);
    }

    /**
     * 2.26隐私管理-关闭或开启好友验证
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |用户登录token   |
     * |state |是  |int | 0为不需要验证，1为需要验证    |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "设置成功"
     * }
     * ```
     * */
    public function friendVerify(Request $request)
    {
        $state = trim($request->input('state'));
        $uid = Auth('api')->id();
        $user = User::where('id', $uid)->first();
        $user->friend_validation = $state;
        if (!$user->save()) {
            return response_json(403, trans('app.setFail'));
        }
        return response_json(200, trans('app.setSuccess'));
    }


    /**
     * 2.27音效与通知-关闭或开启
     * **参数：**
     *
     * |参数名|必选|类型|说明|
     * |:----    |:---|:----- |-----   |
     * |token |是  |string |用户登录token   |
     * |sreceive_notice |否  |int | 接收通知1为开启，2为关闭    |
     * |svideo_chat |否  |int | 语音和视频通话提醒1为开启，2为关闭    |
     * |ssound |否  |int | 声音1为开启，2为关闭    |
     **返回示例**
     *
     * ```
     * {
     * "code": 200,
     * "msg": "设置成功"
     * }
     * ```
     * */
    public function notifySetting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sreceive_notice' => 'nullable|int|in:1,2',
            'svideo_chat' => 'nullable|int|in:1,2',
            'ssound' => 'nullable|int|in:1,2',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        if (empty($request->sreceive_notice) && empty($request->svideo_chat) && empty($request->ssound)) {
            return response_json(403, trans('app.setFail'));
        }
        $user = Auth('api')->user();
        if (!empty($request->sreceive_notice)) {
            $user->sreceive_notice = $request->sreceive_notice;
        }
        if (!empty($request->svideo_chat)) {
            $user->svideo_chat = $request->svideo_chat;
        }
        if (!empty($request->ssound)) {
            $user->ssound = $request->ssound;
        }
        if (!$user->save()) {
            return response_json(403, trans('app.setFail'));
        }
        return response_json(200, trans('app.setSuccess'));
    }

    //更新安卓token
    public function updatePingTime(Request $request)
    {
        \Log::useDailyFiles(storage_path('logs/updatePingTime/updatePingTime.log'));
        $token = 'empty';
        $type = $request->post('type');
        $fcm_token = $request->post('fcm_token') ?: $token;
        $device = $request->post('device');
        $device_type = $request->post('device_type');
        $uid = Auth('api')->id();
        $FcmTokenInfo = new FcmTokenInfo();
        if ($device == 'iOS') {
            $system = '2';
        } else {
            $system = '1';
        }

        $sql = "INSERT INTO  chainchat_fcm_token_info (fcm_token,device,device_type,uid,system) value('{$fcm_token}','{$device}','{$device_type}','{$uid}','{$system}') ON DUPLICATE KEY UPDATE fcm_token = '{$fcm_token}', device = '{$device}', device_type = '{$device_type}', uid = '{$uid}', system = '{$system}'";
        DB::insert($sql);

        $time = date('Y-m-d H:i:s');
        \Log::info('updatePingTime', ['uid' => $uid, 'fcm_token' => $fcm_token, 'system' => $system, 'time' => $time]);
        if ($type == '1' && $fcm_token) {
            $FcmTokenInfo->sendUserFcmuUp($uid, $fcm_token);
        }

        return response_json(200, trans('app.setSuccess'));
    }

    public function CurrentList(Request $request)
    {
        $user = Auth('api')->user();
        //$current_id = Currency::where(array('enabled'=>1,'is_virtual'=>1))->value('current_id');
        if ($user->virtual_current_id) {
            $current_id = $user->virtual_current_id;
        } else {
            $current_id = '';
        }
        $type = trim($request->input('type', 0));
        //type=0代表默认币种列表，type = 1代表支付，type = 2代表充值
        if ($type == 0) {
            $current = Currency::where(array('enabled' => 1, 'is_virtual' => 1))->select('current_id', 'short_en', 'name_en', 'name_cn', 'name_hk', 'circle')->orderBy('sort', 'desc')->get()->toArray();
        } elseif ($type == 1) {
            $current = Currency::where(array('enabled' => 1, 'can_pay' => 1, 'is_virtual' => 1))->select('current_id', 'short_en', 'name_en', 'name_cn', 'name_hk', 'circle')->orderBy('sort', 'desc')->get()->toArray();
        } elseif ($type == 2) {
            $current = Currency::where(array('enabled' => 1, 'can_recharge' => 1, 'is_virtual' => 1))->select('current_id', 'short_en', 'name_en', 'name_cn', 'name_hk', 'circle')->orderBy('sort', 'desc')->get()->toArray();
        } elseif ($type == 3) {
            $current = Currency::where(array('enabled' => 1, 'can_pay' => 1, 'is_virtual' => 1))->select('current_id', 'short_en', 'name_en', 'name_cn', 'name_hk', 'square as circle')->orderBy('sort', 'desc')->get()->toArray();
        }

        foreach ($current as $k => $v) {
            $current[$k]['circle'] = url($v['circle']);
            if ($user->language == 'en') {
                $current[$k]['name'] = $v['name_en'];
            } elseif ($user->language == 'cn') {
                $current[$k]['name'] = $v['name_cn'];
            } elseif ($user->language == 'hk') {
                $current[$k]['name'] = $v['name_hk'];
            } else {

            }
            if ($v['current_id'] == $current_id) {
                $current[$k]['is_default'] = 1;
            } else {
                $current[$k]['is_default'] = 0;
            }
            unset($current[$k]['name_cn']);
            unset($current[$k]['name_hk']);
            unset($current[$k]['name_en']);
        }
        return response_json(200, trans('app.getDataSuccess'), $current);
    }

    public function setDefaultCurrent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //获取数字货币第一个币种
        $user = Auth('api')->user();
        $current_id = trim($request->input('current_id'));
        $user->virtual_current_id = $current_id;
        if (!$user->save()) {
            return response_json(403, trans('app.editFail'));
        }
        return response_json(200, trans('app.editSuccess'));
    }


    //注册送金
    public function reggift($area, $uid, $form_token)
    {
        \Log::useDailyFiles(storage_path('logs/reggift/reggift.log'));
        $date = date('Y-m-d H:i:s');
        $nowtime = time();
        $candyList = Candy::with('arealist')->where('is_del', 0)->where('start_time', '<=', $date)->where('end_time', '>=', $date)->where('status', 2)->get();
//        dd($candyList);
        $user = User::where('id', $uid)->first();
//        dump($candyList);
        if ($candyList->isEmpty()) {
            return response_json(403, '没有注册送礼金活动');
        }
        $targetArea = array();
        $redis_key = 'reg_gift_' . $uid;
        if (Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])) {
            try {
                DB::beginTransaction();

                if (!empty($candyList)) {
                    foreach ($candyList as $k => $v) {
                        //用户注册时间应该小于活动开始时间
//                dump($user->created_at.strtotime($user->created_at));
//                dump($v->start_time.strtotime($v->start_time));
//                if (strtotime($user->created_at)<strtotime($v->start_time)){
                        if ($nowtime < strtotime($v->start_time)) {
                            Log::info('用户id :  ' . $uid . "  该用户注册时间小于活动开始时间 CURRENT id : " . $v->current_id . " money: " . $v->money . "活动开始时间" . $v->start_time . "用户注册时间  " . $user->created_at);
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, '该用户注册时间小于活动开始时间');
//                        dd("214213");
                        }
//                dd("00000");
                        if (!empty($v->arealist)) {
                            foreach ($v->arealist as $kk => $vv) {
                                //只有泰国台湾
//                        if ($vv['area_id']=='886'||$vv['area_id']=='66'){
                                $targetArea[] = $vv['area_id'];
//                            dump($vv);
//                        }
                            }
                        }
//                if (empty($targetArea)){
//                    return response_json(404, '区号不符合');
//                }
                        $current_id = $v->current_id;
                        $fc_money = $v->money;
                        $transfer_account = $v->money;    //转账金额

                        if (empty($current_id) || empty($fc_money)) {
                            Log::info('用户id :  ' . $uid . "  活动数据有误 CURRENT id : " . $current_id . " money: " . $transfer_account);
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, '活动数据有误');
                        }

                        $wallet_model = new UsersWallet($current_id);
                        $decimals = UsersWallet::get_decimals($current_id);
                        $wallet = $wallet_model->get_users_wallet($uid, $current_id);
//                dump($current_id);
//                dump($fc_money);
//                dump($transfer_account);
//                dump($decimals);
//                dump($targetArea);

                        if (empty($wallet)) {
                            Log::info('用户id :  ' . $uid . "  您没有该币种钱包 CURRENT id : " . $current_id);
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, '您没有该币种钱包');
                        }
                        //只要台湾和泰国
                        if (!empty($targetArea)) {
                            if (in_array($area, $targetArea)) {
                                //一个用户只能参加一次送金活动
                                $is_ever_gift = CandyOrder::where('uid', $uid)->where('rechargeType', 20)->first();

                                if (!empty($is_ever_gift)) {
                                    DB::rollBack();
                                    Redis::del($redis_key);
                                    Log::info('用户id :  ' . $uid . "  抱歉您已经收到过赠送礼金了 CURRENT id : " . $current_id);
                                    return response_json(403, '抱歉您已经收到过赠送礼金了');
                                }
                                //符合要求，生成送金订单
                                $order_sn = create_order_sn('CD');
                                $to_address = $wallet->address;
                                $now_time = date('Y-m-d H:i:s');
                                $after_balance = bcadd($wallet->usable_balance, $transfer_account, $decimals);
//                        dump($order_sn);
//                        dump($wallet->usable_balance);
//                        dump($after_balance);
                                $order = array(
                                    'order_sn' => $order_sn,
                                    'uid' => $uid,
                                    'wallet_id' => $wallet->id,
                                    'current_id' => $current_id,
                                    'address' => $to_address,
                                    'send_time' => $now_time,
                                    'confirm_time' => $now_time,
                                    'fee' => 0,     // 手续费
                                    'money' => $fc_money,
                                    'pay_money' => $fc_money,
                                    'total_amount' => $transfer_account,
                                    'amount' => $transfer_account,   // 订单总价
                                    'unit' => $wallet->unit,
                                    'category' => 'move',
                                    'is_send' => 2,   //1(发) 订单 2(收) 手续费
                                    'status' => 803,
                                    'is_done' => 1,
                                    'integral' => 0,
                                    'rechargeType' => 20,   // 0转账；1手机充值；2智能卡充值；3充值返币；4充值退币；5 领取锐币；6pos消费；7 兑换；8聊天转账；9红包；10邀请注册赠币；11聊天转账退款；12红包退款 ; 14商城订单；15付款码支付;16 向商家付款; 17积分兑换货币; 18 手续费，20注册送金活动
                                    'before_balance' => $wallet->usable_balance,
                                    'after_balance' => $after_balance,
                                    'form_token' => $form_token
                                );
                                $trueorder_id = Order::insertGetId($order);
                                $order['relate_orderid'] = $trueorder_id;

                                $order_id = CandyOrder::insertGetId($order);

                                // update
                                $wallet_update = UsersWallet::where('uid', $uid)
                                    ->where('current_id', $current_id)
                                    ->where('used_balance', $wallet->used_balance)
                                    ->where('usable_balance', $wallet->usable_balance)
                                    ->update([
                                        'usable_balance' => bcadd($wallet->usable_balance, $transfer_account, $decimals),
                                        'total_balance' => bcadd($wallet->total_balance, $transfer_account, $decimals),
                                    ]);

                                if ($order_id && $wallet_update && $trueorder_id) {

                                    DB::commit();
                                    Redis::del($redis_key);
//                            $email = array(
//                                'uid' => $uid,
//                                'email' => $user->email,
//                                'username' => "Rapidz",
//                                'to_username' => $user->username,
//                                'transfer_account' => $transfer_account,
//                                'unit' => $wallet->unit
//                            );
//                            SendTransferEmail::dispatch($email)->onQueue('transfer_mail');
                                    return response_json(200, '赠送成功');

                                } else {
                                    DB::rollBack();
                                    Redis::del($redis_key);
                                    Log::info('用户id :  ' . $uid . "  赠送失败 CURRENT id : " . $current_id);
                                    return response_json(404, '赠送失败');

                                }
                                break;
                            }//用户区号满足条件，一个用户只能参加一次活动
                        }


                    }
                }
            } catch (\Exception $exception) {
                DB::rollBack();
                Redis::del($redis_key);

                Log::info(', message:' . $exception->getMessage() . " ,trace " . $exception->getTraceAsString());
                return response_json(402, 'web.报错赠送失败');
            }
        } else {
            return response_json(402, trans('app.accessFrequent'));
        }
//        dd($targetArea);

    }


    // 生成滑动验证码
    public function captcha(Request $request)
    {

        $register = $request->input('register', 0);
        if ($register) {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string',
            ]);
            if ($validator->fails()) {
                return response_json(402, $validator->errors()->first());
            }
            $email = $request->input('email');
            if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                return response_json(402, trans('app.emailFormatError'));
            }
            $user = User::select("id")
                ->where('email', $email)
                ->first();
            if (!empty($user)) {
                return response_json(402, trans('app.emailAlreadyExit'));
            }
        }

        header("Access-Control-Allow-Origin: *");
        $url = app('captcha')->create('default', true);
        $data = array(
            'url' => $url
        );
        //获取版本号
        $lang = $request->input('lang', 'cn');
        $version = $request->header('Version', '1.18');
        //if($version > '1.18'){
        return $this->test_img($lang);
        //}
        //return response_json(200,trans('app.getDataSuccess'),$data);

    }

    public function checkCaptcha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'captcha' => 'required|string',
            'key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        if (!captcha_api_check(trim($request->captcha), trim($request->key))) {
            return response_json(402, trans('app.verificationCodeError'));
        }
        $data = array(
            'key' => trim($request->input('key')),
            'code' => trim($request->input('captcha')),
            'captcha' => 1
        );
        DB::table('captcha')->insert($data);
        return response_json(200, trans('app.success'));
    }

    /*
     * 图片验证
     */
    public function test_img(Request $request)
    {

        $register = $request->input('register', 0);
        $lang = $request->input('lang', 'en') ?: 'en';
        App::setLocale($lang);
        if ($register) {
            switch ($register) {
                case 1: //忘记pin码
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;
                case 2: //忘记密码
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;
                case 3: //注册
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (!empty($user)) {
                        return response_json(402, trans('app.emailAlreadyExit'));
                    }
                    break;
                case 4: //修改
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;
                case 5: //修改个人资料
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;
                case 6: //重置邮箱
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;
                case 7: //认证邮箱
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;

                case 8: // 设置pin密码
                    $validator = Validator::make($request->all(), [
                        'email' => 'required|string',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $email = $request->input('email');
                    if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/', $email)) {
                        return response_json(402, trans('app.emailFormatError'));
                    }
                    $user = User::select("id")
                        ->where('email', $email)
                        ->first();
                    if (empty($user)) {
                        return response_json(402, trans('app.emailNotRegister'));
                    }
                    break;
                default;
            }
        }

        // $source = imagecreatefrompng('https://mobile.rapidz.io/storage/fc_circle_image/cBze30KZog6GMTJc6vkImlREPBgyWT7EAd3Tv62S.png');
//        $mask =imagecreatefrompng('https://mobile.rapidz.io/storage/fc_circle_image/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png');
        $source = imagecreatefrompng(public_path('captcha/banner.png'));
        $mask = imagecreatefrompng(public_path('captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png'));
        $xSize_picture = imagesx($source);
        $ySize_picture = imagesy($source);
        $xSize = imagesx($mask);
        $ySize = imagesy($mask);
        $rand_x = rand($xSize_picture / 2, $xSize_picture - $xSize);
        $rand_y = rand($ySize, $ySize_picture - $ySize);
        $data = array(
            // 'img_background'=>'https://mobile.rapidz.io/storage/fc_circle_image/cBze30KZog6GMTJc6vkImlREPBgyWT7EAd3Tv62S.png',
            // 'img_small'=>'https://mobile.rapidz.io/storage/fc_circle_image/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png',
            'img_background' => public_path('captcha/banner.png'),
            'img_small' => public_path('captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png'),
            'x' => $rand_x,
            'y' => $rand_y,
            'creation_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );
        $id = DB::table('img_captcha')->insertGetId($data);
        if ($id) {
            $token = Crypt::encryptString('img_id:' . $id);
            $id = Crypt::encryptString('jiami_id:' . $id);
            $data1['y'] = $rand_y;
            $data1['token'] = $id;
            $data1['background'] = url('api/user/getBackground?token=' . $token);
            $data1['small'] = url('api/user/small?token=' . $token);
            return response_json(200, trans('app.success'), $data1);
        }

    }


    /*
     *验证codeValidateFail
     */
    public function checkimg(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'x' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = [
                'error' => $validator->errors()->first()
            ];
            return response_json(402, $errors);
        }
        //设置语言
        $lang = $request->input('lang', 'cn');
        App::setLocale($lang);
        $token = $request->input('token');
        $x = $request->input('x');
        $token = Crypt::decryptString($token);
        $id = str_replace('jiami_id:', '', $token);
        $data = DB::table('img_captcha')->where('id', $id)->first();
        $nowdate = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $md5_x[] = md5('x_md5' . $data->x);
        for ($i = 1; $i <= 3; $i++) {
            $jia = $data->x + $i;
            $jian = $data->x - $i;
            $md5_x[] = md5('x_md5' . $jia);
            $md5_x[] = md5('x_md5' . $jian);
        }
        if ($data->captcha == 0) {
            if ($nowdate < $data->creation_at) {
                if (in_array($x, $md5_x)) {
                    DB::table('img_captcha')
                        ->where('id', $id)
                        ->update(['captcha' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
                    return response_json(200, trans('app.codeValidateSuccessfully'));
                } else {
                    DB::table('img_captcha')
                        ->where('id', $id)
                        ->update(['captcha' => 2, 'updated_at' => date('Y-m-d H:i:s')]);
                    return response_json(403, trans('app.codeValidateFail'));
                }
            } else {
                DB::table('img_captcha')
                    ->where('id', $id)
                    ->update(['captcha' => 3, 'updated_at' => date('Y-m-d H:i:s')]);
                return response_json(403, trans('app.codeValidateFail'));
            }
        } else {
            return response_json(403, trans('app.doNotSubmitAgain'));
        }


    }

    /*
     *背景图
     */
    public function getBackground(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $token = $request->input('token');
        $token = Crypt::decryptString($token);
        $id = str_replace('img_id:', '', $token);
        $data = DB::table('img_captcha')->where('id', $id)->first();
        if (empty($data)) {
            return response_json(403, '非法获取');
        }
        $source = imagecreatefrompng($data->img_background);
        $mask = imagecreatefrompng($data->img_small);
        $xSize = imagesx($mask);
        $ySize = imagesy($mask);
        $img = $this->imagealphamerge($source, $mask, $xSize, $ySize, $data->x, $data->y);
        return $img;
    }

    /*
     *小图片
     */
    public function small(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $token = $request->input('token');
        $token = Crypt::decryptString($token);
        $id = str_replace('img_id:', '', $token);
        $data = DB::table('img_captcha')->where('id', $id)->first();
        if (empty($data)) {
            return response_json(403, '非法获取');
        }
        $source = imagecreatefrompng($data->img_background);
        $mask = imagecreatefrompng($data->img_small);
        $xSize = imagesx($mask);
        $ySize = imagesy($mask);
        $img = $this->imagealphamask($source, $mask, $xSize, $ySize, (int)$data->x, (int)$data->y);

        return $img;
    }

    /*
     * 合并背景图片
     */
    private function imagealphamerge($picture, $mask, $xSize, $ySize, $rand_x, $rand_y)
    {
        imagecopy($picture, $mask, $rand_x, $rand_y, 0, 0, $xSize, $ySize);

        // 将图像保存到文件，并释放内存
        ob_start();
        imagepng($picture);
        $content = ob_get_contents();
        //imagepng($picture,'source_test.png');
        imagedestroy($picture);
        ob_end_clean();
        return $response = Response::make($content)->header('Content-Type', 'image/png');
    }

    private function imagealphamask($picture, $mask, $xSize, $ySize, $rand_x, $rand_y)
    {

        $newPicture = imagecreatetruecolor($xSize, $ySize);
        imagesavealpha($newPicture, true);
        imagefill($newPicture, 0, 0, imagecolorallocatealpha($newPicture, 0, 0, 0, 127));

        for ($x = 0; $x < $xSize; $x++) {
            for ($y = 0; $y < $ySize; $y++) {
                $alpha = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
                $alpha = 127 - floor($alpha['red'] / 2);

                $color = imagecolorsforindex($picture, imagecolorat($picture, $x + $rand_x, $y + $rand_y));
                imagesetpixel($newPicture, $x, $y, imagecolorallocatealpha($newPicture, $color['red'], $color['green'], $color['blue'], $alpha));
            }
        }
        // 将图像保存到文件，并释放内存
        ob_start();
        imagepng($newPicture);
        $content = ob_get_contents();
        //imagepng($newPicture,'source_test.png');
        imagedestroy($newPicture);
        ob_end_clean();
        return $response = Response::make($content)->header('Content-Type', 'image/png');


    }


    public function phonecheckimg($token, $x)
    {
//        $token=$request->input('token');
//        $x=$request->input('x');
        $token = Crypt::decryptString($token);
        $id = str_replace('jiami_id:', '', $token);

        $captchas = ImgCaptcha::where('id', $id)->first();
        if (empty($captchas)) {
            return false;
        }
//        $captchas=DB::table('img_captcha')->where('id',$id)->first();
        $nowdate = date('Y-m-d H:i:s', strtotime('-10 minute'));
        $md5_x[] = md5('x_md5' . $captchas->x);
        for ($i = 1; $i <= 3; $i++) {
            $jia = $captchas->x + $i;
            $jian = $captchas->x - $i;
            $md5_x[] = md5('x_md5' . $jia);
            $md5_x[] = md5('x_md5' . $jian);
        }
//        dump(in_array($x,$md5_x));
//        dd($x);
        if ($captchas->mobile_captcha < 2) {
            if ($nowdate < $captchas->creation_at) {
                if (in_array($x, $md5_x)) {

                    if ($captchas->mobile_captcha < 3) {
                        $captchas->mobile_captcha = $captchas->mobile_captcha + 1;
                        $captchas->save();
                    } else {
                        return false;
                    }
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    public function emailcheckimg($token, $x)
    {

//        $token=$request->input('token');
//        $x=$request->input('x');
        $token = Crypt::decryptString($token);
        $id = str_replace('jiami_id:', '', $token);

        $captchas = ImgCaptcha::where('id', $id)->first();
        if (empty($captchas)) {
            return false;
        }
//        $captchas=DB::table('img_captcha')->where('id',$id)->first();
        $nowdate = date('Y-m-d H:i:s', strtotime('-10 minute'));
        $md5_x[] = md5('x_md5' . $captchas->x);
        for ($i = 1; $i <= 3; $i++) {
            $jia = $captchas->x + $i;
            $jian = $captchas->x - $i;
            $md5_x[] = md5('x_md5' . $jia);
            $md5_x[] = md5('x_md5' . $jian);
        }
//        dump(in_array($x,$md5_x));
//        dd($x);
        if ($captchas->email_captcha < 2) {
            if ($nowdate < $captchas->creation_at) {
                if (in_array($x, $md5_x)) {

                    if ($captchas->email_captcha < 3) {
                        $captchas->email_captcha = $captchas->email_captcha + 1;
                        $captchas->save();
                    } else {
                        return false;
                    }
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    // TODO
    public function getEmailCode(Request $request)
    {

        // wXshoGuYqQ2XCY63oOkE1XRAB3sCFN0E
        $encrypt = $request->input('encrypt', '');

        if ($encrypt == 'wXshoGuYqQ2XCY63oOkE1XRAB3sCFN0E') {
            $code = MailCode::where('email', $request->input('email'))
                ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                ->where('status', MailCode::STATUS_UNVERIFY)
                ->orderBy('expire_time', 'desc')
                ->value('code');
            dd($code);
        } else {
            echo 666;
        }

    }


    // 提前注册环信账号
    public function registerEasemob()
    {

        try {

            $db_user_id = User::max("id") ?: 0;
            $max_user_id = RegisterUsers::max("user_id") ?: 0;
            $max_user_id = $max_user_id + 1;

            // 如果生成的环信剩余个数不足1000个,则再次生成
            $surplus_number = $max_user_id - $db_user_id;
            if ($surplus_number >= 1000) {
                echo 'surplus';
                exit;
            }

//            $url = url()->current();
            if ($_SERVER['HTTP_HOST'] == 'supmin.chain-chat.app' || $_SERVER['HTTP_HOST'] == 'app.chain-chat.app' || $_SERVER['HTTP_HOST'] == 'user.chain-chat.app') {
                $pre = 'user';
            } else {
                $pre = 'test';
            }
//            $pre  = 'user';

            $number = $max_user_id + 1;
            $Easemob = new App\Libs\Easemob();
            $now_time = date('Y-m-d H:i:s');
            for ($user_id = $max_user_id; $user_id < $number; $user_id++) {

                // set_time_limit
                set_time_limit(0);

                // $username   =  'user' . $user_id;
                $username = $pre . $user_id;
                $password = randomkeys(16);
                $error = '';
                $result = $Easemob->createUser($username, $password);
                if (!$result) {
                    $error = '账号：' . $username . ' 注册失败';
                }
                if (isset($result['error'])) {
                    $error = '账号：' . $username . ' 错误信息：' . $result['error_description'];
                }
                $data = array(
                    'user_id' => $user_id,
                    'easemob_u' => $username,
                    'easemob_p' => $password,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                    'error' => $error,
                );
                RegisterUsers::insert($data);

                // 生成钱包地址
                GenerateWalletAddress::dispatch($user_id)->onQueue('getnewaddress' . $user_id);

            }

        } catch (\Exception $exception) {

            Log::useFiles(storage_path('registerEasemob.log'));
            Log::info('message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());

        }

        return response_json(200, trans('app.success'));

    }


    // 之前注册失败的环信账号, 重新注册
    public function againRegisterEasemob(Request $request)
    {

        try {

            // 查找注册环信有error的环信账号, 看能否重新注册
            $list = RegisterUsers::select("user_id", "easemob_u", "easemob_p")->where('error', '<>', '')->get()->toArray();
            if (!empty($list)) {

                $Easemob = new App\Libs\Easemob();

                foreach ($list as &$item) {
                    $username = $item['easemob_u'];
                    $password = $item['easemob_p'];
                    $error = '';
                    $result = $Easemob->createUser($username, $password);
                    if (!$result) {
                        $error = '账号：' . $username . ' 注册失败';
                    }
                    if (isset($result['error'])) {
                        $error = '账号：' . $username . ' 错误信息：' . $result['error_description'];
                        // duplicate_unique_property_exists 之前已经注册过了
                        if ($result['error'] == 'duplicate_unique_property_exists') {
                            $username = randomkeys(3) . $item['user_id']; // 账号重复则生成新的账号
                            $result = $Easemob->createUser($username, $password);
                            if (!$result) {
                                $error = '账号：' . $username . ' 注册失败';
                            }
                            if (isset($result['error'])) {
                                $error = '账号：' . $username . ' 错误信息：' . $result['error_description'];
                            }
                            RegisterUsers::where('user_id', $item['user_id'])->update([
                                'username' => $username,
                                'error' => $error,
                            ]);
                        }
                        RegisterUsers::where('user_id', $item['user_id'])->update([
                            'error' => $error
                        ]);
                    } else {
                        RegisterUsers::where('user_id', $item['user_id'])->update([
                            'error' => $error
                        ]);
                    }
                }
            }

            return response_json(200, trans('app.success'));

        } catch (\Exception $exception) {

            Log::useFiles(storage_path('againRegisterEasemob.log'));
            Log::info('message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());

        }

    }

    /**
     * 鉴权商家信息
     */
    public function authEnterprise(Request $request)
    {
        $user = Auth('api')->user();
        $validator = Validator::make($request->all(), [
            'enterpriseTypeName' => 'required|int', // 商家类别名称(独资企业,合伙企业)
            'enterpriseName' => 'required|string',//商家名称
            'enterpriseLegalName' => 'required|string',//企业法人名称
            'enterpriseLegalCard' => 'required|string',//企业法人身份证
            'enterprise_regions_id' => 'required|string',//手机国家区号id
            'enterprisePhone' => 'required|string',//企业法人联系方式
            'enterpriseEmail' => 'required|string',//企业法人邮箱
            'enterpriseAddress' => 'required|string',//商家注册地址
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //假如已经是商家则修改商家信息
        if ($request->input('enterpriseTypeName')) {
            $user->enterprise_type_name = $request->input('enterpriseTypeName');
        }
        if ($request->input('enterpriseName')) {
            $user->enterprise_name = $request->input('enterpriseName');
        }
        if ($request->input('enterpriseLegalName')) {
            $user->enterprise_legal_name = $request->input('enterpriseLegalName');
        }
        if ($request->input('enterpriseLegalCard')) {
            $user->enterprise_legal_card = $request->input('enterpriseLegalCard');
        }
        if ($request->input('enterprise_regions_id')) {
            //检验手机国家区号id是否存在
            $regions = Regions::where('country_id',(int)$request->input('enterprise_regions_id'))->first();
            if(empty($regions)) {
                return response_json(402, trans('app.regionsArea'));
            }
            $user->enterprise_phone = $request->input('enterprise_regions_id');
        }

        if ($request->input('enterpriseEmail')) {
            $user->enterprise_email = $request->input('enterpriseEmail');
        }
        if ($request->input('enterpriseAddress')) {
            $user->enterprise_address = $request->input('enterpriseAddress');
        }
        if($user->customer_type == 3) {
            return response_json(402, trans('app.isEnterpriseInfo'));
        }
        $user->customer_type = 3;
        $user->enterprise_regions_id = 1;

        $rst = $user->save();

        //生成商家收款码
        $receive_money = (new User())->getReceiveMoney($user->id);
        if (!empty($receive_money)){
            $user->enterpriseqrcode = $receive_money;
            $rst = $user->save();
        }

        if ($rst) {
            $data = [
                'path' =>  url($receive_money),
            ];
            return response_json(200, trans('app.changeEnterpriseInfoSuccess'), $data);
        } else {
            return response_json(402, trans('app.changeEnterpriseInfoError'));
        }

    }

    /**
     * 修改商家信息
     */
    public function updateEnterprise(Request $request)
    {
        $user = Auth('api')->user();
        $validator = Validator::make($request->all(), [
            'enterpriseTypeName' => 'nullable|string', // 商家类别名称(独资企业,合伙企业)
            'enterpriseName' => 'nullable|string',//商家名称
            'enterpriseLegalName' => 'nullable|string',//企业法人名称
            'enterpriseLegalCard' => 'nullable|string',//企业法人身份证
            'enterprisePhone' => 'nullable|string',//企业法人联系方式
            'enterpriseEmail' => 'nullable|string',//企业法人邮箱
            'enterpriseAddress' => 'nullable|string',//商家注册地址
            'enterprise_regions_id' => 'nullable|string',//手机国家区号id
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //假如已经是商家则修改商家信息
        if ($request->input('enterpriseTypeName')) {
            $user->enterprise_type_name = $request->input('enterpriseTypeName');
        }
        if ($request->input('enterpriseName')) {
            $user->enterprise_name = $request->input('enterpriseName');
        }
        if ($request->input('enterpriseLegalName')) {
            $user->enterprise_legal_name = $request->input('enterpriseLegalName');
        }
        if ($request->input('enterpriseLegalCard')) {
            $user->enterprise_legal_card = $request->input('enterpriseLegalCard');
        }
        if ($request->input('enterprise_regions_id')) {
            //检验手机国家区号id是否存在
            $regions = Regions::where('country_id',(int)$request->input('enterprise_regions_id'))->first();
            if(empty($regions)) {
                return response_json(402, trans('app.regionsArea'));
            }
            $user->enterprise_regions_id = $request->input('enterprise_regions_id');
        }
        if ($request->input('enterprisePhone')) {
            $user->enterprise_phone = $request->input('enterprisePhone');
        }
        if ($request->input('enterpriseEmail')) {
            $user->enterprise_email = $request->input('enterpriseEmail');
        }
        if ($request->input('enterpriseAddress')) {
            $user->enterprise_address = $request->input('enterpriseAddress');
        }
        if ($user->customer_type == 3) {
            $rst = $user->save();
            $receive_money = $user->receive_money;
            if ($rst) {
                $data = [
                    'path' => url($receive_money),
                ];
                return response_json(200, trans('app.changeEnterpriseInfoSuccess'), $data);
            } else {
                return response_json(402, trans('app.changeEnterpriseInfoError'));
            }
        } else {
            return response_json(402, trans('app.isNOtEnterpriseInfo'));
        }
    }

    // 获取商家分类
    public function getEnterpriseCategory(Request $request)
    {

        $category = App\Models\CompanyType::select("id as category_id", 'name_en')
            ->where('is_del', 0)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
        return response_json(200, trans('app.getDataSuccess'), array(
            'category' => $category
        ));

    }

    /**
     * 获取商家信息
     */
    public function getEnterpriseInfo(Request $request)
    {
        $id= auth('api')->id();
        $userInfo = User::select( 'enterprise_regions_id','enterprise_type_name','enterprise_name','enterprise_legal_name','enterprise_legal_card','enterprise_phone','enterprise_email','enterprise_address','receive_money','customer_type')
        ->where('id',$id)->where('customer_type',3)->first();
        if (!empty($userInfo)) {
            if (!empty($userInfo->receive_money)) {
                $userInfo->receive_money = url($userInfo->receive_money);
            }
            //商家类型替换
            $category = App\Models\CompanyType::select("id as category_id", 'name_en')
                ->where('id', $userInfo->enterprise_type_name)
                ->where('is_del', 0)
                ->orderBy('id', 'desc')
                ->first();
            if (!empty($category)) {
                $userInfo->enterprise_type_name_type = $category->name_en;
            } else {
                $userInfo->enterprise_type_name_type = '';
            }
            $enterprise = Regions::select('region')->where('country_id',$userInfo->enterprise_regions_id)->first();
            if (!empty($enterprise)){
                $userInfo->enterprise_regions_id = $userInfo->enterprise_regions_id;
                $userInfo->enterprise_regions =$enterprise->region;
            } else {
                $userInfo->enterprise_regions_id = '39';
                $userInfo->enterprise_regions ='971';
            }


        } else {
            return response_json(402, trans('app.isNOtEnterpriseInfo'));
        }


        return response_json(200, trans('app.getDataSuccess'), $userInfo);

    }

    /**
     * 搜索国家手机号
     */
    public function getRegionInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'nullable|string',//国家区号搜索
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $page_size = 20;
        //假如已经是商家则修改商家信息
        $keyword = $request->post('keyword');
        if (!empty($keyword)) {
            $regionsInfo = Regions::where('region','LIKE', '%'.$keyword.'%')->orWhere('en_country','LIKE','%'.$keyword.'%')->paginate($page_size);
            if (!empty($regionsInfo)){
                $regionsInfo = $regionsInfo->toarray();
            } else {
                $regionsInfo = [];
            }

        } else {
            $regionsInfo = Regions::paginate($page_size)->toArray();
        }
        $list = $regionsInfo['data'];
        $total = $regionsInfo['total'];
        $last_page = $regionsInfo['last_page'];
        return response_json(200, trans('app.getDataSuccess'), array(
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page
        ));
    }

    /**
     * 获取设置面板数据
     */
    public function getSetupPanel()
    {
        $authid = auth('api')->id();
        $authuser = User::where('id', $authid)->first(['id', 'fingerprint_login_status', 'fingerprint_pay_status',
            'face_pay_status', 'face_login_status','pin']);
        if (empty($authuser->pin)) {
            $authuser->pin = false;
        } else {
            $authuser->pin = true;
        }
        //指纹登录是否开启
        if ($authuser->fingerprint_login_status == 2) {
            $authuser->fingerlogin = 1;
        } else {
            $authuser->fingerlogin = 0;
        }
        unset($authuser->fingerprint_login_status);
        //指纹支付是否开启
        if ($authuser->fingerprint_pay_status == 2) {
            $authuser->fingerpay = 1;
        } else {
            $authuser->fingerpay = 0;
        }
        unset($authuser->fingerprint_pay_status);

        //人脸支付是否开启
        if ($authuser->face_pay_status == 2) {
            $authuser->facepay = 1;
        } else {
            $authuser->facepay = 0;
        }
        unset($authuser->face_pay_status);

        //人脸登录是否开启
        if ($authuser->face_login_status == 2) {
            $authuser->facelogin = 1;

        } else {
            $authuser->facelogin = 0;
        }
        unset($authuser->face_login_status);



        return response_json(200, trans('app.getDataSuccess'), $authuser);
    }

}


