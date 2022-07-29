<?php

namespace App\Http\Controllers\Member;

use App\Libs\Easemob;
use App\Models\Community;
use App\Models\CommunityActionLog;
use App\Models\CommunityAdmin;
use App\Models\CommunityGroup;
use App\Models\CommunityJoinApply;
use App\Models\CommunityNotice;
use App\Models\CommunityTag;
use App\Models\CommunityUser;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Information\Information;
use App\Models\User;
use App\Models\UserFcMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;


/**
 * @group 社区
 * - author whm
 */

class CommunityController extends Controller
{


    /**
     * 创建社区
     */
    public function create(Request $request){
        $this->validate($request, [
            'name' => 'string|min:2|max:32|unique:community,name',
            'desc' => 'required|string|max:512',
            'portrait_uri' => 'required|string|max:100',
            'group_ids' => 'required|string',
            'tag_ids' => 'required|string',
        ]);
        $name = $request->input('name');
        $tag_ids = trim($request->input('tag_ids'),',');
        $portrait_uri = $request->input('portrait_uri');
        $desc = $request->input('desc');
        $user_info = Auth('member')->user();
        $group_ids = Group::where('creatorId', $user_info->id)->whereIn('id', explode(',', $request->input('group_ids')))->pluck('id')->toArray();
        $join_count = count($group_ids);
        if ($join_count < Community::MIN_GROUP_QTY) {
            return response_json(403, trans('app.minGroupNum'));
        }
        if ($join_count > Community::MAX_GROUP_QTY) {
            return response_json(403, trans('app.mostHasGroups'));
        }
        //判断群是否已加入过社区，一个群只能加入一个社区
        if (CommunityGroup::whereIn('group_id', $group_ids)->value('id')){
            return response_json(403, trans('app.groupAlreadyInCommunity'));
        }
        $community_data = [
            'name' => $name,
            'desc' => $desc,
            'tag_ids' => $tag_ids,
            'portrait_uri' => $portrait_uri,
            'creator_id' => $user_info->id,
            'is_public' => $request->input('is_public', 1),
        ];

        DB::beginTransaction();
        //创建社区
        $create_community = Community::create($community_data);
        if (empty($create_community)) {
            DB::rollBack();
            return response_json(403, trans('web.addFail'));
        }
        //将社长加入到社区管理员表
        $create_Admin = CommunityAdmin::create(['user_id' => $user_info->id, 'community_id' => $create_community->id]);
        if (empty($create_Admin)) {
            DB::rollBack();
            return response_json(403, trans('web.addFail'));
        }
        //创建社区与群的关联数据（将群加入社区中）
        $group_join_res = Group::joinCommunity($group_ids, $create_community->id);
        if (!$group_join_res) {
            DB::rollBack();
            return response_json(403, trans('web.addFail'));
        }
        //更新社区人数和社区号
        $no = Community::createNo($create_community->id);
        if (!Community::updateMemberCount($create_community->id) ||
            !Community::where('id', $create_community->id)->update(['no' => $no])) {
            DB::rollBack();
            return response_json(403, trans('web.editFail'));
        }

        DB::commit();
        return response_json(200, trans('web.success'));
    }

    /**
     * 编辑社区基本信息
     */
    public function edit(Request $request){
        $rules = [
            'community_id' => 'required|integer',
            'name' => 'string|min:2|max:32',
            'desc' => 'required|string|max:512',
            'portrait_uri' => 'required|string|max:100',
            'tag_ids' => 'required|string',
            'is_public' => 'required|int|min:0|max:1',
        ];
        $this->validate($request, $rules);
        $id = $request->input('community_id');
        $name = trim($request->input('name'));
        $uid = Auth('member')->id();
        $my_community = Community::mine($id, $uid);
        if (empty($my_community)) {
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        if ($name != $my_community->name && Community::where('id','<>',$id)->where('name',$name)->value('id')) {
            return response_json(403, trans('app.communityNameRepeat'));
        }
        $data = $request->only(array_keys($rules));
        $data['portrait_uri'] = filter_url_host($data['portrait_uri']);
        unset($data['community_id']);
        $res = Community::where('id', $my_community->id)->update($data);
        if (!$res){
            return response_json(403, trans('web.updateFail'));
        }
        CommunityActionLog::add($id, $uid, __FUNCTION__, '修改社区信息');
        return response_json(200, trans('web.editSuccess'));
    }

    /**
     * 获取社区标签库
     */
    public function getCommunityTags(Request $request){
        $user = Auth::guard('member')->user();
        $language = $user->language ? : 'cn';
        $list = CommunityTag::select('id as tag_id',"name_{$language} as name")
            ->where('enabled', 1)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
        return response_json(200, trans('web.getDataSuccess'), compact('list'));
    }

    /**
     * 获取社区列表
     */
    public function getCommunityList(Request $request){
        $query = Community::select("id as community_id", "name as community_name", "member_count", "created_at")
            ->where('is_public', 1)
            ->where('status', 1);

        $keyword = $request->input('keyword', '');
        if(!empty($keyword)){
            $query->where('name', 'like', "{$keyword}%");
        }

        $data = $query->orderBy('id', 'desc')->paginate($this->page_size)->toArray();
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];

        return response_json(200, trans('web.getDataSuccess'), [
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
            'page_size' => $this->page_size,
        ]);
    }

