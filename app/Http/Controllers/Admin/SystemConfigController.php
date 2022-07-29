<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\SystemConfig;
use App\Models\Admin\SystemOperationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemConfigController extends Controller
{
    public function list()
    {
        $system = SystemConfig::get();
        return response_json(200, trans('web.getDataSuccess'), $system);
    }

    public function edit(Request $request)
    {
        $data = $request->all();
        $admin = Auth::guard('admin')->user();
        $keys = SystemConfig::pluck("name")->toArray();
        $edit_config_names = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $keys)) {
                SystemConfig::where('name', $k)->update([
                    'value' => $v,
                    'time' => Carbon::now()
                ]);
                $edit_config_names[] = $k;
            }
        }
        $msg = "管理员{$admin->username}: 编辑系统配置，配置名称列表：" . implode(',', $edit_config_names);
        SystemOperationLog::add_log($admin->id, $request, $msg);
        return response_json(200, trans('web.getDataSuccess'));
    }

    public function create()
    {

    }
}
