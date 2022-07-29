<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SystemOperationLog extends Model
{
    //
    protected $table = 'admin_operation_logs';

    //管理员操作日志
    public static function add_log($id,$request,$msg){
        $log = new SystemOperationLog();
        $log->admin_id = $id;
        $log->path = $request->path();
        $log->method = $request->method();
        $log->ip = $request->getClientIp();
        $log->input = json_encode($msg);
        $log->save();
    }
}
