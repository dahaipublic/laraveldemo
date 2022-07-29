<?php
namespace App\Libs;

class Curl
{
    public static function get($url)
    {
        //初始化一个 cURL 对象
        $ch  = curl_init();
        //设置你需要抓取的URL
        curl_setopt($ch, CURLOPT_URL, $url);
        // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //是否获得跳转后的页面
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public static function post($url, $post_data=null, $header=null)
    {
        $api = Common::makeVerificationApi();
        $post_data = $post_data ? array_merge($api, $post_data) : $api;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        if ($header) curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public static function emsPost($url, $post_data)
    {
        $attributes = array('Accept:text/plain;charset=utf-8', 'Content-Type:application/json', 'charset=utf-8', 'Expect:', 'Connection: Close');//请求属性
        $ch = curl_init();//初始化一个会话
        /* 设置验证方式 */
        curl_setopt($ch, CURLOPT_HTTPHEADER, $attributes);//设置访问
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//设置返回结果为流
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);//设置请求超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);//设置响应超时时间
        /* 设置通信方式 */
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);//使用urlencode格式请求

        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

}