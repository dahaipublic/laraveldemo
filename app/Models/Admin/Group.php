<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

//模版模型
class Group extends Model
{
    protected $table = 'group';
    public function user(){
        return $this->belongsToMany(User::class,'group_user','group_id','user_id');
    }
}
