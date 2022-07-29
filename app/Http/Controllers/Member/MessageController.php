<?php

namespace App\Http\Controllers\Member;

use App\Models\Message;
use App\Models\MessageRead;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
 * @group 通知
 * - author whm
 */

class MessageController extends Controller
{


    // 获取系统消息未读数量
    public function getNoReadMessageNumber(Request $request){
        
        $user = Auth::guard('member')->user();
        $uid = $user->id;

        if(empty($user->message_read_add)){
            $read_model = new MessageRead();
            $read_model->addBeforeMessage($user);
        }

        $no_read_number = MessageRead::from('message as m')
            ->select("r.message_id", "r.read", "r.created_at", "m.title", "m.content", "m.type")
            ->join('message_read as r', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('r.read', 0)
            ->where('m.type', 4) // 通知页面只显示公告
            ->where('m.is_del', 0)
            ->count("m.id");

        return response_json(200, trans('web.getDataSuccess'), array(
            'no_read_number' => $no_read_number
        ));
        
    }


    // 我的系统消息列表
    public function getMessageList(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(),[
            'type' => 'required|int|min:1',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        if(empty($user->message_read_add)){
            $read_model = new MessageRead();
            $read_model->addBeforeMessage($user);
        }

        $type = $request->input('type', 0);
        $data = MessageRead::from('message as m')
            ->select("r.message_id", "r.read", "r.created_at", "m.title", "m.content", "m.type")
            ->join('message_read as r', 'r.message_id', '=', 'm.id')
            ->where('r.uid', $uid)
            ->where('m.type', $type)
            ->where('m.is_del', 0)
            ->orderBy('r.created_at', 'desc')
            ->orderBy('r.read', 'asc')
            ->paginate(10)
            ->toArray();

        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        if(!empty($list)){
            foreach ($list as $key => $item){
                $list[$key]['type_str'] = Message::getTypeStr($item['type']);
                $list[$key]['content'] = htmlspecialchars_decode($item['content']);
            }
        }

        return response_json(200, trans('web.getDataSuccess'),
            array(
                'list' => $list,
                'total' => $total,
                'last_page' => $last_page
            )
        );

    }



    // 我的系统通知详情
    public function getMessageDetails(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'message_id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $message_id = intval($request->input('message_id', 0));
        $message = MessageRead::from('message as m')
            ->select("r.message_id", "r.read", "r.created_at", "m.title", "m.content", "m.type")
            ->join('message_read as r', 'r.message_id', '=', 'm.id')
            ->where('r.message_id', $message_id)
            ->where('r.uid', $uid)
            ->where('m.type', '>', 0)
            ->first();

        if(empty($message)){
            return response_json(403, trans('web.getDataFail'));
        }elseif (empty($message->read)){
            MessageRead::where('message_id',$message_id)->update([
                'read' => 1,
                'updated_at'=> date('Y-m-d H:i:s')
            ]);
        }

        $message->type_str = Message::getTypeStr($message->type);
        $message->content = htmlspecialchars_decode($message->content);

        return response_json(200, trans('web.getDataSuccess'),
            array(
                'message' => $message
            )
        );

    }

}