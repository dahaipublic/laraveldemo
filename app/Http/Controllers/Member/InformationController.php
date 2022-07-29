<?php

namespace App\Http\Controllers\Member;

use App\Models\Information\Information;
use App\Models\Information\InformationCategory;
use App\Models\Information\InformationContent;
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
 * @group 资讯
 * - author whm
 */

class InformationController extends Controller
{

    // 获取资讯分类
    public function getCategory(Request $request){

        try{

            $lang = $request->input('lang', 'cn');
            if(Auth::guard('member')->check()){
                $user = Auth::guard('member')->user();
                $lang = $user->language;
            }
            $category = (new Information())->getCategory($lang);

            return response_json(200, trans('web.getDataSuccess'), array(
                'category' => $category
            ));

        }catch(\Exception $exception) {

            Log::useFiles(storage_path('getCategory.log'));
            Log::info('getCategory,message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

        }

    }


    // 添加资讯
    public function addInformation(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|int',
            'title' => 'required|string',
            // 'summary' => 'required|string',
            'content' => 'required|string',
            'pic' => 'required|string',
            'thumb' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }


        $category_id = $request->input('category_id');
        $title = $request->input('title');
        $summary = $request->input('summary', '');
        $content = $request->input('content');
        $pic = $request->input('pic', '');
        $thumb = $request->input('thumb', '');
        $preview = $request->input('preview', 0);

        $category = InformationCategory::where('id', $category_id)
            ->where('enabled', 1)
            ->where('publish_enabled', 1)
            ->where('is_del', 0)
            ->first();
        if(empty($category)){
            return response_json(403, trans('web.categoryNotExist'));
        }

        // 生成html
//        $create_html = create_html($content);
//        if(empty($create_html)){
//            DB::rollBack();
//            return response_json(403, trans('web.addFail'), array(
//                'error' => 0
//            ));
//        }

        $now_time = date("Y-m-d H:i:s");
        $data = array(
            'uid' => $uid,
            'category_id' => $category_id,
            'title' => $title,
            'summary' => $summary ? : '',
            'pic' => $pic,
            'thumb' => $thumb,
            'status' => 0, // TODO
            'timestamp' => time(),
            'created_at' => $now_time,
            'updated_at' => $now_time,
            'type' => 1,
            'is_pc_create' => 1,
            //'html_url' => $create_html['html_url'],
            //'path_url' => $create_html['path_url'],
        );

        if(empty($preview)){

            DB::beginTransaction();

            $information_id = Information::insertGetId($data);

            $content_data = array(
                'information_id' => $information_id,
                'content' => htmlspecialchars($content),
                'created_at' => $now_time,
                'updated_at' => $now_time,
            );
            $content_insert = InformationContent::insert($content_data);

            if($information_id && $content_insert){
                DB::commit();
                return response_json(200, trans('web.addSuccess'));
            }else{
                DB::rollBack();
                return response_json(403, trans('web.addFail'));
            }
        }else{
            $redis_key = 'member_information_preview_'.$uid;
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


    // 获取预览的资讯
    public function getPreviewInformation(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        //
        $validator = Validator::make($request->all(), [
            'type' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $type = $request->input('type');
        if($type == 1){
            $redis_key = 'member_information_preview_'.$uid;
        }else{
            $redis_key = 'member_community_information_preview_'.$uid;
        }
        $data = Redis::get($redis_key);
        if(!empty($data)){
            $data = json_decode($data, true);
        }
        return response_json(200, trans('web.getDataSuccess'), array(
            'information' => $data
        ));

    }



    // 获取修改资讯的内容
    public function getInformationDetails(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'information_id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $information_id = $request->input('information_id');
        $information = Information::from("information as info")
            ->select("info.id as information_id", "info.uid as follow_uid", "info.category_id", "info.title", "info.summary", "info.thumb", "info.pic", "info.read_number", "info.comment_number", "info.timestamp", "info_con.content")
            ->join('information_content as info_con', 'info.id', '=', 'info_con.information_id')
            ->where("info.id", $information_id)
            ->where("info.uid", $uid)
            ->where('info.type', 1)
            ->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }
        $information->content = htmlspecialchars_decode($information->content);
        $information->thumb = url($information->thumb);
        $information->pic = url($information->pic);

        return response_json(200, trans('web.getDataSuccess'), array(
            'information' => $information
        ));

    }


    // 修改资讯
    public function editInformation(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'information_id' => 'required|int',
            'category_id' => 'required|int',
            'title' => 'required|string',
            // 'summary' => 'required|string',
            'content' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $information_id = $request->input('information_id');
        $information = Information::select("id", "uid", "status", "path_url")->where('uid', $uid)->where('id', $information_id)->where('type', 1)->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }

        $category_id = $request->input('category_id');
        $category = InformationCategory::where('id', $category_id)
            ->where('enabled', 1)
            ->where('publish_enabled', 1)
            ->where('is_del', 0)
            ->first();
        if(empty($category)){
            return response_json(403, trans('web.categoryNotExist'));
        }

        $title = $request->input('title');
        $summary = $request->input('summary', '');
        $content = $request->input('content');
        $pic = $request->input('pic', '');
        $thumb = $request->input('thumb', '');
        if($information->status == 1){
            $status = 1;
        }else{
            $status = 0;
        }

        // 删除之前的html
        unlink($information->path_url);
        // 生成html
//        $create_html = create_html($content);
//        if(empty($create_html)){
//            DB::rollBack();
//            return response_json(403, trans('web.addFail'), array(
//                'error' => 0
//            ));
//        }

        $now_time = date("Y-m-d H:i:s");
        $data = array(
            'category_id' => $category_id,
            'title' => $title,
            'summary' => $summary,
            'status' => $status,
            'updated_at' => $now_time,
            // 'html_url' => $create_html['html_url'],
            // 'path_url' => $create_html['path_url'],
        );

        if(!empty($pic) && !empty($thumb)){
            $data['pic'] = $pic;
            $data['thumb'] = $thumb;
        }
        Information::where('id', $information_id)->where('uid', $uid)->update($data);

        $content_data = array(
            'information_id' => $information_id,
            'content' => htmlspecialchars($content),
            'updated_at' => $now_time,
        );
        InformationContent::where('information_id', $information_id)->update($content_data);

        return response_json(200, trans('web.updateSuccess'));

    }


    // 获取自己发布的资讯列表
    public function getMyInformationList(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $status = $request->input('status', '');
        $keyword = $request->input('keyword', '');

        $query = Information::from("information as info")
            ->select("info.id as information_id", "info.uid as follow_uid", "info.thumb", "info.title", "info.summary","info.created_at as timestamp", "info.read_number", "info.comment_number", "info.reason")
            ->join('information_category as c', 'info.category_id', '=', 'c.id')
            ->where('info.uid', $uid)
            ->where('info.type', 1)
            ->where('info.is_del', 0);

        if($status != ''){
            $query->where('status', $status);
        }
        if(!empty($keyword)){
            $query->where('info.title', 'like', "%{$keyword}%");
        }

        $data = $query->orderBy('info.id', 'desc')
            ->paginate(10)
            ->toArray();

        $list = $data['data'];
        $total = $data['total'];
        $last_page = $data['last_page'];

        if(!empty($list)){
            foreach ($list as &$item){
                $item['thumb'] = url($item['thumb']);
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'list' => $list,
            'total' => $total,
            'last_page' => $last_page,
        ));

    }


    // 删除资讯文章
    public function delInformation(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'information_id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $information_id = $request->input('information_id');
        $information = Information::select("id")->where('uid', $uid)->where('id', $information_id)->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }

        Information::where('id', $information_id)->where('uid', $uid)->update([
            'is_del' => 1
        ]);

        return response_json(200, trans('web.delSuccess'));

    }


    // 取消审核
    public function cancelCheckInformation(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'information_id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $information_id = $request->input('information_id');
        $information = Information::select("id")->where('uid', $uid)->where('id', $information_id)->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }

        Information::where('id', $information_id)->where('uid', $uid)->where('status', 0)->update([
            'status' => 2
        ]);

        return response_json(200, trans('web.cancelSuccess'));

    }


