<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'admin_permissions';

    const ENABLED = '1';
    const DISABLED = '2';

    /**
     * 获取权限列表
     * @param array $where
     * @param array $field
     * @return object
     */
    public static function getList($where = null, $field = null){
        $where = $where ?? [['is_p', 1], ['status', self::ENABLED]];
        $field = $field ?? ['id', 'pid', trans('web.admin_catalory_name') . ' as name'];
        return self::select($field)->where($where)->orderBy('order_id', 'desc')->get();
    }
}