    /**
     *获取我的社区列表（只包含我创建的社区）
     */
    public function myCreateList(Request $request){
        $name = trim($request->input('name', ''));
        $field = ['id', 'name', 'no', 'member_count', 'created_at'];
        $user_info = Auth('member')->user();
        $where[] = ['creator_id', $user_info->id];
        $name and $where[] = ['name', 'like', "%{$name}%"];
        $data = Community::where($where)
            ->select($field)
            ->paginate($this->page_size)
            ->toArray();
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        foreach ($list as &$v){
            $v['nickname'] = $user_info->username;
        }unset($v);
        $page_size = $this->page_size;
        return response_json(200, trans('web.getDataSuccess'), compact('list', 'total', 'last_page', 'page_size'));
    }

    /**
     *获取我加入的社区列表（不包含我创建的社区）
     */
    public function myJoinList(Request $request){
        $name = trim($request->input('name', ''));
        $field = ['c.id', 'c.name', 'c.creator_id', 'c.no', 'c.member_count', 'c.created_at'];
        $data = Community::getUserCommunityByUid(Auth('member')->id(), $field, true, $this->page_size, $name ,true);
        $data['page_size'] = $this->page_size;
        if ($data['list']){
            $nikename_list = User::whereIn('id', array_unique(array_column($data['list'], 'creator_id')))->pluck('username', 'id');
            foreach ($data['list'] as &$v){
                $v['nickname'] = $nikename_list[$v['creator_id']];
            }unset($v);
        }
        return response_json(200, trans('web.getDataSuccess'), $data);
    }

    /**
     * 获取社区统计数据
     */
    public function getCountData(){
        $uid = Auth('member')->id();
        $where[] = ['creator_id', $uid];
        $create_count = Community::from('community')->where($where)->count();
        $create_people_count = Community::from('community')->where($where)->sum('member_count');
        $join_count = Community::getJoinCount($uid);
        $join_people_count = Community::getJoinPeopleCount($uid);
        return response_json(
            200,
            trans('web.getDataSuccess'),
            compact('create_count', 'create_people_count', 'join_count', 'join_people_count')
        );
    }

    /**
     * 社区创建者解散社区
     */
    public function dismiss(Request $request){
        $this->validate($request, ['community_id' => 'required|integer']);
        $id = $request->input('community_id');
        $user_info = Auth('member')->user();
        //只有社区创建者能解散群
        $my_community = Community::mine($id, $user_info->id, true);
        if (empty($my_community)) {
            return response_json(403, trans('app.notCreator'));
        }
        DB::beginTransaction();
        $res = Community::where('id', $id)->delete();
        $res = $res && CommunityGroup::where('community_id', $id)->delete();
        //可能没有关联的数据
        $where[] = ['community_id', $id];
        CommunityUser::where($where)->delete();
        UserFcMessage::where($where)->delete();
        CommunityNotice::where($where)->forceDelete();
        Information::where($where)->delete();
        CommunityAdmin::where($where)->delete();
        if (!$res){
            DB::rollBack();
            return response_json(403, trans('web.deleteFail'));
        }
        DB::commit();
        return response_json(200, trans('web.success'));
    }

