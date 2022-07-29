<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'admin_roles';

    /**
     * 获得此角色下的用户
     * 第三个参数是定义此关联的模型在连接表里的键名，第四个参数是另一个模型在连接表里的键名：
     */
    public function users()
    {
        return $this->belongsToMany('App\Models\Admin\User','admin_role_users','role_id','user_id');
    }

    /**
     * 获得此角色下的权限
     */
    public function permissions()
    {
        return $this->belongsToMany('App\Models\Admin\Permission','admin_role_permissions','role_id','permission_id');
    }
}
