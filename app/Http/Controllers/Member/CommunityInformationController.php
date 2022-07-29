<?php

namespace App\Http\Controllers\Member;

use App\Models\Community;
use App\Models\CommunityActionLog;
use App\Models\Information\Information;
use App\Models\Information\InformationCollect;
use App\Models\Information\InformationComment;
use App\Models\Information\InformationContent;
use App\Models\Information\InformationRead;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;

/**
 * @group 社区资讯管理
 * - author lyx
 */
class CommunityInformationController extends Controller
{

    /**
     * 发布/编辑资讯
     */
    public function publish(Request $request){
        $this->validate($request,  [
            'information_id' => 'nullable|integer',
            'community_id' => 'required|integer',
            'title' => 'required|string|min:3|max:256',
            'content' => 'required|string',
            'cover' => 'required|string',
            'cover_thumb' => 'required|string',
            'summary' => 'nullable|string',
        ]);
        $uid = Auth('member')->id();
        $information_id = $request->input('information_id',0);
        $community_id = $request->input('community_id');
        $content = trim_script($request->input('content'));
        $data['title'] = $request->input('title');
        $data['summary'] = $request->input('summary') ?: '';
        $data['pic'] = $request->input('cover');
        $data['thumb'] = $request->input('cover_thumb');

        $mine_community = Community::mine($community_id, $uid);
        if (empty($mine_community)) {
            return response_json(403, trans('app.notCommunityAdmin'));
        }

        $preview = $request->input('preview', 0);
        if(empty($preview)) {
            if ($information_id) {//编辑
                $information = Information::where('id', $information_id)->where('community_id', $mine_community->id)->first();
                if (empty($information)) {
                    return response_json(403, trans('web.noSuchInformation'));
                }
                if ($information->status != Information::REJECT) {//只有审核不通过的文章才可以编辑
                    return response_json(402, trans('web.permissionForbid'));
                }
                $data['status'] = Information::REVIEWING;
                $res = Information::where('id', $information_id)->update($data);
                $res = $res && InformationContent::where('information_id', $information_id)->update(['content' => $content]);
            } else {//新增
                $data['uid'] = $uid;
                $data['community_id'] = $mine_community->id;
                $data['type'] = Information::COMMUNITY_TYPE;
                $data['timestamp'] = time();
                $data['is_pc_create'] = 1;
                $information = Information::create($data);
                if ($information) {
                    $res = InformationContent::create(['information_id' => $information->id, 'content' => $content]);
                }
            }
            if (empty($res)) {
                return response_json(403, trans('app.' . ($information_id ? 'updateFailed' : 'publishCommunityInfoFail')));
            }
            $information_id and CommunityActionLog::add($information->community_id, $uid, 'editInformation', '修改的社区资讯id：' . $information->id);
            return response_json(200, trans('app.' . ($information_id ? 'updateCommunityInfoSuccess' : 'publishCommunityInfoSuccess')));
        }else{
            $redis_key = 'member_community_information_preview_'.$uid;
            $set = Redis::set($redis_key, json_encode($request->input()));
            if($set){
                return response_json(200, trans('web.addSuccess'), array(
                    'information' => $request->input()
                ));
            }else{
                return response_json(403, trans('web.addFail'));
            }
        }
    }

