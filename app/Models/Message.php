<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;

class Message extends Model
{
    //
    protected $table = 'message';

    protected $primaryKey = 'id';

    //查询用户未读系统消息
    public static function system_message($uid, $type, $count)
    {

        $data = MessageRead::from('message as m')
            ->select("r.message_id", "r.read", "r.created_at", "m.title", "m.content", "m.type")
            ->join('message_read as r', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', $type)
            ->orderBy('r.id', 'desc')
            ->orderBy('r.read', 'asc')
            ->paginate($count)
            ->toArray();
        return $data;
    }

    //查询用户各种类型未读系统消息数量
    public static function get_count($uid)
    {
        $type1 = MessageRead::from('message_read as r')
            ->select()
            ->join('message as m', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', 1)
            ->where('r.read', 0)
            ->count();
        $type2 = MessageRead::from('message_read as r')
            ->select()
            ->join('message as m', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', 2)
            ->where('r.read', 0)
            ->count();
        $type3 = MessageRead::from('message_read as r')
            ->select()
            ->join('message as m', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', 3)
            ->where('r.read', 0)
            ->count();
        $type4 = MessageRead::from('message_read as r')
            ->select()
            ->join('message as m', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', 4)
            ->where('r.read', 0)
            ->count();
        $type5 = MessageRead::from('message_read as r')
            ->select()
            ->join('message as m', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', 5)
            ->where('r.read', 0)
            ->count();
        $count = $type1 + $type2 + $type4;
        $data = array(
            '1' => $type1,
            '2' => $type2,
            '3' => $type3,
            '4' => $type4,
            '5' => $type5,
            'count' => $count
        );
        return $data;
    }


    // http://www.hawu.me/coding/1396
    // 通知类型, 消息类型， 1 版本更新，2 活动通知， 3 通知，4 公告 ， 维护
    public static function getTypeStr($type = 1)
    {

        $type_str = '';
        switch ($type) {
            case 1:
                $type_str = trans('app.messageType1');// 版本更新
                break;
            case 2:
                $type_str = trans('app.messageType2');// 活动通知
                break;
            case 3:
                $type_str = trans('app.messageType3');// 通知
                break;
            case 4:
                $type_str = trans('app.messageType4');// 公告
                break;
            case 5:
                $type_str = trans('app.messageType5');// 维护
                break;
            case 6:
                $type_str = trans('app.messageType6');// 广告
                break;
            case 7:
                $type_str = trans('app.messageType7');// 轮播图
                break;
            default:
                $type_str = trans('app.messageType1');// 版本更新
                break;
        }

        return $type_str;

    }


    /**
     * @desc 发送安卓推送
     * @param $deviceToken
     * @param string $title
     * @param string $message
     * @return bool
     */
    public function sendAndroidTextMessage($to_uid = 0, $deviceToken = '', $title = '', $message = '', $system = 0)
    {

        if ($deviceToken) {

            $now_time = date('Y-m-d H:i:s');
            $headers = [
                'Content-Type:' . 'application/json',
                'Authorization:' . 'key=AAAAC3eR-s4:APA91bFZrUcDYAvZBJY2rizE9Btgy4tKyoEFSFPeqVL5lrzIG-a80XwZqcDs9-BApFrJGuIzK65KFa4K0ANYyxfcoVGM8xHUVJftz89RlijsL7AfYzchuQknuTgtnhhwwCF1ynaGhIin',
            ];
            $arr = array(
                'to' => $deviceToken,
                'data' => array(
                    'body' => json_encode(array(
                        'title' => $title,
                        'message' => $message,
                        'type' => 0,
                        'to_uid' => strval($to_uid)
                    ))
                )
            );
            $data_string = json_encode($arr);
            $curl = curl_init();  //初始化
            curl_setopt($curl, CURLOPT_URL, "http://fcm.googleapis.com/fcm/send");  //设置url
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);  //设置http验证方法
            curl_setopt($curl, CURLOPT_HEADER, 0);  //设置头信息
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);  //设置curl_exec获取的信息的返回方式
            curl_setopt($curl, CURLOPT_POST, 1);  //设置发送方式为post请求
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);  //设置post的数据
            $result = curl_exec($curl);
            if (curl_errno($curl)) {
                $error = curl_error($curl);
                $data = array(
                    'uid' => $to_uid,
                    'fcm_token' => $deviceToken,
                    'success' => 0,
                    'title' => $title,
                    'message' => $message,
                    'system' => $system,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                    'error' => json_encode($error, JSON_UNESCAPED_UNICODE)
                );
                PushRecord::insert($data);
                return false;
            }
            curl_close($curl);
            $result = json_decode($result, true);
            if (!empty($result['success'])) {
                $data = array(
                    'uid' => $to_uid,
                    'fcm_token' => $deviceToken,
                    'success' => 1,
                    'title' => $title,
                    'message' => $message,
                    'system' => $system,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                    'error' => json_encode($result, JSON_UNESCAPED_UNICODE)
                );
                PushRecord::insert($data);
                return true;
            } else {
                $data = array(
                    'uid' => $to_uid,
                    'fcm_token' => $deviceToken,
                    'success' => 0,
                    'title' => $title,
                    'message' => $message,
                    'system' => $system,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                    'error' => json_encode($result, JSON_UNESCAPED_UNICODE)
                );
                PushRecord::insert($data);
                return false;
            }
        }
        return false;

    }


    /**
     * @desc 发送苹果通知
     * @param string $deviceToken
     * @param string $title
     * @param string $message
     * @return bool
     */
    public function sendIOSTextMessage($to_uid = 0, $deviceToken = '', $title = '', $message = '', $system = 0)
    {

        try {

            if ($deviceToken) {

                //$to_uid = strval($to_uid);
                // $deviceToken = 'b3198fb967be54cdb2bd06fc21cb7e45fbc0e67af42a6ca5d85241b48848d763';
                $now_time = date('Y-m-d H:i:s');
                // Put your device token here (without spaces):
                // $deviceToken = 'c7877860c90a348f9bf7d985f0fb943d9491f3cf8cb0fc7e6d92a2163cd7a6aa';
                // $passphrase = '.12345678';
                $passphrase = config('api.IOS_PASSWORD');
                // Put your alert message here:
                ////////////////////////////////////////////////////////////////////////////////
                $ctx = stream_context_create();
                stream_context_set_option($ctx, 'ssl', 'local_cert', 'ck.pem');
                stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
                stream_context_set_option($ctx, 'ssl', 'verify_peer', false);

                // Open a connection to the APNS server
                $fp = stream_socket_client(config('api.IOS_SSL'), $err, $err_str, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
                if (!$fp) {
                    // exit("Failed to connect: $err $errstr" . PHP_EOL);
//                Log::useFiles(storage_path('sendIOSTextMessageConnect.log'));
//                Log::info("Failed to connect: $err $err_str" . PHP_EOL.', deviceToken: '.$deviceToken.', title: '.$title.' , message: '.$message);
                    $data = array(
                        'uid' => strval($to_uid),
                        'fcm_token' => $deviceToken,
                        'success' => 0,
                        'title' => $title,
                        'message' => $message,
                        'system' => $system,
                        'created_at' => $now_time,
                        'updated_at' => $now_time,
                        'error' => "Failed to connect: $err $err_str"
                    );
                    PushRecord::insert($data);
                    return false;
                }

                // echo 'Connected to APNS' . PHP_EOL;
                // Create the payload body
                $body['aps'] = array(
                    'content-available' => '1',
                    'mutable-content' => '1',
                    'title' => $title,
                    'alert' => $message,
                    'sound' => 'voip_call.caf',
                    'badge' => 10,
                    'to_uid' => strval($to_uid)
                );

                // Encode the payload as JSON
                $payload = json_encode($body);
                // Build the binary notification
                $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
                // Send it to the server
                $result = fwrite($fp, $msg, strlen($msg));
                // Close the connection to the server
                fclose($fp);

                // 发送是否成功
                if ($result) {
                    // 'Message successfully delivered' . PHP_EOL;
                    $data = array(
                        'uid' => $to_uid,
                        'fcm_token' => $deviceToken,
                        'success' => 1,
                        'title' => $title,
                        'message' => $message,
                        'system' => $system,
                        'created_at' => $now_time,
                        'updated_at' => $now_time,
                        'error' => json_encode($result, JSON_UNESCAPED_UNICODE)
                    );
                    PushRecord::insert($data);
                    return true;
                } else {
                    $data = array(
                        'uid' => $to_uid,
                        'fcm_token' => $deviceToken,
                        'success' => 0,
                        'title' => $title,
                        'message' => $message,
                        'system' => $system,
                        'created_at' => $now_time,
                        'updated_at' => $now_time,
                        'error' => 'Message not delivered'
                    );
                    PushRecord::insert($data);
                    // 'Message not delivered' . PHP_EOL;
                    return false;
                }
            } else {
                return false;
            }

        } catch (\Exception $exception) {

            Log::useFiles(storage_path('sendIOSTextMessage.log'));
            Log::info('to_uid: ' . $to_uid . ', deviceToken: ' . $deviceToken . ', title: ' . $title . ' , message: ' . $message . ' , system: ' . $system . ', message:' . $message . ',message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());
            return false;

        }

    }


    /**
     * @desc 发送语音or视频通话
     * @param string $deviceToken
     * @param string $username
     * @param int $isVideo
     * @param int $isCall
     * @return bool
     */
    public function sendIOSVoiceMessage($to_uid = 0, $deviceToken = '', $to_username = '', $isVideo = 1, $isCall = 2)
    {

        try {

            // $passphrase = '.12345678';
            $passphrase = config('api.IOS_PASSWORD');
            // Put your alert message here:
            $message = 'MICHOI_M_CLOUDTALK_';
            $ctx = stream_context_create();
            stream_context_set_option($ctx, 'ssl', 'local_cert', public_path('ck.pem'));
            stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
            stream_context_set_option($ctx, 'ssl', 'verify_peer', false);

            $fp = stream_socket_client(config('api.IOS_SSL'), $err, $err_str, 300, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
            if (!$fp) {
                // exit("Failed to connect: $err $errstr" . PHP_EOL);
                Log::useFiles(storage_path('sendIOSVoiceMessageConnect.log'));
                Log::info("Failed to connect: $err $err_str" . PHP_EOL . ', deviceToken: ' . $deviceToken . ', to_username: ' . $to_username . ' , message: ' . $message);
                return false;
            }

            $body['aps'] = array(
                'content-available' => '1',
                'mutable-content' => '1',
                'alert' => $message,
                'sound' => 'voip_call.caf',
                'userName' => $to_username, // 对方通话人名字
                'isVideo' => strval($isVideo), // 是否为视频通话, 1视频通话, 0语音通话
                'isCall' => $isCall,  // 1 接通, 2 拒绝
                'badge' => 10,
                'to_uid' => strval($to_uid)
            );
            // Encode the payload as JSON
            $payload = json_encode($body);
            // Build the binary notification
            $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;
            // Send it to the server
            $result = fwrite($fp, $msg, strlen($msg));
            fclose($fp);

            Log::useFiles(storage_path('sendIOSVoiceMessage.log'));
            Log::info('Message not delivered' . PHP_EOL . ', deviceToken: ' . $deviceToken . ', to_username: ' . $to_username . ' , isVideo: ' . $isVideo . ' , isCall: ' . $isCall . ', result: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

            // 发送是否成功
            if ($result) {
                return true;
            } else {
                return false;
            }

        } catch (\Exception $exception) {

            // $to_uid = 0, $deviceToken = '', $to_username = '', $isVideo = 1, $isCall = 2
            Log::useFiles(storage_path('sendIOSVoiceMessage.log'));
            Log::info('to_uid: ' . $to_uid . ', deviceToken: ' . $deviceToken . ', to_username: ' . $to_username . ' , isVideo: ' . $isVideo . ' , isCall: ' . $isCall . ', message:' . $message . ',message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());
            return false;

        }

    }


    /**
     * @desc 发送推送信息
     * @param $to_uid
     * @param $title
     * @param $message
     * @return bool
     */
    public function _sendMessageText($to_uid, $title = '', $message = '')
    {

        try {

            $token_info = FcmTokenInfo::where('uid', $to_uid)
                ->where('fcm_token', '<>', 'empty')
                ->orderBy('updated_at', 'desc')
                ->first();

            Log::useFiles(storage_path('_sendMessageText.log'));
            Log::info('to_uid:' . $to_uid . ', title:' . $title . ', message:' . $message . ', token_info' . json_encode($token_info, JSON_UNESCAPED_UNICODE));

            // 1：安卓 2：iOS
            if (!empty($token_info)) {
                $deviceToken = $token_info->fcm_token;
                $system = $token_info->system;
                if ($token_info->system == 1) {
                    // eFh0xP83WZA:APA91bEPhD-4OA0HLc4jGhUhKH8WSFfoq2ggMOGIjW-6gQj2-uLZuvhuyYFzDz5wn4xTM8Nhj-6JAp7tbJqZuaOaeOjuvdrwnmM4oSMTCajCoJnm3zn6xXrhVm9BxcZsTfN6Cpb1Eyr1
                    $is_send = $this->sendAndroidTextMessage($to_uid, $deviceToken, $title, $message, $system);
                } else {
                    // $deviceToken = 'c2eb53592504488218ff41ae6de219fcde1fade5279bdc322eaac7eea49c6ccf'; 我的设备
                    // d840b3b339afdb8b577dbb649e9eb6c7945bb4a979c52a7d674ab36af704594b
                    $is_send = $this->sendIOSTextMessage($to_uid, $deviceToken, $title, $message, $system);
                }
                if ($is_send) {
                    return true;
                } else {
                    return false;
                }
            } else {
                $now_time = date('Y-m-d H:i:s');
                $data = array(
                    'uid' => $to_uid,
                    'fcm_token' => '',
                    'success' => 0,
                    'title' => $title,
                    'message' => $message,
                    'system' => 0,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                    'error' => 'Not fcm_token'
                );
                PushRecord::insert($data);
                return false;
            }
        } catch (\Exception $exception) {

            Log::useFiles(storage_path('sendMessageText.log'));
            Log::info('to_uid:' . $to_uid . ', title:' . $title . ', message:' . $message . ',message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());

        }

    }


    /**
     * @desc 获取转账的html模板
     * @param $lang
     * @param $transfer_account
     * @param $unit
     * @param string $to_username
     * @return string
     */
    public function getSendBlade($lang, $transfer_account, $unit, $to_username = '')
    {

        App::setLocale($lang);
        $sending = trans('app.sending');
        $yourTransactionTo = trans('app.yourTransactionTo');
        $thisIsAnAutomatedMailToNotifyYouOfTheTransactionYouHaveInitiated = trans('app.thisIsAnAutomatedMailToNotifyYouOfTheTransactionYouHaveInitiated');
        $regards = trans('app.regards');
        $rapidzTeam = trans('app.rapidzTeam');
        $getRapidzPayOnYourPhone = trans('app.getRapidzPayOnYourPhone');
        $followUs = trans('app.followUs');
        $success = trans('app.success');

        $html_str = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width"/>
    <title>hamdantoken</title>
    <link rel="Shortcut Icon" href="https://apptest.hamdantoken.io/img/emailimg/logoIcon.png">

</head>
<body style="min-height: 100vh;display: flex;align-items: center;margin: 0;padding: 0;">
<style>
	#tds{
    	padding-top:28px;
    }
    .bottom{
    	
    	margin:25px 10px;
    	height:6px;
    	background: url(https://apptest.hamdantoken.io/img/emailimg/bottom.png) no-repeat;
    	background-size: 100%;
    }
    @media screen and (max-width: 768px){
        .box{
            padding-left: 85px !important;
        }
        .box table{
            margin-right: auto;
            width: 100%;
            text-align: center !important;
        }
        .box table:nth-of-type(1) {
            margin-bottom: 15px;
        }
        .box table a{
            margin: auto;
        }
        .box table:nth-of-type(3) td {
            padding-left: 0 !important;
        }
        #tds{
        	padding-top:0px;
        }
    }
</style>
<div style="position: relative;background: url('https://apptest.hamdantoken.io/img/emailimg/top.png') no-repeat;margin: auto;width: 600px;color:#2E2F32;font-size:16px;background-size: 100% ;font-weight:none;font-family: arial,sans-serif,'Microsoft Yahei';"">
    <layout label="header">
        <table cellspacing="0" cellpadding="0" border="0" width="70%" style="margin: auto">
            <tbody>
            <tr>
                <td colspan="1" align="center" style="padding-top: 70px;padding-bottom: 40px;">
                   <!-- <img src="img/logo.png" style="width: 164px;max-width: 100%;" alt="Logo">-->
                </td>
            </tr>
            </tbody>
        </table>
    </layout>


    <table cellspacing="0" cellpadding="0" border="0" width="80%" style="margin: auto">
        <tbody>
       	
       	<tr>
            <td colspan="3" id="tds" style="text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:bold;font-family:Arial;font-size: 18px;word-break: break-word;word-wrap:break-word">
             Sending %Amount% %Crypto ticker% 

            </td>
        </tr>
        <tr>
            <td colspan="3" style="padding-top:12px;text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:400;font-family:Arial;font-size: 14px;word-break: break-word;word-wrap:break-word">
             Your transaction to %Receiving Wallet Address% has begun.
            </td>
        </tr>
        <tr>
            <td colspan="3" id="tds" style="padding-top:10px;text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:400;font-family:Arial;font-size: 14px;word-break: break-word;word-wrap:break-word">
              
This is an automated mail to notify you of the transaction you have initiated. 


            </td>
        </tr>
        <tr>
            <td style="line-height: 24px;word-spacing: 2px;font-size: 16px;padding-top: 50px;">Regards,</td>
        </tr>
        <tr>
            <td style="line-height: 24px;word-spacing: 2px;font-size: 16px">Hamdantoken Team</td>
        </tr>
       
        </tbody>
    </table>
    
    <div class="bottom"></div>
</div>
</body>
</html>
EOD;

        return $html_str;

    }


    public function getReceiveBlade($lang, $transfer_account, $unit)
    {

        App::setLocale($lang);
        $receiving = trans('app.receiving');
        $regards = trans('app.regards');
        $rapidzTeam = trans('app.rapidzTeam');
        $getRapidzPayOnYourPhone = trans('app.getRapidzPayOnYourPhone');
        $followUs = trans('app.followUs');
        $success = trans('app.success');
        $yourTransactionHasBegun = trans('app.yourTransactionHasBegun');
        $thisIsAnAutomatedMailToTotifyYouOfAnIncomingTransaction = trans('app.thisIsAnAutomatedMailToTotifyYouOfAnIncomingTransaction');
        $whichIsInTheProcessOfBeingConfirmedOnTheBlockchain = trans('app.whichIsInTheProcessOfBeingConfirmedOnTheBlockchain');

        $html_str = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width"/>
    <title>hamdantoken</title>
    <link rel="Shortcut Icon" href="https://apptest.hamdantoken.io/img/emailimg/logoIcon.png">

</head>
<body style="min-height: 100vh;display: flex;align-items: center;margin: 0;padding: 0;">
<style>
	#tds{
    	padding-top:28px;
    }
    .bottom{
    	
    	margin:25px 10px;
    	height:6px;
    	background: url(https://apptest.hamdantoken.io/img/emailimg/bottom.png) no-repeat;
    	background-size: 100%;
    }
    @media screen and (max-width: 768px){
        .box{
            padding-left: 85px !important;
        }
        .box table{
            margin-right: auto;
            width: 100%;
            text-align: center !important;
        }
        .box table:nth-of-type(1) {
            margin-bottom: 15px;
        }
        .box table a{
            margin: auto;
        }
        .box table:nth-of-type(3) td {
            padding-left: 0 !important;
        }
        #tds{
        	padding-top:0px;
        }
    }
</style>
<div style="position: relative;background: url('https://apptest.hamdantoken.io/img/emailimg/top.png') no-repeat;margin: auto;width: 600px;color:#2E2F32;font-size:16px;background-size: 100% ;font-weight:none;font-family: arial,sans-serif,'Microsoft Yahei';"">
    <layout label="header">
        <table cellspacing="0" cellpadding="0" border="0" width="70%" style="margin: auto">
            <tbody>
            <tr>
                <td colspan="1" align="center" style="padding-top: 70px;padding-bottom: 40px;">
                   <!-- <img src="img/logo.png" style="width: 164px;max-width: 100%;" alt="Logo">-->
                </td>
            </tr>
            </tbody>
        </table>
    </layout>


    <table cellspacing="0" cellpadding="0" border="0" width="80%" style="margin: auto">
        <tbody>
       	
       	<tr>
            <td colspan="3" id="tds" style="text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:bold;font-family:Arial;font-size: 18px;word-break: break-word;word-wrap:break-word">
             Receiving %Amount% %Crypto ticker% 

            </td>
        </tr>
        <tr>
            <td colspan="3" style="padding-top:10px;text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:400;font-family:Arial;font-size: 14px;word-break: break-word;word-wrap:break-word">
             Your transaction has begun.

            </td>
        </tr>
        <tr>
            <td colspan="3" id="tds" style="padding-top:10px;text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:400;font-family:Arial;font-size: 14px;word-break: break-word;word-wrap:break-word">
              
This is an automated mail to notify you of an incoming transaction, which is in the process of being confirmed on the blockchain.

            </td>
        </tr>
        <tr>
            <td style="line-height: 24px;word-spacing: 2px;font-size: 16px;padding-top: 50px;">Regards,</td>
        </tr>
        <tr>
            <td style="line-height: 24px;word-spacing: 2px;font-size: 16px">Hamdantoken Team</td>
        </tr>
       
        </tbody>
    </table>
    
    <div class="bottom"></div>
</div>
</body>
</html>
EOD;

        return $html_str;


    }


    /**
     * @desc 获取发送验证码页面
     * @param $data
     * @return string
     */
    public function getCodeBlade($data)
    {

        $verifyYouEmail = trans('app.verifyYouEmail');
        $enterYourCodeToCompletReg = trans('app.enterYourCodeToCompletReg');
        $enterYourCodeToCompletVerify = trans('app.enterYourCodeToCompletVerify');
        if ($data->type == 3) {
            $enterYourCodeToComplet = $enterYourCodeToCompletReg;
        } else {
            $enterYourCodeToComplet = $enterYourCodeToCompletVerify;
        }

        $regards = trans('app.regards');
        $rapidzTeam = trans('app.rapidzTeam');
        $getRapidzPayOnYourPhone = trans('app.getRapidzPayOnYourPhone');
        $followUs = trans('app.followUs');
        $success = trans('app.success');
        $yourTransactionHasBegun = trans('app.yourTransactionHasBegun');

        $html_str = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width"/>
    <title>hamdantoken</title>
    <link rel="Shortcut Icon" href="https://apptest.hamdantoken.io/img/emailimg/logoIcon.png">

</head>
<body style="min-height: 100vh;display: flex;align-items: center;margin: 0;padding: 0;">
<style>
	#tds{
    	padding-top:28px;
    }
    .bottom{
    	
    	margin:25px 10px;
    	height:6px;
    	background: url(https://apptest.hamdantoken.io/img/emailimg/bottom.png) no-repeat;
    	background-size: 100%;
    }
    @media screen and (max-width: 768px){
        .box{
            padding-left: 85px !important;
        }
        .box table{
            margin-right: auto;
            width: 100%;
            text-align: center !important;
        }
        .box table:nth-of-type(1) {
            margin-bottom: 15px;
        }
        .box table a{
            margin: auto;
        }
        .box table:nth-of-type(3) td {
            padding-left: 0 !important;
        }
        #tds{
        	padding-top:0px;
        }
    }
</style>
<div style="position: relative;background: url('https://apptest.hamdantoken.io/img/emailimg/top.png') no-repeat;margin: auto;width: 600px;color:#2E2F32;font-size:16px;background-size: 100% ;font-weight:none;font-family: arial,sans-serif,'Microsoft Yahei';"">
    <layout label="header">
        <table cellspacing="0" cellpadding="0" border="0" width="70%" style="margin: auto">
            <tbody>
            <tr>
                <td colspan="1" align="center" style="padding-top: 70px;padding-bottom: 40px;">
                   <!-- <img src="img/logo.png" style="width: 164px;max-width: 100%;" alt="Logo">-->
                </td>
            </tr>
            </tbody>
        </table>
    </layout>


    <table cellspacing="0" cellpadding="0" border="0" width="80%" style="margin: auto">
        <tbody>
       	
       	<tr>
            <td colspan="3" id="tds" style="text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:bold;font-family:Arial;font-size: 18px;word-break: break-word;word-wrap:break-word">
             Verify your email address
            </td>
        </tr>
        
        <tr>
            <td colspan="3" id="tds" style="text-align:center;color:#333333;line-height: 26px;word-spacing: 2px;font-weight:400;font-family:Arial;font-size: 14px;word-break: break-word;word-wrap:break-word">
              Key in the following verification code in your Hamdantoken  App.
            </td>
        </tr>
        <tr>
            <td style="text-align: center;padding-top:28px">
              <span style="background:red;color:#fff;padding:5px 10px;cursor: pointer;">{$data->code}</span>
            </td>
        </tr>
        <tr>
            <td style="line-height: 24px;word-spacing: 2px;font-size: 16px;padding-top: 50px;">Regards,</td>
        </tr>
        <tr>
            <td style="line-height: 24px;word-spacing: 2px;font-size: 16px">Hamdantoken Team</td>
        </tr>
       
        </tbody>
    </table>
    
    <div class="bottom"></div>
</div>
</body>

</html>

EOD;

        <<<EOD

EOD;

//        if($_SERVER['HTTP_HOST'] != 'app.chain-chat.app') {
//            $html_str = <<<EOD
//<body>$data->code</body>
//EOD;
//        }

        return $html_str;

    }

    /**
     * @desc 发送推送信息
     * @param int $to_uid 目标用户id
     * @param string $title 标题
     * @param string $message 内容
     * @param int $os 目标APP的操作系统
     *
     *
     * @return bool
     */
    public function sendMessageText($to_uid, $title = '', $message = '', $os = 0)
    {

        try {

            // 1：安卓 2：iOS
            if (!in_array($os, [1, 2])){
                throw new \Exception('无法获取当前用户的app操作系统');
            }

            $notification = [
                'title' => $title,
                'body' => $message
            ];
            $os = $os == 1 ? 'Android' : 'IOS';
            $res = push_notifications($to_uid, $notification, $os);
            if (empty($res->publishId)) {
                throw new \Exception('没有返回publishId');
            }

            $now_time = date('Y-m-d H:i:s');
            $data = [
                'uid' => $to_uid,
                'success' => 1,
                'title' => $title,
                'message' => $message,
                'system' => $os,
                'created_at' => $now_time,
                'updated_at' => $now_time,
                'publish_id' => $res->publishId,
            ];
            PushRecord::insert($data);
            return true;

        } catch (\Exception $exception) {

            Log::useFiles(storage_path('sendMessageText.log'));
            Log::info('to_uid:' . $to_uid . ', title:' . $title . ', body:' . $message . ', OS：' . $os . ', error_message:' . $exception->getMessage() . ', file:' . $exception->getFile() . ', line:' . $exception->getLine());
            return false;
        }

    }

}
