<?php

namespace App\Models\Admin;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable 
{
    use Notifiable;

    protected $table = 'admin_accounts';
    const GROUP_SUPER_MANAGER = 1;

    const GROUP_NORMAL_MANAGER = 2;

    const GROUP_SUPPORT = 3;

    const GROUP_CUSTOMER_MANAGER = 4;

    const STATUS_ALLOW = 1;

    const STATUS_DISABLED = 0;
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public static $group = [
        self::GROUP_SUPER_MANAGER => 'Super Administrator',
        self::GROUP_NORMAL_MANAGER => 'Normal Administrator',
        self::GROUP_SUPPORT => 'SUPPORT',
        self::GROUP_CUSTOMER_MANAGER => 'Customer Administrator'
    ];
   
    /**
     * 获得此用户的角色
     */
    public function roles()
    {
        return $this->belongsToMany('App\Models\Admin\Role','admin_role_users','user_id','role_id');
    }

   /**
     * 此用户与权限表的关联
     */
    public function permissions()
    {
        return $this->belongsToMany('App\Models\Admin\Permission','admin_user_permissions','user_id','permission_id');
    }

    /**
     * 获取该用户的所有权限
     */
    public function allPermissions()
    {
         return $this->roles()->with('permissions')->get()->pluck('permissions')->flatten()->merge($this->permissions);
    }

    /**
     * 检查该用户是否拥有该权限
     *
     * @param $permission
     *
     * @return bool
     */
    public function canthrough(string $permission)
    {
        //指定某个用户是否拥有指定权限
        // if ($this->permissions->pluck('slug')->contains($permission)) {
        //     return true;
        // }

        return $this->roles->pluck('permissions')->flatten()->pluck('slug')->contains($permission);
    }

    public function isRole(string $role) : bool
    {
        return $this->roles->pluck('slug')->contains($role);
    }
}