    /**
     * 删除资讯
     */
    public function del(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $id = $request->input('id');
        $uid = Auth('member')->id();
        $information = Information::where([['id', $id], ['is_del', 0], ['type', Information::COMMUNITY_TYPE]])->first();
        if (empty($information)) {
            return response_json(403, trans('web.noSuchInformation'));
        }
        if ($information->status != Information::REJECT){//只有审核不通过的文章才可以删除
            return response_json(402, trans('web.permissionForbid'));
        }
        $mine_community = Community::mine($information->community_id, $uid);
        if (empty($mine_community)) {
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        $res = Information::where('id', $id)->update(['is_del' => 1]);
        if (!$res){
            return response_json(403, trans('web.deleteSuccess'));
        }
        $information->status == Information::NORMAL and (new Community())->articleCountDecr($information->community_id);
        CommunityActionLog::add($information->community_id, $uid, 'delInformation', '删除的社区资讯id：'  . $information->id);
        return response_json(200, trans('web.success'));
    }

    /**
     * 资讯列表
     */
    public function getList(Request $request){
        $user_info = Auth('member')->user();
        $title = trim($request->input('title', ''));
        $status = $request->input('status', null);
        $page_size = $this->page_size;
        $manager_community_ids = Community::getManagerByUid($user_info->id);//只获取我创建或我管理的社区下的咨询文章
        $field = ['id as information_id', 'community_id', 'title', 'created_at', 'read_number', 'comment_number', 'is_pc_create', 'status', 'reason'];
        $query = Information::select($field)
            ->whereIn('community_id', $manager_community_ids)
            ->where('is_del', 0);
        is_null($status) or $query->where('status', (int)$status);
        $title and $query->where('title', 'like', "%{$title}%");
        $data = $query->orderBy('id', 'desc')->paginate($page_size)->toArray();
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        if ($list){
            $community_name_list = Community::whereIn('id', array_unique(array_column($list, 'community_id')))->pluck('name', 'id');
            foreach ($list as &$v){
                $v['community_name'] = $community_name_list[$v['community_id']];
            }unset($v);
        }
        return response_json(
            200,
            trans('web.getDataSuccess'),
            compact('list' , 'total', 'last_page', 'page_size')
        );
    }

    /**
     * 获取修改社区资讯的内容
     */
    public function getDetails(Request $request){
        $this->validate($request, ['information_id' => 'required|int']);
        $uid = Auth('member')->id();
        $id = $request->input('information_id');
        $field = [
            'i.id as information_id', 'i.community_id', 'i.title', 'i.summary', 'i.read_number', 'i.comment_number',
            'i.timestamp', 'i.pic', 'i.thumb', 'i.uid as follow_uid', 'i.is_pc_create', 'ic.content',
            'i.status', 'i.reason', 'u.username as author_name', 'u.email', 'c.name as community_name'
        ];
        $information = Information::from('information as i')
            ->select($field)
            ->join('information_content as ic', 'i.id', '=', 'ic.information_id')
            ->leftjoin('community as c', 'i.community_id', '=', 'c.id')
            ->leftjoin('users as u', 'i.uid', '=', 'u.id')
            ->where('i.id', $id)
            ->where('i.is_del', 0)
            ->where('type', Information::COMMUNITY_TYPE)
            ->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }
        if (empty(Community::mine($information->community_id, $uid))){
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        $information->thumb = url($information->thumb);
        $information->pic = url($information->pic);
        $information->status_name = trans('web.' . Information::$status_map[$information->status]);
        return response_json(200, trans('web.getDataSuccess'), compact('information'));
    }

    /**
     * 上下线社区资讯（此接口暂时废除）
     */
    public function upOrDown(Request $request){
        $this->validate($request, [
            'information_id' => 'required|int',
            'op' => 'required|string'
        ]);
        $uid = Auth('member')->id();
        $information_id = $request->input('information_id');
        $op = $request->input('op');
        $information = Information::where([['id', $information_id], ['type', Information::COMMUNITY_TYPE], ['is_del', 0]])->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }
        if (empty(Community::mine($information->community_id, $uid))){
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        $res = (new Information())->upAndDownInformation($information_id, $op);
        if (!$res){
            return response_json(403, trans('app.updateFailed'));
        }
        //更新社区文章数量
        $community = new Community();
        $op == 'up' ? $community->articleCountIncr($information->community_id) : $community->articleCountDecr($information->community_id);
        return response_json(200, trans('web.success'));
    }

    /**
     * 取消审核
     */
    public function cancelCheck(Request $request){
        $this->validate($request, ['information_id' => 'required|int',]);
        $uid = Auth('member')->id();
        $information_id = $request->input('information_id');
        $information = Information::where([
            ['id', $information_id],
            ['type', Information::COMMUNITY_TYPE],
            ['is_del', 0]
        ])->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }
        if ($information->status != Information::REVIEWING){
            return response_json(403, trans('web.informationMustUncheck'));
        }
        if (empty(Community::mine($information->community_id, $uid))){
            return response_json(403, trans('app.notCommunityAdmin'));
        }
        $information->status = Information::REJECT;
        $information->save();
        return response_json(200, trans('web.cancelSuccess'));
    }

}
