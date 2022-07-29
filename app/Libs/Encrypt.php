<?php
namespace App\Libs;

class Encrypt
{
    public static function ems_pwd()
    {
        $time = date('mdHis', time());
        $password = md5(config('ems.API_USERID').'00000000'.config('ems.API_PWD').$time);
        return ['timestamp'=>$time, 'password'=>$password];
    }

    public static function content($content)
    {
        return urlencode(iconv('UTF-8', 'GBK', $content));
    }

    public static function sign($id, $time, $key)
    {
        return sha1($id.$time.'zfxt'.$key);
    }

    //encrypt_openssl新版加密
    public static function encrypt_openssl($str)
    {
        $data['iv']=base64_encode(substr('fdakinel;injajdji',0,16));
        $data['value']=openssl_encrypt($str, 'AES-256-CBC', env('AES_KEY'), 0, base64_decode($data['iv']));
        $encrypt=base64_encode(json_encode($data));
        return $encrypt;
    }
    //decrypt_openssl新版解密
    public static function decrypt_openssl($encrypt)
    {
        $encrypt = json_decode(base64_decode($encrypt), true);
        $iv = base64_decode($encrypt['iv']);
        $decrypt = openssl_decrypt($encrypt['value'], 'AES-256-CBC', env('AES_KEY'), 0, $iv);
        return $decrypt;
    }
}