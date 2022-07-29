<?php


namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\HelperCenter;
use App\Models\Information\Information;
use Illuminate\Http\Request;

class HelperCenterController extends Controller
{
    /**
     * 添加/修改帮助文章
     */
    public function save(Request $request)
    {
        $this->validate($request, [
            'id' => 'nullable|int',
            'title' => 'required|string',
            'content' => 'required|string',
            'sort' => 'nullable',
        ]);
        $account_id = \Auth::guard('admin')->id();
        $content = trim_script($request->input('content'));
        $id = $request->input('id');
        $title = $request->input('title');
        $sort = $request->input('sort');

        if ($id) {
            if (empty($model = HelperCenter::find($id))) {
                return response_json(403, trans('web.noSuchHelperCenter'));
            }
        } else {
            $model = new HelperCenter();
            $model->account_id = $account_id;
        }
        $html_content = $model->setContentStyle($title, $content);
        $model->html_path = $html_content;
        $model->content = $content;
        $model->title = $title;
        $model->sort = (int)$sort;
        $res = $model->save();
        if (empty($res)) {
            return response_json(403, trans('web.' . ($id ? 'editFail' : 'addFail')));
        }
        return response_json(200, trans('web.' . ($id ? 'editSuccess' : 'addSuccess')));
    }

    /**
     * 获取帮助详情
     */
    public function getDetail(Request $request)
    {
        $this->validate($request, ['id' => 'required|int']);
        $id = $request->input('id');
        $info = HelperCenter::where('id', $id)->where('is_del', 0)->first(['id', 'title', 'content', 'sort']);
        if (empty($info)) {
            return response_json(403, trans('web.noSuchHelperCenter'));
        }
        return response_json(200, trans('web.getDataSuccess'), compact('info'));
    }

    /**
     * 删除帮助
     */
    public function del(Request $request)
    {
        $this->validate($request, ['id' => 'required|int']);
        $id = $request->input('id');
        $res = HelperCenter::where('id', $id)->update(['is_del' => 1]);
        if ($res === false) {
            return response_json(403, trans('web.delFail'));
        }
        return response_json(200, trans('web.delSuccess'));
    }

    /**
     * 获取帮助列表
     */
    public function getList()
    {
        $data = (new HelperCenter())->getList([], ['*'], false, $this->page_size);
        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];
        $page_size = $this->page_size;
        return response_json(200, trans('web.getDataSuccess'), compact('list', 'total', 'last_page', 'page_size'));
    }
}