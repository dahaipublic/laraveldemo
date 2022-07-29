<?php
namespace App\Libs;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/12/2
 * Time: 9:53
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;


class MyLog
{
    /**
     * @param $name
     * @param $title
     * @param $content
     * @param int $level
     * @param string $file
     * @throws Exception
     * 单文件日志
     */
    public static function log($name, $title, $content, $level = Logger::INFO, $file = 'lumen')
    {
        $log = new Logger($name);
        $log->pushHandler(new StreamHandler(storage_path('logs/' . $name . '.' . $file . '.log'), 0));
        if ($level === Logger::INFO) {
            $log->addInfo($title . '：' . $content);
        } elseif ($level === Logger::ERROR) {
            $log->addError($title . '：' . $content);
        }
    }

    /**
     * 按天文件日志
     */
    public static function dayLog($name, $title, array $content = array(), $level = Logger::INFO, $file = 'lumen')
    {
        $log = new Logger($name);
        $file = $file . date('Y-m-d', time());
        $log->pushHandler(new StreamHandler(storage_path('logs/' . $name . '.' . $file . '.log'), 0));
        if ($level === Logger::INFO) {
            $log->addInfo($title . '：' . $content);
        } elseif ($level === Logger::ERROR) {
            $log->addError($title . '：' . $content);
        }
    }

    /**
     * 信息日志
     * @param $title
     * @param $content
     * @param string $file
     * @param int type:0=单文件日志 1=按天文件日志输出
     */
    static function logInfo($title, array $content = array(), $file = 'lumen', $type = 0)
    {
        if ($type === 1) {
            self::dayLog('app', $title, $content, Logger::INFO, $file);
        } else {
            self::log('app', $title, $content, Logger::INFO, $file);
        }

    }

    /*
     * 错误日志
     * @param $title
     * @param $content
     * @param string $file
     * @param int type:0=单文件日志 1=按天文件日志输出
     */
    static function logError($title, array $content = array(), $file = 'lumen', $type = 0)
    {
        if ($type === 1) {
            self::dayLog('admin', $title, $content, Logger::ERROR, $file);
        } else {
            self::log('admin', $title, $content, Logger::ERROR, $file);
        }

    }
}