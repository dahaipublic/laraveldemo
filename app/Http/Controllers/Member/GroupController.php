<?php

namespace App\Http\Controllers\Member;

use App\Libs\Easemob;
use App\Libs\RandomRedPackage;
use App\Models\Business\BusinessLogLogin;
use App\Models\Community;
use App\Models\CommunityUser;
use App\Models\Currency;
use App\Models\Friend;
use App\Models\Group;
use App\Models\GroupNotice;
use App\Models\GroupUser;
use App\Models\Information\Information;
use App\Models\Information\InformationCategory;
use App\Models\Information\InformationCollect;
use App\Models\Information\InformationComment;
use App\Models\Information\InformationCommentReport;
use App\Models\Information\InformationCommentReportRecord;
use App\Models\Information\InformationContent;
use App\Models\Information\InformationFast;
use App\Models\Information\InformationRead;
use App\Models\Information\InformationReport;
use App\Models\Information\InformationReportRecord;
use App\Models\Information\InformationUsersReport;
use App\Models\Information\InformationUsersReportRecord;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLogLogin;
use App\Models\UsersWallet;
use App\Models\UsersWithdrawAudit;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;



/**
 * @group 群组信息
 * - author whm
 */

class GroupController extends Controller
{


    // 创建群聊
    public function create(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        try{

            $validator = Validator::make($request->all(), [
                'groupname'      => 'required|string|max:100',
                'memberIds'      => 'nullable|string',
                'desc'    => 'nullable|string',
                'notice'    => 'nullable|string',
            ]);
            if ($validator->fails()) {
                return response_json(402,$validator->errors()->first());
            }

            $groupname = $request->input('groupname');
            $member_ids = explode(',', $request->input('memberIds', ''));
            $desc = $request->input('desc', '');
            $notice = trim($request->input('notice', ''));
            $member_list = User::whereIn('id', $member_ids)->where([['easemob_u', '<>', ''], ['id', '<>', $uid]])->pluck('easemob_u', 'id')->toArray();
            $group_member_count = count($member_list);
            if ($group_member_count > Group::MAX_PEOPLE_NUM - 1){
                return response_json(403, trans('app.mostHasGroupMembers'));
            }
            $easemob = new Easemob();
            $options = [
                'groupname' => $groupname,
                'desc'      => "this is a love group",
                'public' => true,//是否公开群组
                'owner' => "$user->easemob_u",
                'members' => array_values($member_list),
                'maxusers' => Group::MAX_PEOPLE_NUM,//最大群员数
            ];
            $result = $easemob->createGroup($options);
            if(!isset($result['data']['groupid']) || empty($result['data']['groupid'])){
                Log::useFiles(storage_path('groupCreate.log'));
                Log::info('result:'.json_encode($result, JSON_UNESCAPED_UNICODE));
                return response_json(403, $result['error_description'], array(
                    'error' => $result
                ));
            }
            $huanxin_id  = $result['data']['groupid'];
            $group_data = [
                'name' => $groupname,
                'huanxinId' => $huanxin_id,
                'creatorId' => $uid,
                'desc' => $desc,
                'memberCount' => $group_member_count + 1,
            ];
            $group_res = Group::create($group_data);
            if(empty($group_res)){
                $easemob->deleteGroup($huanxin_id);
                return response_json(403, trans('web.insertFailed'));
            }
            $update_no = Group::where('id', $group_res->id)->update(['no' => Group::createNo($group_res->id)]);
            $group_user_data = [];
            $now_time = date('Y-m-d H:i:s');
            $member_list[$uid] = $user->easemob_u;
            foreach($member_list as $k => $v){
                array_push($group_user_data, [
                    'user_id' => $k,
                    'group_id' => $group_res->id,
                    'role' => $k == $uid ? GroupUser::HOST_ROLE : GroupUser::MEMBER_ROLE,//1群主 0普通成员
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                ]);
            }
            $group_user_res = DB::table('group_user')->insert($group_user_data);
            if(empty($group_user_res) || !$update_no){
                $easemob->deleteGroup($huanxin_id);
                $group_res->delete();
                return response_json(403, trans('web.insertFailed'));
            }
            $notice and GroupNotice::create(['group_id' => $group_res->id, 'notice' => $notice]);//群公告

            //todo... --------------------------tmp code----------------------------
            $huanxin_member_count = $easemob->getGroupDetail([$huanxin_id])['data'][0]['affiliations_count'] ?? 0;
            $local_member_count = GroupUser::where('group_id', $group_res->id)->count();
            if ($huanxin_member_count != $local_member_count){
                $log_data = [
                    'group_id' => $group_res->id,
                    'huanxin_member_count' => $huanxin_member_count,
                    'local_member_count' => $local_member_count,
                ];
                (new\App\Http\Controllers\Api\GroupController())->addErrorLog($request, $log_data);
            }
            //todo... --------------------------tmp code----------------------------

            return response_json(200, trans('web.success'), array(
                'groupId'       => $group_res->id, // 群组id
                'huanxinId'     => $huanxin_id, // 群组环信id
            ));
        } catch (\Exception $exception) {
            $input = $request->all();
            Log::useFiles(storage_path('groupCreate.log'));
            Log::info('user_id:'.$uid.', input:'.json_encode($input, JSON_UNESCAPED_UNICODE).',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

        }

    }


    // 获取群组信息相关数量
    public function getTotalNumber(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $create_group = Group::select("id")->where('creatorId', $uid)->where('status', 1)->get()->toArray();
        $create_group_ids = array_column($create_group, 'id');

        // 自建普通群数
        $create_group_number = count($create_group);
        // 已加入群组
        $join_group_number = GroupUser::where('user_id', $uid)->where('status', 1)->count("id");
        // 自建普通群人数
        $group_number = 0;
        if(!empty($create_group_ids)){
            $group_number = GroupUser::where('status', 1)->whereIn('group_id', $create_group_ids)->count("id");
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'create_group_number' => $create_group_number,
            'join_group_number' => $join_group_number,
            'group_number' => $group_number,
        ));

    }


