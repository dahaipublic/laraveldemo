<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdminAcount extends Model
{
    protected $table = 'admin_accounts';
//    protected $hidden = ['password'];
    protected $guarded = [];

    public function role()
    {
        return $this->belongsToMany(Role::class, 'admin_role_users', 'user_id', 'role_id');
    }
}
