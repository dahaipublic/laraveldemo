<?php

namespace App\Http\Controllers\Member;

use App\Libs\Easemob;
use App\Models\Friend;
use App\Models\User;
use App\Models\UserFcConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 * @group 好友信息
 * - author whm
 */

class FriendsController extends Controller
{

    // 获取好友信息相关数量
    public function getTotalNumber(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $friends_number = Friend::where('user_id', $uid)->where('status', 2)->count("friend_id");

        return response_json(200, trans('web.getDataSuccess'), array(
            'friends_number' => $friends_number,
        ));

    }


    // 获取好友列表
    public function getFriendsList(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $keyword = $request->input('keyword', '');
        $query = Friend::select('u.username', 'u.email', 'friends.friend_id', 'friends.display_name', 'friends.updated_at as created_at')
            ->join('users as u', function ($query) {
                $query->on('u.id', '=', 'friends.friend_id');
            })
            ->where('friends.user_id', $uid)
            ->where('friends.status', 2);
        if(!empty($keyword)){
            $query->where(function($query) use ($keyword) {
                $query->where('friends.display_name', 'like', "%$keyword%")->orWhere('u.username', 'like', "%$keyword%")->orWhere('u.email', 'like', "%$keyword%");
            });
        }
        $data = $query->orderBy('friends.updated_at', 'desc')
            ->paginate(10)
            ->toArray();

        $friendsList = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        if(!empty($friendsList)){
            foreach ($friendsList as &$item){
                $item['display_name'] = $item['display_name'] ? : $item['username'];
                unset($item['username']);
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'friends'  => $friendsList,
            'total' => $total,
            'last_page' => $last_page,
        ));

    }


    // 删除好友
    public function delFriends(Request $request){

        $user = Auth::guard('member')->user();
        $user_id = $user->id;

        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $friend_id  = $request->input('friend_id');
        $friend = Friend::select('u.username', 'u.easemob_u', 'friends.display_name', 'friends.friend_id')
            ->join('users as u', function ($query) {
                $query->on('u.id', '=', 'friends.friend_id');
            })
            ->where('friends.user_id', $user_id)
            ->where('friends.friend_id', $friend_id)
            ->where('friends.status', 2)
            ->first();
        if(empty($friend)){
            return response_json(403, trans('web.noFriends'));
        }

        try{

            $username       = $user->easemob_u;
            $username_f     = $friend->easemob_u;
            (new Friend())->delFriends($user_id, $friend_id, $username, $username_f);

            return response_json(200, trans('web.delSuccess'));

        }catch(\Exception $exception){

            Log::useFiles(storage_path('memberDelFriends.log'));
            Log::info('user_id:'.$user_id.', friend_id:'.$friend_id.', message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

        }

    }


    // 好友申请列表
    public function getApplyList(Request $request){

        $user = Auth::guard('member')->user();
        $user_id = $user->id;

        $data = Friend::join('users as u', function ($query) {
            $query->on('u.id', '=', 'friends.user_id');
        })
            ->select('friends.user_id as friend_id','u.username','u.email', 'friends.display_name','friends.created_at')
            ->where('friends.friend_id', $user_id)
            ->where('friends.is_del_apply', 0)
            ->where('friends.status', 1)
            ->orderBy('friends.created_at', 'desc')
            ->paginate(10)
            ->toArray();

        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];

        if(!empty($list)){
            foreach ($list as $key => &$item) {
                if(empty($item['display_name'])){
                    $item['display_name'] = $item['username'];
                }
                unset($item['username']);
            }
        }

        return response_json(200, trans('web.success'), array(
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
        ));

    }

