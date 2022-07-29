<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppException extends Model
{
    protected $table = 'app_exception';  //

    // 添加API调用日志错误
    public function addAppException($code = '', $error = '', $route = '', $param = array(), $header = array()){

        $now_time = date('Y-m-d H:i:s');
        $data = array(
            'code' => $code,
            'error' => $error,
            'route' => $route,
            'param' => json_encode($param),
            'header' => json_encode($header),
            'created_at' => $now_time,
            'updated_at' => $now_time,
        );
        AppException::insert($data);

    }

}