    /**
     * 获取社区详情
     */
    public function getDetails(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $id = $request->input('id');
        $user_info = Auth('member')->user();
        $community = Community::where('id', $id)->first();
        if (empty($community)){
            return response_json(403, trans('app.noCommunity'));
        }
        $info['tags'] = CommunityTag::whereIn('id',explode(',', $community->tag_ids))->get(['id', "name_{$user_info->language} as name"]) ?: [];
        if ($community->creator_id == $user_info->id){
            $info['is_creator'] = 1;
            $info['creator_name'] = $user_info->username;
        }else{
            $info['is_creator'] = 0;
            $info['creator_name'] = User::where('id', $community->creator_id)->value('username');
        }
        $info['notice'] = CommunityNotice::where('community_id', $community->id)->orderBy('is_top', 'desc')->orderBy('id', 'desc')->value('title') ?: '';
//        $info['nickname'] = CommunityUser::where('community_id', $community->id)->where('user_id', $user_info->id)->value('display_name') ?: '未设置';
        $info['portrait_uri'] = url($community->portrait_uri);
        $info['is_admin'] = empty(Community::mine($community->id, $user_info->id)) ? 0 : 1;//是否是社区管理员
        $field = ['id', 'name', 'no', 'member_count', 'article_count', 'portrait_uri', 'desc','is_public', 'creator_id'];
        $info = array_merge(Arr::only($community->toArray(), $field), $info);
        return response_json(200, trans('web.getDataSuccess'), compact('info'));
    }

    /**
     * 获取社区下的群组列表
     */
    public function getCommunityGroupList(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $name = trim($request->input('name', ''));
        $id = $request->input('id');
        $uid = Auth('member')->id();
        $group_ids = Community::getGroupIdsById($id);
//        $my_join = GroupUser::getGroupIdsByUid($uid);
        $query = Group::select(['id', 'name', 'memberCount', 'no', 'type', 'created_at'])->whereIn('id', $group_ids);
        $name and $query->where('name', 'like', "%{$name}%");
        $data = $query->paginate($this->page_size)->toArray();
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        $page_size = $this->page_size;
        Group::setTypeNameMap();
        foreach ($list as &$v){
            $v['is_full'] = $v['memberCount'] >= Group::MAX_PEOPLE_NUM ? 1 : 0;
//            $v['is_join'] = (int)in_array($v['id'], $my_join);
            $v['type'] = Group::$type_map[$v['type']] ?? '';
        }unset($v);
        return response_json(
            200,
            trans('web.getDataSuccess'),
            compact('list', 'total', 'last_page', 'page_size')
        );
    }

    /**
     * 获取我管理的社区列表
     */
    public function getManagerCommunityList(){
        $ids = Community::getManagerByUid(Auth('member')->id());
        $list = $ids ? Community::whereIn('id', $ids)->get(['id as community_id', 'name'])->toArray() : [];
        return response_json(200, trans('web.getDataSuccess'), compact('list'));
    }

    /**
     * 群主发起群加入社区申请
     */
    public function groupJoinApply(Request $request){
        $this->validate($request, [
            'group_id' => 'required|int',
            'community_id' => 'required|int',
        ]);
        $group_id = $request->input('group_id');
        $community_id = $request->input('community_id');
        $user_info = Auth('member')->user();
        $check = $this->canGroupJoinValidate($group_id, $user_info->id);
        if ($check !== true){
            return response_json(403, $check);
        }
        $data = ['user_id' => $user_info->id, 'community_id' => $community_id, 'group_id' => $group_id];
        $res = CommunityJoinApply::create($data);
        if (!$res){
            return response_json(403, trans('web.applyForFail'));
        }
        //发送透传消息给社长
        $ext['type'] = 'message';
        $to = [(new Community())->getCreatorEasemob($community_id)];
        (new Easemob())->sendCmd($user_info->username, 'users', $to, 'com.chain.community.applyJoin', $ext);
        return response_json(200, trans('web.applyForSuccess'));
    }

    /**
     * 验证是否可以发起群加入社区申请
     */
    private function canGroupJoinValidate($group_id, $uid){
        //验证是否是群主、群是否已加入社区、群是否正在申请加入社区
        $group = Group::find($group_id);
        if (empty($group))  {
            return trans('app.noGroup');
        }
        if ($group->creatorId != $uid) {
            return trans('app.notCreator');
        }
        if (CommunityGroup::where('group_id', $group_id)->value('id')){
            return trans('web.AGroupAlreadyInCommunity');
        }
        if (CommunityJoinApply::where('group_id', $group_id)->where('status', CommunityJoinApply::UNCHECK)->value('id')){
            return trans('web.groupIsApplyingJoin');
        }
        return true;
    }

