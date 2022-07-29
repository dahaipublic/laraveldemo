<?php

namespace App\Http\Controllers\Api;

use App\Models\FcmTokenInfo;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class BaseController extends Controller
{
    //
    /**
     * 22.1商家拥有的优惠券
     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----  |
    |device |是  |string | android或者ios |


    |返回示例|
    |:-----  |
    ```
    {
        "code": 200,
        "msg": "获取数据成功",
        "data": {
            "change_log": "We have added great features",
            "address": "http://127.0.0.1/Rapidzpay/Download/app-release-1.03.apk",
            "version": "1.0",
            "minVersion": "1.0",
        }
    }
    ```

      **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|----- |
    |address |string   |下载地址  |
    |version |string   |当前版本  |
    |minVersion |string   |最低版本  |
    |address |string   |详细地址  |
    |change_log |string   |更新日志  |
     * */
    public function checkVersion(Request $request)
    {
        $language = 'en';
        \App::setLocale($language);
        $device = $request->post('device');
        if(!in_array($device, ['ios', 'android'])){
            return response_json(400);
        }
        $where =[
            ['is_current', '=', '1'],
            ['device', '=', $device]
        ] ;
        $info = \DB::table('app_version')->where($where)->select('address','now_version', 'version', 'minVersion', 'change_log')->get()->toArray()[0];
        $redis = app("redis.connection");
        $uid = Auth('api')->id();
        $notice=$redis->get("notice".$uid);
        if($notice){
            $info->version=$info->version+0;
            $info->notice=1;
        }else{
            $info->notice=0;
            $firstSeconds = strtotime(date("Y-m-d H:i:s"));
            $lastSeconds = strtotime(date("Y-m-d 23:59:59"));
            $difference=$lastSeconds-$firstSeconds;
            $redis->setex("notice".$uid , $difference , 1);
        }
        $version=$request->header('Version','1.0');
        if($version < $info->minVersion){
            $info->type=3;
        }elseif ($version==$info->now_version){
            $info->type=1;
        }else{
            $info->type=2;
        }
        return response_json(200, trans('app.getDataSuccess'), $info);
    }


    //发送唤醒APP
    public function revive_program()
    {
        $FcmTokenInfo =  new FcmTokenInfo();
//        $start_time = date('Y-m-d H:i:s', strtotime('-6 minutes'));
//        $end_time = date('Y-m-d H:i:s', strtotime('-2 minutes'));
        $uid_list = $FcmTokenInfo->where('system', '1')->where('ping_time', '!=', 'empty')->select('uid', 'fcm_token')->get();

        // $this->ajaxReturn(['msg'=>$where]);
        foreach ($uid_list as $key => $value) {
            $a[] = $FcmTokenInfo->sendUserFcmuUp($value->uid, $value->fcm_token);
        }
        dump($a);
    }

    //ios唤醒app
    public function iosPush()
    {
        error_reporting(0);
        $deviceTokens = DB::table('fcm_token_info')
            ->select('fcm_token')
            ->where('system', 2)
            ->where('fcm_token', '<>', 'empty')
            ->get();
        $passphrase = config('api.IOS_PASSWORD');
        // Put your alert message here:
        $message = 'MICHOI_M_CLOUDTALK_';
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', public_path('ck.pem'));
        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
        stream_context_set_option($ctx, 'ssl', 'verify_peer', false);

        $fp = stream_socket_client(config('api.IOS_SSL'), $err, $errstr, 300, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
        if ($fp) {
            foreach ($deviceTokens as $value) {
                $deviceToken = $value->fcm_token;
                //$deviceToken = '18b9a0c9a00e008e022120d8b0bc34109952654c23ddb47ef5a65edd7f8f5ecd';
                $body['aps'] = array(
                    'content-available' => '1',
                    'alert' => $message,
                    'sound' => 'voip_call.caf',
                    'userName' => 'aa',
                    'isVideo' => 1,
                    'isCall' => 2,
                    'badge' => 10,
                );
                // Encode the payload as JSON
                $payload = json_encode($body);
                // Build the binary notification
                $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
                // Send it to the server
               fwrite($fp, $msg, strlen($msg));
            }
            fclose($fp);
            DB::table('fcm_token_info')
                ->where('system', 2)
                ->where('fcm_token', '<>', 'empty')
                ->update(['send_time' => date('Y-m-d H:i:s')]);
            return 200;
        } else {
            return 400;
        }
    }


}
