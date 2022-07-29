<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Api\Candy;
use App\Models\Api\CandyArea;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Admin\SystemOperationLog;
/**
 * @group 60活动管理
 * - author whm
 */
class CandyController extends Controller
{
    /**
     * 60.1 活动列表
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |


    |返回示例|
    |:-----  |
    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "id": 7,
    "name": "注册送金",
    "status": 1,
    "money": "0.00000000",
    "totalmoney": "0.00000000",
    "totalnumber": 0,
    "current_id": 1002,
    "rule": "1",
    "area_id": 0,
    "created_at": "2019-05-13 15:57:29",
    "updated_at": "2019-05-13 15:57:29",
    "start_time": "2019-01-02 00:00:00",
    "end_time": "2019-06-01 00:00:00",
    "is_del": 0,
    "areas": [
    {
    "candy_id": 7,
    "area_id": 1,
    "created_at": null,
    "updated_at": null
    },
    {
    "candy_id": 7,
    "area_id": 2,
    "created_at": null,
    "updated_at": null
    }
    ]
    }
    ]
    }
    ```

     **返回参数说明**
     *
    |参数名|类型|说明|
    |:-----  |:-----|----- |
     */
    public function index()
    {
        $candylist = Candy::with('arealist')->where('is_del',0)->get();
        foreach ($candylist as $k=>&$v){
//            $v->start_time=date('Y/m/d H:i:s',strtotime($v->start_time));
//            $v->end_time=date('Y/m/d H:i:s',strtotime($v->end_time));
//            $tempAreas = $v->areas();
//            foreach($tempAreas as $kk=>$vv){
//                dump($vv['candy_id']);
//            }
////            dd($tempAreas);
//            $v['arealist'] = $tempAreas;
        }
        return response_json(200,trans('web.getDataSuccess'),$candylist);
    }