    /**
     * 群主申请群加入社区被拒绝重新申请
     */
    public function groupJoinReApply(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $apply_id = $request->input('id');
        $user_info = Auth('member')->user();
        $where[] = ['id', $apply_id];
        $where[] = ['user_id', $user_info->id];
        $where[] = ['status', CommunityJoinApply::REJECT];//被拒绝后才能重新发起申请
        $apply_info = CommunityJoinApply::where($where)->first();
        if (empty($apply_info)){
           return response_json(403, trans('web.noApply'));
        }
        $check = $this->canGroupJoinValidate($apply_info->group_id, $apply_info->user_id);
        if ($check !== true){
            return response_json(403, $check);
        }
        $apply_info->status = CommunityJoinApply::UNCHECK;
        $apply_info->save();
        //发送透传消息给社长
        $ext['type'] = 'message';
        $to = [(new Community())->getCreatorEasemob($apply_info->community_id)];
        (new Easemob())->sendCmd($user_info->username, 'users', $to, 'com.chain.community.applyJoin', $ext);
        return response_json(200, trans('web.success'));
    }


    /**
     * 获取群加入社区申请列表
     */
    public function getGroupJoinApplyList(Request $request){
        $this->validate($request, [
            'status' => 'nullable|int',
        ]);
        $status = $request->input('status', null);
        $uid = Auth('member')->id();
        $query = Group::from('group as g')
            ->select(['cja.*', 'g.name as group_name', 'g.type', 'g.memberCount', 'c.name as community_name'])
            ->join('community_join_apply as cja', 'cja.group_id', '=', 'g.id')
            ->join('community as c', 'c.id', '=', 'cja.community_id')
            ->where('cja.user_id', $uid);
        is_null($status) or $query->where('cja.status', $status);
        $data = $query->orderBy('id', 'desc')->paginate($this->page_size)->toArray();
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        $page_size = $this->page_size;
        Group::setTypeNameMap();
        CommunityJoinApply::setStatusNameMap();
        foreach ($list as &$v){
            $v['type'] = Group::$type_map[$v['type']] ?? '';
            $v['status_name'] = CommunityJoinApply::$status_name_map[$v['status']] ?? '';
        }unset($v);
        return response_json(
            200,
            trans('web.getDataSuccess'),
            compact('list', 'total', 'last_page', 'page_size')
        );
    }

    /**
     * 获取群加入社区审核列表
     */
    public function getGroupJoinCheckList(Request $request){
        $this->validate($request, [
            'status' => 'nullable|int',
            'keyword' => 'nullable|string'
        ]);
        $status = $request->input('status', null);
        $keyword = trim($request->input('keyword', ''));
        $uid = Auth('member')->id();
        $community_ids = Community::getManagerByUid($uid);//只获取我管理的社区下的申请
        if (empty($community_ids)){
            return response_json(200, trans('web.getDataSuccess'), $this->getEmptyData());
        }
        $query = Group::from('group as g')
            ->select(['cja.*', 'g.name as group_name', 'c.name as community_name'])
            ->join('community_join_apply as cja', 'cja.group_id', '=', 'g.id')
            ->join('community as c', 'c.id', '=', 'cja.community_id')
            ->whereIn('cja.community_id', $community_ids);
        $keyword and $query->where(function ($query) use($keyword){
            $query->where('g.name', 'like', "%{$keyword}%")->orWhere('c.name', 'like', "%{$keyword}%");
        });
        is_null($status) or $query->where('cja.status', $status);
        $data = $query->orderBy('cja.id', 'desc')->paginate($this->page_size)->toArray();
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        $page_size = $this->page_size;
        CommunityJoinApply::setStatusNameMap();
        foreach ($list as &$v){
            $v['status_name'] = CommunityJoinApply::$status_name_map[$v['status']] ?? '';
        }unset($v);
        return response_json(
            200,
            trans('web.getDataSuccess'),
            compact('list', 'total', 'last_page', 'page_size')
        );
    }