    // 获取自己创建群组列表
    public function getMyGroupList(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $groups_query = GroupUser::select('group.id as groupId', 'group.no', 'group.name as group_name','group.creatorId','group.memberCount','group.type','group.created_at', 'community_group.community_id as community_join')
            ->join('group', function ($query) {
                $query->on('group.id', '=', 'group_user.group_id');
            })->leftjoin('community_group', function ($query) {
                $query->on('community_group.group_id', '=', 'group_user.group_id');
            });

        $keyword = $request->input('keyword', '');
        if(!empty($keyword)){
            $groups_query->where('group.name', 'like', "%{$keyword}%");
        }
        $data = $groups_query->where('group_user.user_id', $uid)
            ->where('group.creatorId', $uid)
            ->orderBy('group.created_at', 'desc')
            ->orderBy('group.id', 'desc')
            ->paginate($this->page_size)
            ->toArray();

        $groups = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        if(!empty($groups)){
            $joinedTheCommunity = trans('web.joinedTheCommunity');
            $notJoinedTheCommunity = trans('web.notJoinedTheCommunity');
            $commonGroups = trans('web.commonGroups');
            foreach ($groups as $key => &$item) {
                if($item['community_join']){
                    $item['status'] = 1;
                    $item['community_join'] = Community::where('id', $item['community_join'])->value("name")  ? : '';
                }else{
                    $item['status'] = 0;
                    $item['community_join'] = $notJoinedTheCommunity;
                }
                $item['type'] = $commonGroups;
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'groups' => $groups,
            'total' => $total,
            'last_page' => $last_page,
        ));

    }


    // 获取自己已加入群组列表
    public function getMyJoinGroupList(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $groups_query = GroupUser::select('group.id as groupId','group.no','group.name as group_name','group.creatorId','group.memberCount','group.type','group.created_at', 'group_user.created_at as join_at', 'community_group.community_id as community_join')
            ->join('group', function ($query) {
                $query->on('group.id', '=', 'group_user.group_id');
            })->leftjoin('community_group', function ($query) {
                $query->on('community_group.group_id', '=', 'group_user.group_id');
            });

        $keyword = $request->input('keyword', '');
        if(!empty($keyword)){
            $groups_query->where('group.name', 'like', "%{$keyword}%");
        }
        $data = $groups_query->where('group.creatorId', '<>', $uid)
            ->where('group_user.user_id', $uid)
            ->orderBy('group.created_at', 'desc')
            ->orderBy('group.id', 'desc')
            ->paginate($this->page_size)
            ->toArray();

        $groups = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        if(!empty($groups)){
            $joinedTheCommunity = trans('web.joinedTheCommunity');
            $notJoinedTheCommunity = trans('web.notJoinedTheCommunity');
            $commonGroups = trans('web.commonGroups');
            foreach ($groups as $key => &$item) {
                if($item['community_join']){
                    $item['status'] = 1;
                    $item['community_join'] = Community::where('id', $item['community_join'])->value("name")  ? : '';
                }else{
                    $item['status'] = 0;
                    $item['community_join'] = $notJoinedTheCommunity;
                }
                $item['type'] = $commonGroups;
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'groups' => $groups,
            'total' => $total,
            'last_page' => $last_page,
        ));

    }


