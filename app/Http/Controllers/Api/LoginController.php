<?php

namespace App\Http\Controllers\Api;

use App\Jobs\DeleteRedisToken;
use App\Models\Api\LoginToken;
use App\Models\AppError;
use App\Models\CompanyType;
use App\Models\EnterpriseCategory;
use App\Models\Prize;
use App\Models\RegisterUsers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Support\Facades\Hash;
//use App\Jobs\EasemobCreateUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
//use App\Transformers\UserTransformer;
use App\Models\User;
use App\Models\MailCode;
use App\Models\EmsCode;
use App\Models\AppRecommend;
use App\Models\UserLogLogin;
use App\Models\UserLogLogout;
use App\Models\Currency;
use Auth;
use App\Models\AppErrror;
use App\Models\Message;
use App\Models\FcmTokenInfo;
use App\Models\Api\Captcha;
use App\Models\Api\ImgCaptcha;
use Illuminate\Support\Facades\Crypt;
use App\Libs\MyLog;


/**
 * @group 1用戶API
 * - author linlicai
 */
class LoginController extends Controller
{
    use ThrottlesLogins;
    protected $header = 'authorization';
    protected $prefix = 'bearer';

    /**
     * wx会员登录
     */
    public function loginWx()
    {
        // 访问域名会优先执行index方法，用以获取到code
        $appid = config('app.appid');
//        $redirect_uri = urlencode(Config::get('weixin.back_url'));
        $redirect_uri = urlencode(url('api/get/getuseropenid'));
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appid . "&redirect_uri=" . $redirect_uri . "&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect";
        //        $this->success("成功",$url);
        header("Location: " . $url);
        die;
    }

    /**
     * 获取openid
     */
    public function getuseropenid(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //回调地址会传回一个code，则我们根据code去获取openid和授权获取到的access_token
        $code = $request->get('code');
        $appid = config('app.AppId');
        $secret = config('app.AppSecret');
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appid . "&secret=" . $secret . "&code=" . $code . "&grant_type=authorization_code";
        $res = curl_get_https($url);
        $res = json_decode($res, 1);
        Log::info('getuseropenid', $res);

        $access_token = isset($res['access_token']) ? $res['access_token'] : '';
        $getopenid = isset($res['openid']) ? $res['openid'] : '';
        if ($access_token === '') {
            return response_json(403, trans('app.accessTokenIsNull'));
        }
        if ($getopenid === '') {
            return response_json(403, trans('app.openidIsNull'));
        }
        //获取用户授权信息
        $urltoc = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $access_token . "&openid=" . $getopenid . "&lang=zh_CN";
        $resinfos = $this->http_curl($urltoc);
        $openid = $resinfos['openid'];
        $userModel = new User();
        $check_member = $userModel->where('openid', $openid)->first();
        if (empty($check_member)) {
            //首次进入，则获取用户信息，插入数据库
            $resinfo['openid'] = $openid;
            $fileName = randomkeys(32);
            $path = storage_path('app/public/');
            if (!is_dir($path)) {
                create_dir($path);
            }
            $facePath = storage_path('app/public/qrcode/app_logo1.png');
            if (!@fopen($facePath, 'r')) {
                $facePath = 'https://apptest.hamdantoken.io/storage/qrcode/app_logo.png';
            }
            $receive_money_path = $path . $fileName . '.png';
            $receive_money = 0;
            ////////////// Storage::put 生成二维码改变
            $qrcode_create = \QrCode::format('png')->size(295)->merge($facePath, .20, true)->generate($receive_money, $receive_money_path);
            $insert_data = [
                'openid' => $openid,
                'wx_nickname' => $resinfos['nickname'],
                'wx_sex' => $resinfos['sex'],
                'wx_city' => $resinfos['city'],
                'wx_province' => $resinfos['province'],
                'wx_country' => $resinfos['country'],
                'wx_headimgurl' => $resinfos['headimgurl'],
                'created_at' => time(),
                'status' => 1,
            ];
            $userModel->save($insert_data);
            $userId = $userModel->id;

            //注册生成该APP用户的推荐码
            $newRecommendcode = randomkeys(4);
            while (User::select("id")->where('recommend_code', $newRecommendcode)->first()) {
                $newRecommendcode = randomkeys(4);
            }
            User::where(['id', $userId])->update(['recommend_code' => $newRecommendcode]);
        } else {
            //说明是已经是公众号成员，则调用用户信息存到session即可
            $wxMemberInfo = $userModel->where("openid", $openid)->first();
            $userId = $wxMemberInfo->id;
            $openid = $wxMemberInfo->openid;
            \Session::set('wx_member_info', $wxMemberInfo);
            //跳转网页
        }
        $ret = $this->auth->wxLogin($openid);
        if ($ret) {
            $data = ['userinfo' => $this->auth->getUserinfo()];
            Log::info('getuseropenid', $data);
            return response_json(200, trans('app.loginSuccess'));
        } else {
            return response_json(403, trans('app.loginFail'));
        }

    }