    /**
     * 社区管理员拒绝群加入社区
     */
    public function rejectGroupJoinApply(Request $request){
        $this->validate($request, [
            'id' => 'required|int',
            'cause' => 'nullable|string'
        ]);
        $apply_id = $request->input('id');
        $cause = $request->input('cause', '');
        $uid = Auth('member')->id();
        $where[] = ['id', $apply_id];
        $where[] = ['status', CommunityJoinApply::UNCHECK];
        $apply_info = CommunityJoinApply::where($where)->first();
        if (empty($apply_info)){
            return response_json(403, trans('web.noApply'));
        }
        if (!CommunityAdmin::isMe($apply_info->community_id, $uid)){
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        $apply_info->reject_cause = $cause;
        $apply_info->status = CommunityJoinApply::REJECT;
        $apply_info->save();
        return response_json(200, trans('web.success'));
    }

    /**
     * 社区管理员删除群加入社区申请记录
     */
    public function delGroupJoinApply(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $apply_id = $request->input('id');
        $uid = Auth('member')->id();
        $apply_info = CommunityJoinApply::where('id', $apply_id)->first();
        if (empty($apply_info)){
            return response_json(403, trans('web.noApply'));
        }
        if (!CommunityAdmin::isMe($apply_info->community_id, $uid)){
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        $apply_info->delete();
        return response_json(200, trans('web.delSuccess'));
    }

    /**
     * 社区管理员同意群加入社区申请
     */
    public function agreeGroupJoinApply(Request $request){
        $this->validate($request, ['id' => 'required|int',]);
        $apply_id = $request->input('id');
        $uid = Auth('member')->id();
        $where[] = ['id', $apply_id];
        $where[] = ['status', CommunityJoinApply::UNCHECK];
        $apply_info = CommunityJoinApply::where($where)->first();
        //验证群申请是否存在、验证是否是社区管理员、验证群是否已加入过社区
        if (empty($apply_info)){
            return response_json(403, trans('web.noApply'));
        }
        if (!CommunityAdmin::isMe($apply_info->community_id, $uid)){
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        if (CommunityGroup::where('group_id', $apply_info->group_id)->value('id')){
            return response_json(403, trans('web.AGroupAlreadyInCommunity'));
        }
        if (CommunityGroup::getGroupCount($apply_info->community_id) >= Community::MAX_GROUP_QTY) {
            return response_json(403, trans('app.mostHasGroups'));
        }
        //验证通过，更新申请状态、将群加入社区、更新社区成员数
        DB::beginTransaction();
        $apply_info->status = CommunityJoinApply::PASSED;
        $res = $apply_info->save();
        $res = $res && Group::joinCommunity([$apply_info->group_id], $apply_info->community_id);
        $res = $res && Community::updateMemberCount($apply_info->community_id);
        if (!$res){
            DB::rollBack();
            return response_json(403, trans('web.handleFail'));
        }
        DB::commit();
        CommunityActionLog::add($apply_info->community_id, $uid, __FUNCTION__, '同意群加入社区，群id：' . $apply_info->group_id);
        return response_json(200, trans('web.handleSuccess'));
    }

    protected function getEmptyData(){
        return ['list' => [], 'total' => 0, 'last_page' => 1, 'page_size' => $this->page_size];
    }

    /**
     * 获取用户可加入社区的群列表
     */
    public function getCanJoinGroupList(Request $request){
        //获取我创建的并且未加入社区的群列表
        $list = Group::from('group as g')
            ->select(['g.id', 'g.name', 'g.no'])
            ->leftJoin('community_group as cg', 'g.id', '=', 'cg.group_id')
            ->where('creatorId', Auth('member')->id())
            ->whereNull('cg.id')
            ->get()
            ->toArray();
        //过滤掉正在申请加入社区的群
        $list and $list = CommunityJoinApply::filterApplyingJoinGroup($list);
        return response_json(200, trans('web.getDataSuccess'), compact('list'));
    }

    /**
     * 群主取消群加入社区申请
     */
    public function cancelGroupJoinApply(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $apply_id = $request->input('id');
        $uid = Auth('member')->id();
        $where[] = ['id', $apply_id];
        $where[] = ['user_id', $uid];
        $where[] = ['status', CommunityJoinApply::UNCHECK];
        $apply_info = CommunityJoinApply::where($where)->first();
        if (empty($apply_info)){
            return response_json(403, trans('web.noApply'));
        }
        $apply_info->status = CommunityJoinApply::CANCEL;
        $apply_info->save();
        return response_json(200, trans('web.cancelSuccess'));
    }
}