    // 查看群组成员列表
    public function getGroupMember(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'groupId'      => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $groupId = $request->input('groupId');
        $page_size = $this->page_size;
        $data = GroupUser::select('u.id as uid', 'u.username','u.email', 'group_user.display_name','group_user.role','group_user.created_at')
            ->join('users as u', function ($query) {
                $query->on('u.id', '=', 'group_user.user_id');
            })
            ->join('group', function ($query) {
                $query->on('group.id', '=', 'group_user.group_id');
            })
            ->where('group_user.group_id', $groupId)
//            ->where('group.creatorId', $uid)
            ->paginate($page_size)
            ->toArray();

        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];

        if(!empty($list)){
            $friend_ids = array_column($list, 'uid');
            $friend_display_name = Friend::where('user_id',  $uid)
                ->whereIn('friend_id', $friend_ids)
                ->where('status', 2)
                ->pluck('display_name', 'friend_id')
                ->toArray();
            GroupUser::setRoleNameMap();
            foreach ($list as $key => &$item) {
                if (empty($friend_display_name[$item['uid']])){
                    $item['display_name'] = $item['display_name'] ? : $item['username'];
                }else{
                    $item['display_name'] = $friend_display_name[$item['uid']];
                }
                unset($item['username']);
                $item['role_name'] = GroupUser::$role_map[$item['role']] ?? '';
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'page_size' => $page_size,
        ));

    }


    // 获取群组详情
    public function getGroupDetails(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'groupId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $groupId = $request->input('groupId');
        $group = Group::select('group.id as groupId', 'group.no', 'group.creatorId', 'group.name as group_name','group.desc','group.portraitUri','group.creatorId','group.memberCount','group.type','group.created_at','group_notice.notice')
            ->leftjoin('group_notice', function ($query) {
                $query->on('group_notice.group_id', '=', 'group.id');
            })
            ->where('group.id', $groupId)
            ->first();

        if(empty($group)){
            return response_json(403, trans('web.noGroup'));
        }
        $group->notice = $group->notice ? : '';
        $group->portraitUri = $group->portraitUri ? url($group->portraitUri) : url('storage/img/defaultlogo.png');
        $group->type = trans('web.commonGroups');
        $group->is_creator = ($group->creatorId == $uid) ? 1 : 0;

        return response_json(200, trans('web.getDataSuccess'), array(
            'groupInfo' => $group,
        ));

    }


    // 修改群组信息
    public function editGroupInfo(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'groupId' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $groupId = $request->input('groupId');
        $group = Group::select('group.id as groupId', 'group.name as group_name','group.desc','group.portraitUri','group.creatorId','group.memberCount','group.type','group.created_at','group_notice.notice')
            ->leftjoin('group_notice', function ($query) {
                $query->on('group_notice.group_id', '=', 'group.id');
            })
            ->where('group.id', $groupId)
            ->where('group.creatorId', $uid)
            ->first();

        if(empty($group)){
            return response_json(403, trans('web.noGroup'));
        }

        $portraitUri = $request->input('portraitUri', '');
        $group_name = $request->input('group_name', '');
        $desc = $request->input('desc', '');
        $notice = $request->input('notice', '');

        $data = array();
        if(!empty($portraitUri)){
            $data['portraitUri'] = $portraitUri;
        }
        if(!empty($group_name)){
            $data['name'] = $group_name;
        }
        if(!empty($desc)){
            $data['desc'] = $desc;
        }

        // 群公告
        if(!empty($notice)){
            $group_notice = GroupNotice::select("group_id")->where('group_id', $groupId)->first();
            if(!empty($group_notice)){
                GroupNotice::where('group_id', $groupId)->update([
                    'notice' => $notice
                ]);
            }else{
                $notice_data = array(
                    'group_id' => $groupId,
                    'notice' => $notice,
                );
                GroupNotice::create($notice_data);
            }
        }else{
            GroupNotice::where('group_id', $groupId)->update([
                'notice' => ''
            ]);
        }

        if(!empty($data)){
            Group::where('id', $groupId)->where('creatorId', $uid)->update($data);
            return response_json(200, trans('web.editSuccess'));
        }else{
            return response_json(402, trans('web.missingParameters'));
        }

    }


    // 好友下拉数据
    public function getFriends(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $friendsList = Friend::join('users as u', function ($query) {
            $query->on('u.id', '=', 'friends.friend_id');
        })
            ->select('friends.friend_id', 'u.username', 'friends.display_name')
            ->where('friends.user_id', $uid)
            ->where('friends.status', 2)
            ->get()
            ->toArray();
        if(!empty($friendsList)){
            foreach ($friendsList as $key => &$item) {
                $item['display_name'] = $item['display_name'] ? : $item['username'];
                unset($item['username']);
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'friendsList' => $friendsList,
        ));

    }








}