    // 获取发布的资讯数量
    public function getInformationStatusNumber(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $list = Information::select("id as information_id", "status")
            ->where('uid', $uid)
            ->where('type', 1)
            ->where('is_del', 0)
            ->get()
            ->toArray();

        $status0 = 0;
        $status1 = 0;
        $status2 = 0;
        $status3 = 0;
        if(!empty($list)){
            foreach ($list as $item){
                if($item['status'] == 1){
                    $status1 += 1;
                }elseif ($item['status'] == 2){
                    $status2 += 1;
                }elseif ($item['status'] == 0){
                    $status0 += 1;
                }elseif ($item['status'] == 3){
                    $status3 += 1;
                }
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'status0' => $status0,
            'status1' => $status1,
            'status2' => $status2,
            'status3' => $status3,
        ));

    }



    // 上下线资讯
    public function upAndDownInformation(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $validator = Validator::make($request->all(), [
            'information_id' => 'required|int',
            'op' => 'required|string'
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $information_id = $request->input('information_id');
        $information = Information::select("id")->where('uid', $uid)->where('id', $information_id)->first();
        if(empty($information)){
            return response_json(403, trans('web.noSuchInformation'));
        }

        // up 上线 down 下线
        $op = $request->input('op');
        (new Information())->upAndDownInformation($information_id, $op);

        return response_json(200, trans('web.success'));

    }


    // 上传图片
    public function uploadImg(Request $request){

        $validator = Validator::make($request->all(), [
            'image' => 'required|image',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        $file = $request->file('image');
        $type = $request->input('type', 0); // 0 都有, 1 原图, 2 缩略图

        if(empty($type)){
            $images = array(
                'pic' => upload_img_file($file, 0),
                'thumb' => upload_img_file($file, 1),
            );
        }else if($type == 1){
            $images = array(
                'pic' => upload_img_file($file, 0),
                'thumb' => ''
            );
        }else{
            $images = array(
                'pic' => '',
                'thumb' => upload_img_file($file, 1),
            );
        }


        return  response_json(200,trans('web.addSuccess'), $images);

    }


}