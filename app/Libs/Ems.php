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
class Ems extends Controller
{
    /** 发送单条
     * @param $mobile 发送的手机号
     * @param string templateId 模板ID
     * @param int $type 1 忘记pin码,2忘记密码,3注册认证手机号码,4重置手机号码,5修改个人资料
     * @param int $type 0代表短信，1代表彩信，2,代表国际短信，默认值为0
     * @return array|mixed
     */
    public static function singleSend($mobile, $area='cn', $type = 0,$userid = '',$language='cn') {

        $code = Ems::createContent($mobile, $area, $type, $userid);
        if ($area=='cn'){
            $mobile = substr($mobile,2);
        }
        //dd($mobile);
        $url = config('ems.SENDCOULD_URL');
        if (!$code) {
            return false;
        }
        if ($area=='cn'){
            $msgType=0;
        }else{
            $msgType=2;
        }
        if ($type==3){
            if ($language=='cn'){
                if ($area=='cn'){
                    $templateId='25001';      //国内短信 中文注册
                }else{
                    $templateId = '27273';   //国际短信 中文注册
                }

            }elseif($language=='hk'){   //APP发过来的语言 是繁体
                if ($area=='cn'){    //国内手机号码
                    $templateId='28064';      //国内短信 繁体注册
                }else{
                    $templateId = '28070';   //国际短信 繁体注册
                }

            }
            else{
                if ($area=='cn'){
                    $templateId='26241';      //国内短信 英文注册
                }else{
                    $templateId = '24998';   //国际短信 英文注册
                }

            }
        }else{
            if ($language=='cn'){
                if ($area=='cn'){
                    $templateId='25139';         //国内短信中文普通
                }else{
                    $templateId = '26243';       //国际短信中文普通
                }
            }elseif ($language=='hk'){
                if ($area=='cn'){
                    $templateId='28065';         //国内短信繁体普通
                }else{
                    $templateId = '28067';       //国际短信繁体普通
                }
            }
            else{
                if ($area=='cn'){
                    $templateId='26242';         //国内短信英文普通
                }else{
                    $templateId = '25138';       //国际短信英文普通
                }
            }
        }
        $param = array(
            'smsUser' => config('ems.SENDCOULD_API_USERID'),
            'templateId' => $templateId,
            'msgType' => $msgType,
            'phone' => $mobile,
            'vars' => json_encode(['code'=>$code]),
        );

        $sParamStr = "";
        ksort($param);
        foreach ($param as $sKey => $sValue) {
            $sParamStr .= $sKey . '=' . $sValue . '&';
        }

        $sParamStr = trim($sParamStr, '&');
        $smskey = config('ems.SENDCOULD_PWD');
        $sSignature = md5($smskey."&".$sParamStr."&".$smskey);


        $param = array(
            'smsUser' => config('ems.SENDCOULD_API_USERID'),
            'templateId' => $templateId,
            'msgType' => $msgType,
            'phone' => $mobile,
            'vars' => json_encode(['code'=>$code]),
            'signature' => $sSignature
        );

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
        if ($area!=='cn'){
            $mobile = substr($mobile,2);
        }
        $mobiles = explode(',', $mobile);
        $data = array();
        foreach ($mobiles as $mobile) {
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
        }

        $result = DB::table('ems_code')->insert($data);
        return $result ? $code : false;
    }
}
