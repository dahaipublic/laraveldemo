<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

//模版模型
class SystemConfig extends Model
{
    protected $table = 'admin_config';
    public $timestamps = false;
}
