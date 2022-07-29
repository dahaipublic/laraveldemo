<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;

class LoginToken extends Model
{
    protected $table = 'login_token';
    protected $fillable = array('token', 'uid','tokentext','type');

}
