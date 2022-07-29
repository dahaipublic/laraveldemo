<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailCode extends Model
{

    protected $table = 'email_code';
    //忘记pin码
    const TYPE_FORGET_PIN = 1;
    //忘记密码
    const TYPE_FORGET_PASSWORD = 2;
    //注册
    const TYPE_REG = 3;
    //修改pos账号密码
    const TYPE_CHANGE_POS = 4;
    //修改个人资料
    const TYPE_MODIFY_PROFILE = 5;
    //重置邮箱
    const TYPE_RESET_EMAIL = 6;
    //认证邮箱
    const TYPE_VERIFYEMAIL= 7;
    // 设置PIN
    const SET_PIN = 8;
    // 提现
    const CASH_WITHDRAWAL = 9;

    //未验证
    const STATUS_UNVERIFY = 0;
    //已验证
    const STATUS_VERIFYED = 1;
}
