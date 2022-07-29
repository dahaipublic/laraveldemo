<?php

namespace App\Models;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;


class UserInfo extends Model
{

    use Notifiable;
    protected $table = 'users_info';

}


