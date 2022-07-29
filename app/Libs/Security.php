<?php
namespace App\Libs;

class Security
{
    const SALT = 'Laravel_Rapidz';
    protected static $_instance = null;
    protected $action;
    protected static $secret_key;
    protected static $iv;
    protected $options;
    protected static $index_one_key = 'sadkfjskfslkdfjiewsdkjfksdfsfqqwkjekqkjnq';

    public function __construct($type = 'FM')
    {
        switch ($type){
            case 'FM':
                self::set_fm_key();
                self::set_iv();
                break;
            case 'RP':
                self::set_rp_key();
                self::set_iv();
                break;
            default:
                static::$secret_key = md5(self::$index_one_key.self::SALT);
                self::set_iv();
                break;
        }
    }

    public static function get_instance($type = 'FM'){
        if (!self::$_instance instanceof Security)
            self::$_instance = new self($type);
        return self::$_instance;
    }

    public static function set_fm_key()
    {
        if(getenv('SERVER_ADDR') == '148.66.58.154'){
            self::$secret_key = '1baf60d1dfa15dbb285d1a210bfa69dd';
        }else{
            self::$secret_key = 'b5e899e173da0069bb84aa2f06c187b3';
        }
    }

    public static function set_rp_key()
    {
        if(getenv('SERVER_ADDR') == '148.66.58.154'){
            self::$secret_key = '1baf60d1dfa15dbb285d1a210bfa69dd';
        }else{
            self::$secret_key = 'b5e899e173da0069bb84aa2f06c187b3';
        }
    }

    public static function set_iv()
    {
        self::$iv = md5(self::$secret_key, 16);
    }

    public function encrypt($string)
    {
        return openssl_encrypt($string, 'AES-256-CBC', self::$secret_key, 0, self::$iv);
    }

    public function decrypt($string)
    {
        return openssl_decrypt(base64_decode($string), 'AES-256-CBC', self::$secret_key , 1, self::$iv);
    }
}