    /**
     * 60.2 添加活动
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |current_id |是  |string | 赠送币种    |
    |name |是  |string | 活动名称    |
    |money |是  |int |  金额
    |start_time |是  |string | 开始时间 |
    |end_time |是  |string |结束时间  |
    |arealist |是  |数组 |区号  |


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
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_id' => 'required|string|min:1|max:5',
            'name' => 'required|string',
            'money' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'arealist' => 'required|array',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        \Log::useDailyFiles(storage_path('logs/admin/admin.log'));
        $nowtime = date('Y-m-d H:m:i',time());
        $redis_key = 'create_candy_activity_'.$request->current_id;
        if(Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])) {
            try{
                DB::beginTransaction();
            $candy = new Candy();
            $candy->name = empty($request->name)?"注册送金活动":$request->name;
            $candy->current_id = $request->current_id;
            $candy->money = $request->money;
            $candy->rule = 1;
            $candy->status = 2;
            $candy->start_time = $request->start_time;
            $candy->end_time = $request->end_time;
            $rst1 = $candy->save();
            $candyId = $candy->id;

            foreach($request->arealist as $k=>$v){
                $v = str_replace('+', '', $v);
                $insertdata[]=array('candy_id'=>$candyId,'area_id'=>$v,'created_at'=>$nowtime,'updated_at'=>$nowtime);
            }
//            dd($insertdata);
            $rst2 = CandyArea::insert($insertdata);
            if ($rst1 && $rst2) {
                Redis::del($redis_key);
                DB::commit();
                $admin = Auth('admin')->user();
                //记录日志，管理员添加活动记录
                if ($admin->language == 'cn'){
                    $msg = '管理员'.$admin->username."添加活动：".$request->name;
                }elseif($admin->language == 'en'){
                    $msg = 'Administrators '.$admin->username." add activity：".$request->name;
                }elseif($admin->language == 'hk'){
                    $msg = '管理员'.$admin->username."添加活动：".$request->name;
                }
                SystemOperationLog::add_log($admin->id,$request,$msg);
                return response_json(200,trans('web.addSuccess'));
            }else{
                Redis::del($redis_key);
                return response_json(402,trans('web.addFail'));
            }

        } catch (\Exception $exception) {
        DB::rollBack();
        Redis::del($redis_key);
//     dd($exception);

        Log::info(', message:'.$exception->getMessage()." ,trace ".$exception->getTraceAsString());
        return response_json(402,trans('web.addFail'));
    }
        }else{
            return response_json(402,trans('app.accessFrequent'));
        }
    }

    /**
     * 60.3 修改活动
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |current_id |否  |string | 赠送币种    |
    |name |否 |string | 活动名称    |
    |money |否  |int |  赠送金额  |
    |start_time |否  |string | 开始时间 |
    |end_time |否  |string |结束时间   |
    |arealist |否  |数组 |那个国家的区号  |
    |status |否  |int |1是关闭，2是开启 |
    |is_delete |否  |int |0是正常，1是删除 |
    |id |是  |int |活动id |


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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|int',
            'current_id' => 'nullable|int',
            'name' => 'nullable|string',
            'money' => 'nullable|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'arealist' => 'nullable|array',
            'status' => 'nullable|int',
            'is_delete' => 'nullable|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $nowtime = date('Y-m-d H:m:i',time());

        $candy = Candy::where('id',$request->id)->first();
        if (empty($candy)){
            return response_json(402,trans('web.updateFail'));
        }
        $redis_key = 'update_candy_activity_'.$request->current_id;
        if(Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])) {
            try{
                DB::beginTransaction();

        if (!empty($request->name)){
            $candy->name = empty($request->name)?"注册送金活动":$request->name;
        }
        if (!empty($request->money)) {
            $candy->money = $request->money;
        }
        if (!empty($request->current_id)) {
            $candy->current_id = $request->current_id;
        }
//        var_dump($request->status);
        if (!empty($request->status)) {
//            echo $request->status;
            $candy->status = $request->status;
        }
//        $candy->rule = 1;
        if (!empty($request->start_time)) {
            $candy->start_time = $request->start_time;
        }
        if (!empty($request->end_time)) {
            $candy->end_time = $request->end_time;
        }
        $rst1 = $candy->save();
        $candyId = $candy->id;
        if (!empty($request->arealist)){
            $rst3 = CandyArea::where('candy_id',$request->id)->delete();
            foreach($request->arealist as $k=>$v){
                $v = str_replace('+', '', $v);
                $insertdata[]=array('candy_id'=>$candyId,'area_id'=>$v,'created_at'=>$nowtime,'updated_at'=>$nowtime);
            }
            $rst2 = CandyArea::insert($insertdata);
        }else{
            $rst2=true;

        }

                if ($rst1 && $rst2) {
                    DB::commit();
                    //管理员修改活动
                    $admin = Auth('admin')->user();
                    //记录日志，管理员添加活动记录
                    if ($admin->language == 'cn'){
                        $msg = '管理员'.$admin->username."修改活动：".$candy->name;
                    }elseif($admin->language == 'en'){
                        $msg = 'Administrators '.$admin->username." edit activity：".$candy->name;
                    }elseif($admin->language == 'hk'){
                        $msg = '管理员'.$admin->username."修改活动：".$candy->name;
                    }
                    SystemOperationLog::add_log($admin->id,$request,$msg);
                    Redis::del($redis_key);
                    return response_json(200,trans('web.updateSuccess'));
                }else{
                    Redis::del($redis_key);
                    return response_json(402,trans('web.updateFail'));
                }

            } catch (\Exception $exception) {
                DB::rollBack();
                Redis::del($redis_key);
                \Log::useDailyFiles(storage_path('logs/admin/admin.log'));
                Log::info(', message:'.$exception->getMessage()." ,trace ".$exception->getTraceAsString());
                return response_json(402,trans('web.updateFail'));
            }
        }else{
            return response_json(402,trans('app.accessFrequent'));
        }
    }

    /**
     * 60.5 活动详情
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |id |是  |string | 活动id    |


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
    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $candy = Candy::with('arealist')->where('id',$request->id)->first();
//        $candy->start_time=date('Y/m/d H:i:s',strtotime($candy->start_time));
//        $candy->end_time=date('Y/m/d H:i:s',strtotime($candy->end_time));
        return response_json(200,trans('web.getDataSuccess'),$candy);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }
    /**
     * 60.6 删除活动
     **请求参数**
     *
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |id |是  |string | 活动id    |


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
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $candy = Candy::where('id',$request->id)->first();
        if (empty($candy)){
            return response_json(402,trans('web.deleteFail'));
        }
        $redis_key = 'delete_candy_activity_'.$request->current_id;
        if(Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])) {
            try{
                DB::beginTransaction();

            $candy->status=1;
            $candy->is_del=1;
            $rst1 = $candy->save();
//dd($rst1);
            } catch (\Exception $exception) {
                DB::rollBack();
                Redis::del($redis_key);

                \Log::useDailyFiles(storage_path('logs/admin/admin.log'));
                Log::info(', message:'.$exception->getMessage()." ,trace ".$exception->getTraceAsString());
                return response_json(402,trans('web.deleteFail'));
            }
        }else{
            return response_json(402,trans('app.accessFrequent'));
        }

        if ($rst1) {
            Redis::del($redis_key);
            DB::commit();
            $admin = Auth('admin')->user();
            //记录日志，管理员添加活动记录
            if ($admin->language == 'cn'){
                $msg = '管理员'.$admin->username."删除活动：".$candy->name;
            }elseif($admin->language == 'en'){
                $msg = 'Administrators '.$admin->username." del activity：".$candy->name;
            }elseif($admin->language == 'hk'){
                $msg = '管理员'.$admin->username."删除活动：".$candy->name;
            }
            SystemOperationLog::add_log($admin->id,$request,$msg);
            return response_json(200,trans('web.deleteSuccess'));
        }else{
            Redis::del($redis_key);
            return response_json(402,trans('web.deleteFail'));
        }
    }
}
