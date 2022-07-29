<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/24
 * Time: 16:08
 * 文件日志写入
 */

namespace App\Libs;

class MyLogs
{
    /**
     * MyLogs constructor.
     * @param $message  规定传json或array
     * @param string $file 如果需要指定写入文件必传 不写入存在mylogs/mylogs下
     * @param string $day 如果需要按天写入传1
     */
    public static function MyLogs($message, $file = '', $day = '')
    {
        if (empty($file)) {
            $log_dir = storage_path('mylogs/mylogs');
        } else {
            $log_dir = storage_path('mylogs') . '/' . $file;
        }
        if (empty($day) && $day == 1) {
            $dayName = date('Y-m-d');
        } else {
            $dayName = '';
        }
        $files = $log_dir . $dayName . '.log';
        $dir_name = dirname($files);
        //判断是否存在文件夹，没有则创建
        //目录不存在就创建
        if (!file_exists($dir_name)) {
            //iconv防止中文名乱码
            mkdir(iconv("UTF-8", "GBK", $dir_name), 0777, true);
        }
        $time = date('Y-m-d H:i:s', time()) . ' ';
        //将错误日志记录写入文件中
        if (is_array($message)) {
            $message = json_encode($message, 1);
            file_put_contents($files, $time . $message . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents($files, $time . $message . PHP_EOL, FILE_APPEND);
        }
    }
}