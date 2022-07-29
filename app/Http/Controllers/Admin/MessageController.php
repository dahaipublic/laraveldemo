<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\InsertMessageRead;
use App\Libs\Easemob;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\TaskMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\SystemOperationLog;
use Illuminate\Validation\Rule;

/**
 * @group 59发送通知
 * - author whm
 */

class MessageController extends Controller
{

    /**
     * 59.1、后台发送通知
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |title |是  |string | 通知标题    |
    |content |是  |string | 通知内容    |
    |type |是  |int |  通知类型, 消息类型， 1 版本更新，2 活动通知， 3 通知，4 公告 ， 5 维护 , 6 广告, 7 轮播图 |
    |lang |是  |string | 语言，cn 中文,en 英文, hk繁体，后面可能会加：fa泰文,es西班牙语,fr法国，kr韩语，ru俄罗斯语，de德语，vn越南语，tr土耳其语，nl荷兰语，pt葡萄牙语，it意大利语, pl波兰语 |
    |start_time |否  |string | 维护开始时间 type == 5 |
    |end_time |否  |string |维护结束时间 type == 5  |
    |ios_version |否  |string |ios 版本 type == 1  |
    |android_version |否  |string |android版本 type == 1  |
    |ios_download |否  |string |ios下载地址 type == 1  |
    |android_download |否  |string |android 下载地址 type == 1  |
    |forced_update |否  |string |forced_update， 0否，1是 type == 1  |

    |返回示例|
    |:-----  |
    ```
    {
    "code": 200,
    "msg": "添加成功"
    }
    ```

     **返回参数说明**
     *
    |参数名|类型|说明|
    |:-----  |:-----|----- |
     */
    public function add(Request $request){

        $account_id = (int)\Auth::guard('admin')->id(); // 管理员id
        $admin = \Auth::guard('admin')->user();

        if($request->input('type') <= 5){
            $validator = Validator::make($request->all(), [
                'type' => 'required|int|min:1|max:7',
                'title' => 'required|string',
                'content' => 'required|string',
                //'thumb' => 'required|string',
                'lang' => 'required|string',
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'type' => 'required|int|min:1|max:7',
                'title' => 'required|string',
                'thumb' => 'required|string',
            ]);
        }

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $redis_key = 'admin_message_add'.$account_id;
        if(Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])){

            try{

                // type  消息类型， 1 版本更新，2 活动通知， 3 通知，4 公告 ，5 维护 , 6 广告
                $type = $request->input('type');
                $title = trim($request->input('title'));
                $content = htmlspecialchars($request->input('content', ''));
                $lang = $request->input('lang', 'cn');
                $thumb = $request->input('thumb', 'storage/img/banner2.png');
                $maintian_type = 0;

                $now_time = date('Y-m-d H:i:s');
                $data = array(
                    'account_id' => $account_id,
                    'title' => $title,
                    'thumb' => '',
                    'content' => $content,
                    'type' => $type,
                    'lang' => $lang,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                );
                if(!empty($thumb)){
                    if($_SERVER['HTTP_HOST']=='supmin.chain-chat.app'){
                        $img_data = getimagesize(url($thumb));
                    } else {
                        $img_data = @getimagesize(url($thumb));
                    }

                    $temp_arr = array();
                    $temp_arr['url'] = $thumb;
                    $temp_arr['width'] = $img_data[0];
                    $temp_arr['height'] = $img_data[1];
                    $data['thumb'] = json_encode($temp_arr);
                }

                if($type == 1){
                    $validator = Validator::make($request->all(), [
                        'ios_version' => 'required|string',
                        'android_version' => 'required|string',
                        'ios_download' => 'required|string',
                        'android_download' => 'required|string',
                        'forced_update' => 'required|int|min:0|max:1',
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $data['ios_version'] = trim($request->input('ios_version', ''));
                    $data['android_version'] = trim($request->input('android_version', ''));
                    $data['ios_download'] = trim($request->input('ios_download', ''));
                    $data['android_download'] = trim($request->input('android_download', ''));
                    $data['forced_update'] = intval($request->input('forced_update', 0));
                }
                elseif($type == 5){
                    $validator = Validator::make($request->all(), [
                        'start_time' => 'required',
                        'end_time' => 'required',
                        'maintian_type' => [
                            'required',
                            Rule::in([1,2,3])
                        ],
                    ]);
                    if ($validator->fails()) {
                        return response_json(402, $validator->errors()->first());
                    }
                    $start_time = strtotime($request->input('start_time'));
                    $end_time = strtotime($request->input('end_time'));
                    $maintian_type = intval($request->input('maintian_type'));
                    if(empty($start_time) || empty($end_time)){
                        Redis::del($redis_key);
                        return response_json(402, trans('web.timeCanNotBeEmpty'));
                    }
                    else if($start_time >= $end_time){
                        Redis::del($redis_key);
                        return response_json(403, trans('web.startTimeMustBeLessThanEndTime'));
                    }
                    $data['start_time'] = date('Y-m-d H:i:s', $start_time);
                    $data['end_time'] = date('Y-m-d H:i:s', $end_time);
                    $data['maintian_type'] = $maintian_type; // 维护类型，1 app ，2 用户管理， 3 app和用户管理同时维护
                }elseif ($type == 6 || $type == 7){
                    if(empty($thumb)){
                        return response_json(402, trans('web.pleaseUploadPictures'));
                    }
                    // 最多上传七张
                    $upload_count = Message::where('type', $type)->where('is_del', 0)->count("id");
                    if($upload_count >= 7){
                        return response_json(402, trans('web.upToSevenUploads'));
                    }
                }
                $message_id = Message::insertGetId($data);

                if($message_id){

                    $task = array(
                        'message_id' => $message_id,
                        'is_insert' => 0,
                        'created_at' => $now_time,
                        'updated_at' => $now_time
                    );
                    TaskMessage::insert($task);

//                    if($type <= 5){
//                        if($type != 1){
//
//                            // // 维护类型，1 app ，2 用户管理， 3 app和用户管理同时维护
//                            if($type != 5){
//                                $users = collect(User::select("id", "easemob_u")
//                                    ->where('language', $lang)
//                                    ->where('status', 1)
//                                    ->where('sreceive_notice', 1) // 接收通知:1为接受通知，2为不接受通知
//                                    ->get()
//                                    ->toArray());
//                            }else{
//                                if($maintian_type == 1 || $maintian_type == 3){ //
//                                    $users = collect(User::select("id", "easemob_u")
//                                        ->where('language', $lang)
//                                        ->where('status', 1)
//                                        ->get()
//                                        ->toArray());
//                                }else{
//                                    $users = collect(User::select("id", "easemob_u")
//                                        ->where('language', $lang)
//                                        ->where('status', 1)
//                                        ->where('customer_type', 2) // 1为普通用户，2为用户管理用户
//                                        ->get()
//                                        ->toArray());
//                                }
//                            }
//
//                            $Easemob = new Easemob();
//                            $list = $users->chunk('1000')->toArray();
//
//                            if($type != 5){
//                                if(!empty($list)){
//                                    foreach ($list as $users){
//                                        set_time_limit(0);
//                                        $temp = array();
//                                        foreach ($users as $user){
//                                            $temp[] = array(
//                                                'message_id' => $message_id,
//                                                'uid' => $user['id'],
//                                                'created_at' => $now_time,
//                                                'updated_at' => $now_time,
//                                            );
//                                        }
//                                        // 批量插入
//                                        $insert = MessageRead::insert($temp);
//                                        if($insert){
//                                            $from = 'admin';
//                                            $target_type = "users";
//                                            $action = 'com.rapidzpay.SystemMessage';
//                                            $ext['type'] = "message";
//                                            $target = array_column($users, 'easemob_u');
//                                            $res = $Easemob->sendCmd($from, $target_type, $target, $action, $ext);// 文本消息
//                                            // 记录LOG
//                                            Log::useFiles(storage_path('adminMessageAddRecord.log'));
//                                            Log::info('error:0' . ', from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                                                . ', content:' . $content . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));
//                                            if (isset($res['error'])) {
//                                                // 记录LOG
//                                                Log::useFiles(storage_path('adminMessageAddRecordError.log'));
//                                                Log::info('error:1' . ', from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                                                    . ', content:' . $content . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));
//                                            }
//                                        }
//                                    }
//                                }
//
//                            }else{
//                                if(!empty($list)){
//
//                                    App::setLocale($lang);
//                                    foreach ($list as $users){
//                                        set_time_limit(0);
//                                        $temp = array();
//                                        foreach ($users as $user){
//                                            $temp[] = array(
//                                                'message_id' => $message_id,
//                                                'uid' => $user['id'],
//                                                'created_at' => $now_time,
//                                                'updated_at' => $now_time,
//                                            );
//                                        }
//                                        // 批量插入
//                                        $insert = MessageRead::insert($temp);
//                                        if($insert && $type == 4){
//                                            $from = 'admin';
//                                            $target_type = "users";
//                                            $action = 'com.chain.community.consultation';
//                                            $ext['title'] = $title;
//                                            $ext['portrait_uri'] = url($thumb);
//                                            $ext['content'] = htmlspecialchars_decode($content);
//                                            $ext['noticeType'] = 1;
//                                            $target = array_column($users, 'easemob_u');
//                                            $res = $Easemob->sendCmd($from, $target_type, $target, $action, $ext);// 文本消息
//                                            // 记录LOG
//                                            Log::useFiles(storage_path('adminMessageAddRecord.log'));
//                                            Log::info('error:0' . ', from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                                                . ', content:' . $content . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));
//                                            if (isset($res['error'])) {
//                                                // 记录LOG
//                                                Log::useFiles(storage_path('adminMessageAddRecordError.log'));
//                                                Log::info('error:1' . ', from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                                                    . ', content:' . $content . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));
//                                            }
//                                        }
//                                    }
//                                }
//                            }
//
//                        }
//                    }
                    $data['maintian_type'] = $maintian_type;
                    $data['message_id'] = $message_id;
                    // InsertMessageRead::dispatch($data)->onQueue('message');

                    //发送通知日志
                    // type  消息类型， 1 版本更新，2 活动通知， 3 通知，4 公告 ，5 维护 , 6 广告
                    $name = '';
                    $msg = '';
                    if ($type == 1){
                        $name = '版本更新';
                    }elseif($type == 2){
                        $name = '活动通知';
                    }elseif($type == 3){
                        $name = '通知';
                    }elseif($type == 4){
                        $name = '公告';
                    }elseif($type == 5){
                        $name = '维护';
                    }elseif($type == 6){
                        $name = '广告';
                    }
                    // 记录日志
                    if ($admin->language == 'cn'){
                        $msg = '管理员'.$admin->username.'发送'.$name;
                    }elseif($admin->language == 'hk'){
                        $msg = '管理员'.$admin->username.'发送'.$name;
                    }else{
                        $msg = 'Administrators '.$admin->username.'发送'.$name;
                    }
                    SystemOperationLog::add_log($admin->id, $request, $msg);
                    return response_json(200, trans('web.addSuccess'));

                }else{

                    return response_json(200, trans('web.addFail'));

                }

            }catch(\Exception $exception) {

                $input = $request->all();
                Log::useFiles(storage_path('adminMessageAdd.log'));
                Log::info('account_id: '.$account_id.', input:'.json_encode($input, JSON_UNESCAPED_UNICODE).',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

            }

        }else{
            return response_json(402,trans('web.accessFrequent'));
        }

    }



    /**
     * 59.2、发送通知列表
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |page |是  |int | 分页    |

    |返回示例|
    |:-----  |
    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": {
    "list": [
    {
    "message_id": 1,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 2,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 3,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 4,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 5,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 6,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 7,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 8,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 9,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 10,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 11,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 12,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 13,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 14,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 15,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    },
    {
    "message_id": 16,
    "title": "这是一个版本更新",
    "content": "版本更新内容",
    "type": 1,
    "created_at": "2018-10-31 16:06:47",
    "username": "admin",
    "type_str": "版本更新"
    }
    ],
    "total": 46,
    "last_page": 3
    }
    }
    ```

     **返回参数说明**
     *
    |参数名|类型|说明|
    |:-----  |:-----|----- |
    |message_id |int   |通知id  |
    |title |string   |标题  |
    |content |string   |通知内容  |
    |type |int   |通知类型, 消息类型， 1 版本更新，2 活动通知， 3 通知，4 公告, 5 维护  |
    |type_str |string   |通知中文说明类型  |
    |username |string   |管理员  |
    |created_at |string   |创建时间  |
     */
    public function getMessageList(Request $request){

        $page = intval($request->input('page',1));

        $validator = Validator::make($request->all(), [
            'type' => 'required|int', // 4 公告, 6 广告, 7 轮播图
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $type = $request->input('type', 0);
        $data = Message::from("message as m")
            ->select("m.id as message_id", "m.title", "m.content", "m.type", "m.thumb", "m.created_at", "a.username")
            ->join('admin_accounts as a', 'm.account_id', '=', 'a.id')
            ->where('m.type', $type)
            ->where('m.is_del', 0)
            ->orderBy('m.created_at', 'desc')
            ->orderBy('m.id', 'desc')
            ->paginate(16)
            ->toArray();

        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];

        if(!empty($list)){
            foreach ($list as $key => &$item){
                $item['type_str'] = trans('web.messageType'.$item['type']);
                $item['content'] = htmlspecialchars_decode($item['content']);
                if(!empty($item['thumb'])){
                    $item['thumb'] = json_decode($item['thumb'], true);
                }else{
                    $item['thumb'] = array();
                }
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page
        ));

    }


    // 维护公告须有一个关闭维护的按钮
    public function closeMaintenance(Request $request){

        $validator = Validator::make($request->all(), [
            'message_id' => 'required|int',
            'close' => 'required|int|min:0|max:1',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $message_id = $request->input('message_id');
        $close = $request->input('close', 0);

        $message = Message::select("id as message_id")
            ->where('type', 5)
            ->where('id', $message_id)
            ->first();
        if(empty($message)){
            return response_json(403, trans('web.noSuchMaintenance'));
        }

        $update = Message::where('id', $message_id)->update([
            'close' => $close
        ]);
        if($update){
            return response_json(200, trans('web.success'));
        }else{
            return response_json(200, trans('web.fail'));
        }

    }


    // 删除通知/广告
    public function delMessage(Request $request){

        $admin = Auth('admin')->user();

        $validator = Validator::make($request->all(), [
            'message_id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $message_id = $request->input('message_id');
        $message = Message::select("id as message_id")
            ->where('id', $message_id)
            ->first();
        if(empty($message)){
            return response_json(403, trans('web.noSuchMaintenance'));
        }

        // 记录日志
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'删除通知：'.$message_id;
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'删除通知：'.$message_id;
        }else{
            $msg = 'Administrators '.$admin->username.'删除通知：'.$message_id;
        }
        SystemOperationLog::add_log($admin->id, $request, $msg);

        Message::where('id', $message_id)->update([
           'is_del' => 1
        ]);

        return response_json(200, trans('web.delSuccess'));

    }


    //


}
