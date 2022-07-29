<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdminRoles extends Model
{
    protected $table = 'admin_roles';
    protected $guarded = [];

    const SUPER_ADMIN = 1;//超级管理角色id
    const DEVELOPER = 181;//开发者角色id
}