    /**
     *  登录接口
     */

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|string|max:50',
            'password' => 'nullable|string|between:8,32',
            'lang' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //登录设置语言
        if (!empty($request->lang)) {
            \App::setLocale($request->lang);
        }

        $getEmail = $request->post('email');

        if ($getEmail) {
            $user = User::where('email', $request->input('email'))
                ->where('status', User::STATUS_ALLOW)
                ->first();
            if (!$user) {
                return response_json(402, trans('app.emailNotRegister'));
            }
            if ($user->email_pin_error >= 3) {
                return response_json(402, trans('app.pinErrorExceedThreeTimesLogin'));
            }
            if ($user->status != 1) {
                return response_json(402, trans('web.accountAreBlockLogin'));
            }
            if ($user->email_status == 1) {//邮箱未认证
                return response_json(402, trans('app.emailNotVerify'), ['email_status' => 1]);
            }

            if ($request->input('password')) {
                if (!Hash::check(think_md5($request->input('password')), $user->password)) {
                    return response_json(402, trans('app.accountWrongOrPasswordWrong'));
                }
            } else {
                return response_json(402, trans('app.parameWrong'));
            }
        }

        $token = $this->gettoken($user->id);
        $needLoginTtl = config('app.needloginttl') * 60;

        $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;    //30天的秒数就是2592000

        //用户单点登录  当前 time 存入 Redis
        $time = time();
        $mirotime = $time . str_random(4);
        //nt  新token,ot老token
        $redData = ['uid' => $user->id, 't' => $time, 'nt' => '', 'ot' => ''];
        $seriRedData = serialize($redData);
        Redis::set(User::USER_INFO . $token, $seriRedData, 'EX', $needLoginTtl);
        LoginToken::updateOrCreate(['uid' => $user->id, 'type' => 0], ['tokentext' => $seriRedData, 'token' => $token]);

        if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } else {
            $ip = $request->getClientIp();
        }
        Redis::set(User::STRING_SINGLETOKEN . $user->id, $mirotime);
        $singleToken = encryptionToken($user->id, $mirotime);

        //app用户登录日志
        $agent = $request->header('User-Agent');
        $userLog = new UserLogLogin;
        $userLog->ip = $ip;
        $userLog->userId = $user->id;
        $userLog->username = $user->username;
        $userLog->redistime = $mirotime;
        $userLog->desc = $singleToken;
        $userLog->login_token = $token;
        $userLog->device = $agent;
        $userLog->save();

        $authuser = User::getUser($user->id);

        if (!empty($authuser->headimg_url)) {
            $authuser->headimg_url = url($authuser->headimg_url);
        } else {
            $authuser->headimg_url = url('storage/img/defaultlogo.png');
        }
        //检验是否从用户后台退出登录