    // 同意好友申请
    public function agree(Request $request){

        $user = Auth::guard('member')->user();
        $user_id = $user->id;

        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $friend_id = $request->input('friend_id');

        try{

            DB::beginTransaction();

            $friend = Friend::select("user_id", "friend_id", 'status')->where('user_id', $friend_id)
                ->where('friend_id', $user_id)
                ->first();
            if (empty($friend)){
                DB::rollBack();
                return response_json(403, trans('web.noApplication'));
            }
            if($friend->status == '1'){
                Friend::where('user_id', $friend_id)->where('friend_id', $user_id)->update(['status'=> 2]);
            }else{
                DB::rollBack();
                return response_json(403, trans('web.noApplication'));
            }

            $userFriend = Friend::select("user_id", "friend_id", 'status')
                ->where('user_id', $user_id)
                ->where('friend_id', $friend_id)
                ->first();
            if(!empty($userFriend->status)){
                Friend::where('user_id', $user_id)
                    ->where('friend_id', $friend_id)
                    ->update(['status' => 2]);
            }else{
                $createData = array(
                    'user_id'   => $user_id,
                    'friend_id' => $friend_id,
                    'status'    => 2
                );
                Friend::create($createData);
            }

            $Easemob    = new Easemob();
            $username   = $user->easemob_u;

            $username_fs = User::select("easemob_u")->where('id', $friend_id)->first();
            if (!empty($username_fs)){
                $username_f = $username_fs->easemob_u;
            }else{
                $username_f = '';
            }
            $result = $Easemob->addFriend($username, $username_f);
            if(isset($result['error'])){
                DB::rollBack();
                return response_json(403, $result['error_description']);
            }

            return response_json(200, trans('web.addSuccess'));

        }catch (\Exception $exception){

            DB::rollBack();
            Log::useFiles(storage_path('memberFriendAgree.log'));
            Log::info('friend_id:'.$friend_id.', userInfo:'.json_encode($user, JSON_UNESCAPED_UNICODE).', message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());
            return response_json(403, trans('web.noApplication'));

        }

    }


    // 拒绝好友申请
    public function reject(Request $request){

        $user = Auth::guard('member')->user();
        $user_id = $user->id;

        $validator = Validator::make($request->all(), [
            'friend_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $friend_id  = $request->input('friend_id');

        $where = [['user_id', $friend_id], ['friend_id', $user_id], ['status', 1], ['is_del_apply', 0]];
        $friend = Friend::where($where)->first();
        if (empty($friend)){
            return response_json(403, trans('web.noApplication'));
        }
        $res = $friend->where($where)->update(['status' => 3]);
        if (!$res) {
            return response_json(403, trans('web.fail'));
        }
        return response_json(200, trans('web.success'));

    }


    // 搜索好友
    public function search(Request $request){

        $user = Auth::guard('member')->user();
        $user_id = $user->id;

        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $keyword    = $request->input('keyword');
        $user_field = ['id as user_id', 'username', 'email', 'customer_type', 'sex', 'portRaitUri'];

        $search_user = User::select($user_field)
            ->where(function($query) use ($keyword) {
                //$query->where('email', "{$keyword}")->orWhere('username', 'like', "{$keyword}");
                $query->where('email', "{$keyword}")->orWhere('username', "{$keyword}");
            })
            // ->where('id', '<>', $user_id)
            ->where('email_status', 2)
            ->where('status',1)
            ->first();

        if(empty($search_user)){
            $search_user = User::select($user_field)
                ->where(function($query) use ($keyword) {
//                    $query->where('email', "like", "%{$keyword}%")->orWhere('username', 'like', "%{$keyword}%");
                    $query->where('email', "{$keyword}")->orWhere('username', "{$keyword}");
                })
                // ->where('id', '<>', $user_id)
                ->where('email_status', 2)
                ->where('status',1)
                ->first();
        }

        if(!empty($search_user)){

            // 判断是否已添加好友
            $search_user->is_friends = (new Friend())->isFriends($user_id, $search_user->user_id) ? 1 : 0;
            $search_user->portRaitUri = url($search_user->portRaitUri);
            $search_user->customer_type = User::getCustomerType($search_user->customer_type);
            return response_json(200, trans('web.success'), array(
                'search_user' => $search_user
            ));

        }else{

            return response_json(403, trans('web.userNotFound'));

        }

    }


    // 好友申请
    public function apply(Request $request){

        $user = Auth::guard('member')->user();
        $user_id = $user->id;

        $this->validate($request, [
            'friend_id'      => 'required|integer',
            'message'        => 'nullable|string|max:100'
        ]);
        $message    = $request->input('message') ? :'';
        $friend_id  = $request->input('friend_id');

        //查询好友是否开启好友验证
        $_verify = User::select("friend_validation", "sellerId")->where('id', $friend_id)->first();
        if(!empty($_verify)){
            $verify = $_verify->friend_validation;
        }else{
            $verify = 1;
        }

        if($friend_id == $user->id){
            return response_json(403, trans('web.cantAddYourSelf'));
        }
        $friend = Friend::where('user_id', $user->id)
            ->where('friend_id', $friend_id)
            ->first();

        if(!empty($friend)){
            switch ($friend->status) {
                case 2:
                    return response_json(403, trans('web.alreadyAFriend'));
                    break;
                case 1:
                    if($friend->is_del_apply==1){
                        Friend::where('user_id', $user->id)
                            ->where('friend_id', $friend_id)
                            ->update(['is_del_apply' => 0]);
                    }else{
                        return response_json(403, trans('web.alreadyAdded'));
                    }
                    break;
                case 5:
                    return response_json(403, trans('web.refusalToApply'));
                    break;
                case 6:
                    return response_json(403, trans('web.alreadyBlack'));
                    break;
                default:
                    Friend::where('user_id', $user->id)
                        ->where('friend_id', $friend_id)
                        ->update(['status' => 1]);
                    break;
            }

            // 环信透传
            $username_fs = User::where('id', $friend_id)->first();
            if (!empty($username_fs)){
                $username_f = $username_fs->easemob_u;
            }else{
                $username_f = '';
            }
            $from = $user->easemob_u;
            $target_type = "users";
            $action = 'com.chain.friend.apply';
            $target = array(
                $username_f
            );
            $ext = array(
                'action' => 'com.chain.friend.apply'
            );
            $Easemob = new Easemob();
            $res = $Easemob->sendCmd($from, $target_type, $target, $action, $ext);// 发送透传

            // 记录LOG
//            Log::useFiles(storage_path('memberFriendApply.log'));
//            Log::info('0, from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));

        }else{
            $createData = array(
                'user_id'   => $user->id,
                'friend_id' => $friend_id,
                'message' => $message,
            );
            $friend = array(
                'user_id'   => $friend_id,
                'friend_id' => $user->id,
                'message' => $message,
            );

            if ($verify){    //需要认证
                try{

                    Friend::create($createData);

                    // 环信透传
                    $username_fs = User::where('id', $friend_id)->first();
                    if (!empty($username_fs)){
                        $username_f = $username_fs->easemob_u;
                    }else{
                        $username_f = '';
                    }
                    $from = $user->easemob_u;
                    $target_type = "users";
                    $action = 'com.chain.friend.apply';
                    $target = array(
                        $username_f
                    );
                    $ext = array(
                        'action' => 'com.chain.friend.apply'
                    );
                    $Easemob = new Easemob();
                    $res = $Easemob->sendCmd($from, $target_type, $target, $action, $ext);// 发送透传

                    // 记录LOG
//                    Log::useFiles(storage_path('memberFriendApply.log'));
//                    Log::info('1, from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                        . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));

                }catch (\Exception $exception) {
                    return response_json(403, trans('web.doNotSubmitAgain'));
                }
            }else{   //不需要认证直接通过
                //
                try{
                    DB::beginTransaction();
                    $createData['status'] = 2;
                    $friend['status'] = 2;
                    $create = Friend::create($createData);
                    $_create = Friend::create($friend);
                    if($create && $_create){
                        //不需好友验证添加环信好友关系
                        $Easemob    = new Easemob();
                        $username   = $user->easemob_u;
                        $username_fs = User::where('id', $friend_id)->first();
                        if (!empty($username_fs)){
                            $username_f = $username_fs->easemob_u;
                        }else{
                            $username_f = '';
                        }

                        $result = $Easemob->addFriend($username, $username_f);
                        if(isset($result['error'])){
                            DB::rollBack();
                            return response_json(403, $result['error_description']);
                        }
                        $returnmsg = trans('web.addSuccess');
                        DB::commit();

                        $from = $user->easemob_u;
                        $target_type = "users";
                        $action = 'com.chain.friend.apply';
                        $target = array(
                            $username_f
                        );
                        $ext = array(
                            'action' => 'com.chain.friend.apply'
                        );
                        $Easemob = new Easemob();
                        $res = $Easemob->sendCmd($from, $target_type, $target, $action, $ext);// 发送透传

                        // 记录LOG
//                        Log::useFiles(storage_path('memberFriendApply.log'));
//                        Log::info('2, from:' . $from . ', target_type:' . $target_type . ', target:' . json_encode($target, JSON_UNESCAPED_UNICODE)
//                            . ', ext:' . json_encode($ext, JSON_UNESCAPED_UNICODE) . ', res:' . json_encode($res, JSON_UNESCAPED_UNICODE));

                        return response_json(200, $returnmsg);

                    }else{
                        DB::rollBack();
                        return response_json(200, trans('web.sendFail'));
                    }
                }catch (\Exception $exception) {
                    return response_json(403, trans('web.doNotSubmitAgain'));
                }
            }   //end //不需要认证直接通过
        }

        if($verify){
            return response_json(200, trans('web.sendSuccess'));
        }else{
            return response_json(200, trans('web.addSuccess'));
        }

    }


}