<?php
namespace App\Libs;

class Verify
{
    public static function isMobile($mobile)
    {
        return preg_match("/^1[34578]\d{9}$/", $mobile);
    }

    public static function isEmail($email)
    {
        return preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/', $email);
    }
}