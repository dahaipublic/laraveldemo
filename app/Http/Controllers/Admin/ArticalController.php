<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemOperationLog;
use App\Models\Community;
use App\Models\Information\Information;
use App\Models\Information\InformationContent;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class ArticalController extends Controller
{

    //只能查看或审核发现中的资讯
    protected $basic_where = [
        ['type', Information::FIND_TYPE],
        ['is_del', 0]
    ];

//    文章发布
    public function publish(Request $request)
    {
        $title = $request->input('title');
        $titleEn = $request->input('title_en');
        $content = $request->input('content');
        $contentEn = $request->input('content_en');
        DB::beginTransaction();
        $id = Information::insertGetId(['title' => $title,
            'title_en' => $titleEn]);
        if ($id) {
            $contentModel = new InformationContent();
            $contentModel->fill([
                'information_id' => $id,
                'content' => $content,
                'content_en' => $contentEn,
            ]);
            if ($contentModel->save()) {
                DB::commit();
                return response_json(200,  trans('web.getDataSuccess'));
            }
        } else {
            DB::rollBack();
            return response_json(403, trans('web.getDataFail'));
        }

    }

    // 获取列表与搜索
    public function getList(Request $request)
    {
        $type = strtolower(trim($request->input('type')));
        $pageSize = $this->page_size;
        if ($type == 'search') {
            $keywords = trim($request->input('keywords'));
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $checked = (int)$request->input('checked');//审核
            if (empty($endDate)) $endDate = date('Y-m-d');
            $information = Information::from("information as info")->select("info.*", "u.username")
                ->join('users as u', 'info.uid', '=', 'u.id')
                ->with('category', 'community')
                ->where($this->basic_where)
                ->whereBetween('info.timestamp', [strtotime($startDate), strtotime($endDate)]);
            $information->where('info.status', $checked);
            if($request->input('id')){
                $information->where('info.id', $request->input('id'));
            }
            if (empty($keywords)) {
                return response_json(200, trans('web.getDataSuccess'), $information->orderBy('info.id', 'desc')->paginate($pageSize));
            }
            $information->where(function ($query) use($keywords){
                $query->where('info.title', 'like', "%{$keywords}%")->orWhere('u.username', 'like', "%{$keywords}%");
            });
            return response_json(200, trans('web.getDataSuccess'), $information->orderBy('info.id', 'desc')->paginate($pageSize));
        } elseif ($type == 'filter') {
            $checked = (int)$request->input('checked');//审核
            $query = Information::from("information as info")->select("info.*", "u.username")
                ->join('users as u', 'info.uid', '=', 'u.id')
                ->with('category', 'community')
                ->where($this->basic_where);
            $query->where('info.status', $checked);
            if($request->input('id')){
                $query->where('info.id', $request->input('id'));
            }
            $list = $query->orderBy('info.created_at', 'desc')->paginate($pageSize);
            return response_json(200, trans('web.getDataSuccess'), $list);
        }
    }

//  审核公众号资讯
    public function check(Request $request)
    {
        $statuName = [trans('willCheck'), trans('checkPass'), trans('checkRefuse'), trans('offline')];
        $status = $request->input('status');
        $admin = Auth::guard('admin')->user();
        if (!in_array($status, [0, 1, 2, 3])) {
            return response_json(403, trans('web.parameterError'));
        }
        $articalIds = $request->input('artical_ids');
        if (!isset($articalIds)) {
            return response_json(403, trans('web.missingParameters'));
        }
        $idsArray = explode(",", $articalIds);
        $result = Information::whereIn('id', $idsArray)->where($this->basic_where)->update((function () use ($status, $request) {
            if ($status == 1) {
                return [
                    'status' => $status,
                    'check_time' => Carbon::now()
                ];
            } elseif ($status == 2) {
                return [
                    'status' => $status,
                    'reason' => $status == 2 ? $request->input('reason') : '',
                    'check_time' => Carbon::now()
                ];
            } elseif ($status == 3) {
                return [
                    'status' => $status,
                    'up_down_time' => Carbon::now()
                ];
            }
        })());
        if (!$result) {
            return response_json(403, trans('web.fail'));
        }
        $msg = "管理员{$admin->username}: 审核公众号资讯, status: {$status}, information_id: " . implode(',', $idsArray);
        SystemOperationLog::add_log($admin->id, $request, $msg);
        return response_json(200, trans('web.success'));
    }

    public function getContent(Request $request)
    {
        $information_id = $request->input('information_id');
        if (empty($information_id)) return response_json(403, trans('web.parameterError'));
        $result = InformationContent::where('information_id', $information_id)->first();;
        $result['title'] = Information::find($information_id)->title;
        $result['content'] = htmlspecialchars_decode($result->content);
        return response_json(200, trans('web.getDataSuccess'), $result);
    }

    /**
     * 删除资讯
     */
    public function del(Request $request){
        $this->validate($request, ['id' => 'required|int']);
        $id = $request->input('id');
        $admin = Auth::guard('admin')->user();
        $information = Information::where(array_merge([['id', $id]], $this->basic_where))->first();
        if (empty($information)) {
            return response_json(403,  trans('web.noSuchInformation'));
        }
        $information->is_del = 1;
        $res = $information->save();
        if (!$res){
            return response_json(403, trans('web.handleFail'));
        }
        SystemOperationLog::add_log($admin->id, $request, "管理员{$admin->username}: 删除公众号资讯, information_id: $id");
        return response_json(200,  trans('web.handleSuccess'));
    }

    /**
     * 上下线资讯
     */
    public function upOrDown(Request $request){
        $this->validate($request, [
            'information_id' => 'required|int',
            'op' => 'required|string'
        ]);
        $information_id = $request->input('information_id');
        $op = $request->input('op');
        $admin = Auth::guard('admin')->user();
        $information = Information::where(array_merge([['id', $information_id]], $this->basic_where))->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }
        $res = (new Information())->upAndDownInformation($information_id, $op);
        if (!$res){
            return response_json(403, trans('web.handleFail'));
        }
        $msg = "管理员{$admin->username}: " . ($op == 'up' ? '上' : '下') . "线公众号资讯, information_id: $information_id";
        SystemOperationLog::add_log($admin->id, $request, $msg);
        return response_json(200,  trans('web.handleSuccess'));
    }
}