<?php

namespace App\Libs;

use App\Http\Controllers\Controller;
//use Auth;
use Illuminate\Support\Facades\DB;
use App\Models\EmsCode;
use Illuminate\Support\Facades\Log;
/**
 * 用户发送短信
 * - author llc
 */
class Monyunems extends Controller
{
    /** 发送单条
     * @param $mobile 发送的手机号
     * @param string templateId 模板ID
     * @param int $type 1 忘记pin码,2忘记密码,3注册认证手机号码,4重置手机号码,5修改个人资料
     * @param int $type 0代表短信，1代表彩信，2,代表国际短信，默认值为0
     * @return array|mixed
     */
    public static function singleSend($mobile, $area, $type = 0,$userid = '',$language='cn') {
        $code = Monyunems::createContent($area.$mobile, $area, $type, $userid);
        if (!$code) {
            return false;
        }
        $url =  config('ems.monyunems_url');
        $randomtoken = randomkeys(12);
        $param = array(
            'phone' => $mobile,
            'area' => $area,
            'type' => $type,
            'code' => $code,
            'token' => $randomtoken,
        );
        $secrect = config('ems.monyunems_pwd');
        ksort($param,SORT_STRING);
        $string1 = '';
        foreach($param as $key=>$v){
            if (empty($v)){
                continue;
            }
            $string1 .=$key .'='.$v.'&';
        }
        $string1 .= "key=" . $secrect;
        $signature = strtoupper(md5(trim($string1)));
        $param['verify'] = $signature;

        $data = http_build_query($param);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type:application/x-www-form-urlencoded',
                'content' => $data
            ));

        try{
            $context  = stream_context_create($options);
            $result = file_get_contents($url, FILE_TEXT, $context);

        }catch (\Exception $exception) {
            \Log::useDailyFiles(storage_path('logs/ems/ems.log'));
            Log::info('message:'.$exception->getMessage()." ,trace ".$exception->getTraceAsString());
            return json_encode(array('result'=>''));
        }


        return $result;
    }

    /** 生成发送文本
     * @param $mobile 发送的手机号，多个手机号用逗号隔开
     * @param $lang 发送文本的语言类型，如cn,en,hk
     * @return bool|string
     */
    public static function createContent($mobile, $area, $type, $userid)
    {
        $code = rand(100000, 999999);

        $expire = time() + 300;
//        if ($area!=='cn'){
//            $mobile = substr($mobile,2);
//        }
//        $mobiles = explode(',', $mobile);

        $data = array();
       // foreach ($mobiles as $mobile) {
            if($type==3){

                array_push($data, [
                    'mobile' => $mobile,
                    'created_at' => date('Y-m-d H:i:s', time()),
                    'updated_at' => date('Y-m-d H:i:s', time()),
                    'expire_time' => date('Y-m-d H:i:s', $expire),
                    'code' => $code,
                    'type' => $type,
                    'status' => EmsCode::STATUS_UNVERIFY
                ]);
            }else{
                //  $user = Auth::user('api');
                array_push($data, [
                    'user_id' => $userid,
                    'mobile' => $mobile,
                    'created_at' => date('Y-m-d H:i:s', time()),
                    'updated_at' => date('Y-m-d H:i:s', time()),
                    'expire_time' => date('Y-m-d H:i:s', $expire),
                    'code' => $code,
                    'type' => $type,
                    'status' => EmsCode::STATUS_UNVERIFY
                ]);
            }
      //  }

        $result = DB::table('ems_code')->insert($data);
        return $result ? $code : false;
    }

    function getSigntrue($param){
        $endata['phone'] = $param['phone'];
        $endata['area'] = $param['area'];
        $endata['type'] = $param['type'];
        $endata['code'] = $param['code'];
        $endata['token'] = $param['token'];
//    unset($param['verify']);
//    var_dump($param);
        $secrect = "sdklfjlsdmvlkjb[toeir3223534454452";
        ksort($endata,SORT_STRING);
        $string1 = '';
        foreach($endata as $key=>$v){
            if (empty($v)){
                continue;
            }
            $string1 .=$key .'='.$v.'&';
        }
        $string1 .= "key=" . $secrect;
        $rstToken = strtoupper(md5(trim($string1)));
        return $rstToken;


    }
}