//        $checkUidKey = $user->id . md5($user->id) . 'member';
//        $checkMenberLogout = Redis::get($checkUidKey);
//        if (!empty($checkMenberLogout)) {
//            Redis::del($checkUidKey);
//        }
        return $this->respondWithToken($token . "." . $singleToken, $user->id, trans('app.loginSuccess'), $authuser);
    }


    /**
     * 验证
     */
    public function checkimg($token, $x)
    {

        try {

            $token = Crypt::decryptString($token);
            $id = str_replace('jiami_id:', '', $token);
            $captchas = ImgCaptcha::where('id', $id)->first();
            if (empty($captchas)) {
                return false;
            }
            $nowdate = date('Y-m-d H:i:s', strtotime('-15 minute'));
            $md5_x[] = md5('x_md5' . $captchas->x);
            for ($i = 1; $i <= 3; $i++) {
                $jia = $captchas->x + $i;
                $jian = $captchas->x - $i;
                $md5_x[] = md5('x_md5' . $jia);
                $md5_x[] = md5('x_md5' . $jian);
            }
            if ($captchas->reg_captcha < 10) {
                if ($nowdate < $captchas->creation_at) {
                    if (in_array($x, $md5_x)) {
                        if ($captchas->reg_captcha < 10) {
                            $captchas->reg_captcha = $captchas->reg_captcha + 1;
                            $captchas->save();
                        } else {
                            return false;
                        }
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return response_json(402, trans('app.sliderVerification'));
                }
            } else {
                return false;
            }

        } catch (\Exception $exception) {

            Log::useFiles(storage_path('checkimg.log'));
            Log::info('checkimg, message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());
            return false;

        }

    }


    /**
     * 1.5刷新用户TOKEN
     *  -该接口可测试，但是现在还不需要用，现在会在请求头自动返回更新token
     *  -请求参数
     *|参数名|必选|类型|说明|
     *|:----    |:---|:----- |-----   |
     *|token |是  |string |登录token   |
     *|返回示例|
     *|:-----  |
     *
     * ```
     * {
     * "code": 200,
     * "msg": "成功退出登录",
     * "data": []
     * }
     * ```
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $myapitoken = $this->parse($request);
        if (empty($myapitoken)) {
            return response()->json(['code' => 405, 'data' => '', 'msg' => trans('app.tokenNotPrivide')]);
        }
        $tokenarrays = explode('.', $myapitoken);
        $seriUserId = Redis::get($tokenarrays[0]);
        $unseriUserId = unserialize($seriUserId);
        $userId = $unseriUserId['uid'];
        // $firstLoginTime = Redis::get('API_STRING_SINGLETOKEN_'.$userId);
        if (empty($userId)) {
            return response()->json(['code' => 405, 'data' => '', 'msg' => trans('app.tokenInvalide')]);
        }
        if (!empty($unseriUserId['ot'])) {
            Redis::setex($unseriUserId['ot'], 10, $seriUserId);
        }
        //并发上锁
        if (Redis::command('set', ['reg_lock_' . $tokenarrays[0], true, 'NX', 'EX', 10])) {
            $mynowtime = time();

            $ttlTime = config('app.ttl');
            $needLoginTtl = config('app.needloginttl') * 60;
            $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;

            if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } else {
                $ip = $request->getClientIp();
            }

            //Redis::set('API_STRING_SINGLETOKEN_' . $userId, $mynowtime);
            //$singleToken = md5("_(8)./a6pi4354".$mynowtime."53454#$&G43514" . $userId . "989883467a5@F0lH");
            $singleToken = $tokenarrays[1];
            $newtoken = $this->gettoken($userId);
            //清空登录数据,
            $unseriUserId['nt'] = $newtoken . "." . $singleToken;
            Redis::set($tokenarrays[0], serialize($unseriUserId), 'EX', 10);
            // Redis::del($tokenarrays[0]);

            $redData = ['uid' => $userId, 't' => $mynowtime, 'nt' => '', 'ot' => $tokenarrays[0]];
            $a = Redis::set($newtoken, serialize($redData), 'EX', $needLoginTtl);
            return $this->respondWithToken($newtoken . "." . $singleToken, $userId, trans('app.loginStatusSuccess'));
        } else {
            return $this->respondWithToken($myapitoken, $userId, trans('app.loginStatusSuccess'));
        }
    }

    public function gettoken($id)
    {
        //生成一个不会重复的字符串
        $str = md5(uniqid(md5(microtime(true) . $id), true));
        $str = sha1($str);  //加密
        return $str;
    }


    protected function respondWithToken($token, $uid, $msg, $data = [])
    {
        return response()->json([
            'code' => 200,
            'msg' => $msg,
            'data' => [
                'data' => $data,
                'uid' => $uid,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => env('SESSION_LIFETIME') * 60,
            ],

        ]);
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
     * 搜索区号
     * **参数：**
     * |参数名|必选|类型|说明|
     * |:----          |:---    |:-----     |-----       |
     * |bearer Token | 是|string|登录token
     * |s |是  |string |搜索关键字   | 8|6|86|中|中国|国
     **/
    public function searchArea(Request $request)
    {
        $validator = Validator::make($request->all(), [
            's' => 'required|string|min:1|max:6',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //$user = auth('api')->user();
        $keyword = $request->input('s');
        $Regions = new Regions;
        if (!empty($keyword)) {
            $Regions = $Regions->where(function ($query) use ($keyword) {
                $query->where('country', 'like', "%{$keyword}%")->where('is_open', "1")->orWhere('en_country', 'like', "%{$keyword}%")->orWhere('region', 'like', "%{$keyword}%");
            });
        }
        $Regions = $Regions->select('country', 'en_country', 'region', 'area', 'tw_country', 'country_id')->get();
        //$region = Regions::all()->toarray();
        if (!empty($Regions)) {
            return response_json(200, trans('app.success'), $Regions);
        } else {
            return response_json(402, trans('app.fail'));
        }

    }

    /**
     * 获取地区列表
     **/
    public function getarea(Request $request)
    {
        $validator = Validator::make($request->all(), [
            's' => 'nullable|string|min:1|max:6',
            'lang' => 'nullable|string|min:1|max:6',
        ]);
        $lang = $request->lang;
        if (!empty($lang)) {
            \App::setLocale($lang);
        } else {
            $lang = 'cn';
        }
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        //$user = auth('api')->user();
        $keyword = $request->input('s');
        $Regions = new Regions;
        if (!empty($keyword)) {
            $Regions = $Regions->where(function ($query) use ($keyword) {
                $query->where('country', 'like', "%{$keyword}%")->where('is_open', "1")->orWhere('en_country', 'like', "%{$keyword}%")->orWhere('region', 'like', "%{$keyword}%");
            });
        } else {
            $Regions = $Regions::where('is_open', "1");
        }

        if ($lang == 'en') {
            $Regions = $Regions->select('en_country as country', 'region', 'area', 'country_id')->orderBy('en_country', 'asc')->get();

        } elseif ($lang == 'hk') {
            $Regions = $Regions->select('tw_country as country', 'region', 'area', 'country_id')->orderBy('en_country', 'asc')->get();
        } else {
            $Regions = $Regions->select('country', 'region', 'area', 'country_id')->orderBy('en_country', 'asc')->get();

        }
        //$region = Regions::all()->toarray();
        if (!empty($Regions)) {
            return response_json(200, trans('app.getDataSuccess'), $Regions);
        } else {
            return response_json(402, trans('app.fail'));
        }
        // $region = Regions::all()->toarray();
        // return response_json(200,trans('app.getDataSuccess'),$region);
    }

    /**
     * 1.8获取APP语言接口
     **/
    public function applanglist(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lang' => 'nullable|string|in:en,cn',
            'version' => 'required'
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $lang = $request->lang;
        if (!empty($lang)) {
            \App::setLocale($lang);
        }
        $version = trans('android.version');
        if ($request->version == $version) {
            $langlist['version'] = $version;
            $langlist['langList'] = [];
        } else {
            $langlist['version'] = $version;
            $allLangList = trans('android');
            if (is_array($allLangList)) {
                foreach ($allLangList as $k => $v) {
                    $temp[] = array('key' => $k, 'value' => $v);
                }
            }
            $langlist['langList'] = $temp;
        }

        return response_json(200, trans('app.getDataSuccess'), $langlist);

    }


    /**
     * app上传日志错误
     */
    public function uploadError(Request $request)
    {
        // $lang = $request->message;
        if (!empty($request->msg1)) {

            $flight = new AppError();
//            $flight->route = empty($request->msg1)?'':serialize($request->msg1);
//            $flight->param = empty($request->msg2)?'':serialize($request->msg2);
//            $flight->error = empty($request->msg3)?'':serialize($request->msg3);
//            $flight->header = empty($request->header())?'':serialize($request->header());

            $os = $request->header('os');
            if (stripos($os, 'ios') !== false) {
                $flight->os = 'ios';
            } elseif (stripos($os, 'android') !== false) {
                $flight->os = 'android';
            } else {
                $flight->os = 'other';
            }

            $flight->route = empty($request->msg1) ? 'testmsg1' : json_encode($request->msg1, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $flight->param = empty($request->msg2) ? 'testmsg2' : json_encode($request->msg2, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $flight->error = empty($request->msg3) ? 'testmsg3' : json_encode($request->msg3, JSON_UNESCAPED_UNICODE);
            $flight->header = empty($request->header()) ? 'header' : json_encode($request->header());
            $flight->save();
        }
        return response_json(200, 'success', array(
//            'request' => $request->all(),
//            'header' => $request->header(),
        ));

    }

    /**
     * @param $email
     * @return string
     * 隐藏邮箱手机号
     */
    function mail_hidden($str)
    {
        if (strpos($str, '@')) {
            $email_array = explode("@", $str);

            if (strlen($email_array[0]) <= 1) {
//                $prevfix =  substr_replace($email_array[0],'*',1,1);
////                $rs = $prevfix.$email_array[1];
//                $prevfix = substr($str, 0, 1); //邮箱前缀
//                $count = 0;
//                $str = preg_replace('/([\d\w+_-]{0,100})@/', '*@', $str, -1, $count);
//                $rs = $prevfix . $str;
                $rs = $str;
            } else if (strlen($email_array[0]) <= 5) {

                $frist = substr($email_array[0], 0, 1);
//                $last = substr( $email_array[0], -1, 1 );
                $rs = $frist . '****@' . $email_array[1];
            } else {
                $frist = substr($email_array[0], 0, 3);
                $last = substr($email_array[0], -2);
                $rs = $frist . '****' . $last . '@' . $email_array[1];
//                $prevfix =  substr_replace($email_array[0],'****',3,1);
//                $rs = $prevfix.$email_array[1];
            }

        } else {
            $pattern = '/(1[3458]{1}[0-9])[0-9]{4}([0-9]{4})/i';
            if (preg_match($pattern, $str)) {
                $rs = preg_replace($pattern, '$1****$2', $str); // substr_replace($name,'****',3,4);
            } else {
                $rs = substr($str, 0, 3) . "***" . substr($str, -1);
            }
        }
        return $rs;

    }


    // 注册
    public function register(Request $request)
    {
        $language = $request->header('lang');
        if (empty($language)) {
            $language = 'cn';
        }
        App::setLocale($language);
        $messages = [
            'recommend.min' => trans('app.recommendFormatWrong'),
            'recommend.max' => trans('app.recommendFormatWrong'),
            'email.unique' => trans('app.reg_email_allready_registed'),
            'captcha.required' => trans('app.captchaisrequire'),
            'key.required' => trans('app.captchaisrequire'),
        ];
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:50|required_without:phone|unique:users,email',
            'password' => 'required|string|between:8,32',
            'repassword' => 'required|string|between:8,32',
            'code' => 'required|min:6',
            'verifytype' => 'required|int',
            'recommend' => 'nullable|string|min:4|max:4',
            'lang' => 'nullable|string|min:1|max:5',
            'captcha' => 'required|string',
            'key' => 'required|string',
        ], $messages);


        if ($request->input('password') != $request->input('repassword')) {
            return response_json(402, trans('app.duplicatepassword'));
        }
//        if (!in_array($request->input('email'), ['15113993100@163.com', '15113993101@163.com', '15113993102@163.com', '1262638533@qq.com', '181229184@qq.com'])) {
//            $ischeckimg = $this->checkimg($request->key, $request->captcha);
//            if (!$ischeckimg) {
//                return response_json(402, trans('app.verifyFailPleaseRetry'));
//            }
//        }

        //  1 版本更新，2 活动通知， 3 通知，4 公告 ，5 维护
        $message = Message::select("id", "title", "content", "type", "start_time", "end_time")
            ->where('type', 5)
            ->where('lang', $language)
            ->orderBy('id', 'desc')
            ->first();
        $now_time = time();
        if (!empty($message)) {
            if ($now_time > strtotime($message->start_time) && $now_time < strtotime($message->end_time)) {
                if (!empty($message->end_time)) {
                    return response_json(499, trans('app.appismaintain'));
                } else {
                    return response_json(499, trans('app.appismaintain'));
                }
            }
        }

        //唯一锁key
        if ($request->input('verifytype') == '1') {
            $redisKey = "reg_user_" . $request->input('phone');
        } else {
            $redisKey = "reg_user_" . $request->input('email');
        }

        $phone = '';
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        //注册加锁
        if (Redis::command('set', ['reg_lock_' . $redisKey, true, 'NX', 'EX', 10])) {
//            try {
            DB::beginTransaction();
            $user = new User;
            if ($request->input('verifytype') == '1') {
                $user->phone_status = 2;
            } else {
                $user->email_status = 2;
            }

            $powerc = config('app.powerc');
            //start万能验证码
            $powercdie = config('app.powercdie');
            if ($request->input('code') !== $powerc || $powercdie == 1) {

                if ($request->input('verifytype') == '1') {
                    //台湾地区以0开头的手机号码，发送短信需要去掉
                    $firstm = substr($phone, 0, 1);
                    if ($firstm == 0) {
                        $phoneNoZero = substr($phone, 1);
                    } else {
                        $phoneNoZero = $phone;
                    }

                    $area = str_replace('+', '', $request->input('area'));
                    $code = EmsCode::where('mobile', $area . $phoneNoZero)
                        ->where('status', EmsCode::STATUS_UNVERIFY)
                        ->where('expire_time', '>=', date('Y-m-d H:i:s', time()))
                        ->orderBy('updated_at', 'desc')
                        ->first();

                    if (!$code) {
                        DB::rollBack();
                        Redis::del('reg_lock_' . $redisKey);
                        return response_json(402, trans('app.youdonotsendthesms'));
                    }
                    if ($request->input('code') != $code->code) {
                        DB::rollBack();
                        Redis::del('reg_lock_' . $redisKey);
                        return response_json(402, trans('app.codeUndefined'));
                    }
                } else {
                    $code = MailCode::where('email', $request->input('email'))
                        ->where('expire_time', '>', date('Y-m-d H:i:s', time()))
                        ->where('type', MailCode::TYPE_REG)
                        ->where('status', MailCode::STATUS_UNVERIFY)
                        ->orderBy('expire_time', 'desc')
                        ->first();

                    if (!$code) {
                        DB::rollBack();
                        Redis::del('reg_lock_' . $redisKey);
                        return response_json(402, trans('app.youdonotsendtheemail'));
                    }
                    if ($request->input('code') != $code->code) {
                        DB::rollBack();
                        Redis::del('reg_lock_' . $redisKey);
                        return response_json(402, trans('app.codeUndefined'));
                    }
                }

                $code->status = 1;
                $code->save();

            } else {
                // $user->phone_status = 2;
                // $user->email_status = 1;

            }//end 万能验证码


            //默认用户名 为手机号码
            $user->headimg_url = "storage/img/defaultlogo.png";
            $email = $request->input('email');
            $user->username = $this->mail_hidden($email);
            $user->email = $email;
            $user->language = $language;
            $user->phone = $phone;
            $user->area = str_replace('+', '', $request->input('area'));
            $user->password = bcrypt(think_md5($request->input('password')));
            $user->status = 1;
            $ruid = 0;
            //验证推荐码，正确入库
            if ($request->input('recommend')) {
                if ($originRecommendUser = User::where('recommend_code', $request->input('recommend'))->first()) {
                    $ruid = $originRecommendUser->id;   //推荐人的用户id
                } else {
                    DB::rollBack();
                    Redis::del('reg_lock_' . $redisKey);
                    return response_json(402, trans('app.recommendCodeUndefined'));
                }
            }
            //注册生成该APP用户的推荐码
            $newRecommendcode = randomkeys(4);
            while (User::select("id")->where('recommend_code', $newRecommendcode)->first()) {
                $newRecommendcode = randomkeys(4);
            }

            $user->recommend_code = $newRecommendcode;
            $user->recommender_id = $ruid;   //推荐人的用户id
            $rst = $user->save();
            $lastid = $user->id;

            // 注册环信
//                $easemob = RegisterUsers::select("user_id", "easemob_u", "easemob_p")->where('user_id', $lastid)->where('error', '')->first();
//
//                if(!empty($easemob)){
//                    (new User())->where('id', $lastid)->update([
//                        'easemob_u' => $easemob->easemob_u,
//                        'easemob_p' => $easemob->easemob_p,
//                    ]);
//                }else{
//                    // 如果没有之前生成环信账号, 则现在生成
//                    $url = url()->current();
//                    if (IS_FORMAL_HOST){
//                        $isEasemob = User::createEasemob($lastid);
//                    }else{
//                        $isEasemob = User::createEasemob($lastid, 0, 2);
//                    }
//                    if($isEasemob['code'] != 200){
//                        DB::rollBack();
//                        Redis::del('reg_lock_'.$redisKey);
//                        return response_json(402,$isEasemob['msg']);
//                    }else{
//                        $register_users = array(
//                            'user_id' => $lastid,
//                            'easemob_u' => $isEasemob['data']['username'],
//                            'easemob_p' => $isEasemob['data']['password'],
//                            'created_at' => date('Y-m-d H:i:s'),
//                            'updated_at' => date('Y-m-d H:i:s')
//                        );
//                        RegisterUsers::insert($register_users);
//                    }
//                }

            if (!empty($lastid)) {

                $token = $this->gettoken($lastid);
                $ttlTime = config('app.ttl');
                $needLoginTtl = config('app.needloginttl') * 60;
                $needLoginTtl = empty($needLoginTtl) ? 2592000 : $needLoginTtl;

                // 用户单点登录
                // 当前 time 存入 Redis
                $time = time();
                $redData = ['uid' => $user->id, 't' => $time, 'nt' => '', 'ot' => ''];
                $a = Redis::set($token, serialize($redData), 'EX', $needLoginTtl);
                if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
                    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
                } else {
                    $ip = $request->getClientIp();
                }

                Redis::set('API_STRING_SINGLETOKEN_' . $user->id, $time, 'EX', $needLoginTtl);
                $singleToken = md5("_(8)./a6pi4354" . $time . "53454#$&G43514" . $user->id . "989883467a5@F0lH");

                DB::commit();
                Redis::del('reg_lock_' . $redisKey);
                $initFc['range'] = 0;
                $initFc['stranger'] = 1;
                $easemob_u = '';
                $easemob_p = '';
                $data = [];

                return $this->respondWithToken($token . "." . $singleToken, $lastid, trans('app.regSuccess'), $easemob_u, $easemob_p, '', $initFc);

            } else {
                DB::rollBack();
                Redis::del('reg_lock_' . $redisKey);
                return response_json(402, trans('app.regFail'), array(
                    'error' => 1
                ));
            }

//            } catch (\Exception $exception) {
//                DB::rollBack();
//                Redis::del('reg_lock_' . $redisKey);
//                Log::error("APP Registe fail:" . $request->input('token') . "APP Registe fail: exeception" . $exception);
//                return response_json(402, trans('app.regFail'), array(
//                    'error' => 1
//                ));
//            }

        } else {
            return response_json(402, trans('app.accessFrequent'));
        }

    }

    /**
     * 未登录状况下判断是否开启指纹/人脸登录
     */

    public function checkOpenLogin(Request $request)
    {
        $messages = [
            'email.unique' => trans('app.emailNotExist'),
            'equid.unique' => trans('app.equNotExist'),
            'type.unique' => trans('app.typeRequest'),
        ];
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:50',
            'equid' => 'required|string|max:255',
            'type' => 'required|int|max:10',
        ], $messages);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $type = $request->input('type');
        $email = $request->input('email');
        $equId = $request->input('equid');
        $data = [];
        switch ($type) {
            case 1:
                //指纹登录 关闭1 开启2
                $user = User::where('email', $email)->where('status', User::STATUS_ALLOW)
                    ->where('fingerprint_id', $equId)->first();
                if (empty($user)) {
                    $data['fingerprintloginstatus'] = 1;
                } else {
                    if ($user->fingerprint_login_status == 2) {
                        $data['fingerprintloginstatus'] = 2;
                    } else {
                        $data['fingerprintloginstatus'] = 1;
                    }
                }

                break;
            case 2:
                //人脸登录 关闭1 开启2
                $user = User::where('email', $email)->where('status', User::STATUS_ALLOW)
                    ->where('fingerprint_id', $equId)->first();
                if (empty($user)) {
                    $data['faceloginstatus'] = 1;
                } else {
                    if ($user->face_login_status == 2) {
                        $data['faceloginstatus'] = 2;
                    } else {
                        $data['faceloginstatus'] = 1;
                    }
                }

                break;
            default;
        }


        return response_json(200, trans('app.getSuccess'), $data);


    }

    /**
     * 未登录校验是否设置支付密码
     * 参数 hard 设备id
     *     email 校验邮箱
     */
    public function checkPayPin(Request $request)
    {
        $messages = [
            'email.unique' => trans('app.emailNotExist'),
        ];
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|max:50',
        ], $messages);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $phoneid = $request->header('phoneid');
        if (empty($phoneid)) {
            return response_json(402, trans('app.phoneidNotExist'));
        }
        $email = $request->input('email');
        $user = User::where('email', $email)->where('status', User::STATUS_ALLOW)->where('fingerprint_id', $phoneid)->first();
        $pin = 0;
        if (empty($user)) {
            $pin = 0;
        } else {
            if (empty($user->pin)) {
                $pin = 0;
            } else {
                $pin = 1;
            }
        }

        $data = [
            'pin' => $pin
        ];
        return response_json(200, trans('app.getSuccess'), $data);
    }


    /*
    * 获取滑块验证图片
    */
    public function slideImg(Request $request)
    {

        $register = $request->input('register', 0);
        $lang = $request->input('lang', 'cn') ?: 'cn';
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
     * 滑块验证图片校验
     */
    public function checkSlideImg(Request $request)
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
}
