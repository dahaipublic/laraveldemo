<?php
namespace App\Libs;

use App\Models\ActionLog;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Business\Pos;
use App\Models\Route;
use App\Models\User;
use App\Models\UsersWallet as Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Common
{
    public static function getSideBar()
    {
        $result = Cache::get('user_route', function() {
            $colletion = array();
            $auth_routes = array();
            $result = DB::table('level_route')->select('route_id')
                ->where('level', User::LEVEL)
                ->get();
            foreach ($result as $value) {
                $auth_routes[] = $value->route_id;
            }
            foreach (Route::getRoutes($auth_routes) as $value) {
                $colletion[$value->id] = get_object_vars($value);
            }
            $result = Tool::getTree($colletion);
            Cache::put('user_route', $result, 60);
            return $result;
        });
        return $result;
    }

    public static function getManagerSideBar()
    {
        $result = Cache::get('manager_route', function() {
            $colletion = array();
            $auth_routes = array();
            $result = DB::table('level_route')->select('route_id')
                ->where('level', Admin::LEVEL)
                ->get();
            foreach ($result as $value) {
                $auth_routes[] = $value->route_id;
            }
            foreach (Route::getRoutes($auth_routes) as $value) {
                $colletion[$value->id] = get_object_vars($value);
            }
            $result = Tool::getTree($colletion);
            Cache::put('manager_route', $result, 60);
            return $result;
        });
        return $result;
    }

    public static function getPosSideBar()
    {
        $result = Cache::get('pos_route', function() {
            $colletion = array();
            $auth_routes = array();
            $result = DB::table('level_route')->select('route_id')
                ->where('level', Pos::LEVEL)
                ->get();
            foreach ($result as $value) {
                $auth_routes[] = $value->route_id;
            }
            foreach (Route::getRoutes($auth_routes) as $value) {
                $colletion[$value->id] = get_object_vars($value);
            }
            $result = Tool::getTree($colletion);
            Cache::put('pos_route', $result, 60);
            return $result;
        });
        return $result;
    }

    public static function log($data, $batch_flag = 0)
    {
        if ($batch_flag === 0) {
            $action = new ActionLog();
            $action['admin_id'] = $data[0];
            $action['sellerId'] = $data[1];
            $action['actions'] = $data[2];
            $action['ip'] = $data[3];
            $action['created_at'] = date('Y-m-d H:i:s', time());
            $result = $action->save();
        } else {

        }
        return $result ? ['code'=>200] : ['code'=>400];
    }

    public static function makeVerificationApi()
    {
        $time = time();
        $id = config('api.id');
        $key = config('api.key');
        return [
            'id' => $id,
            'time' => $time,
            'sign' => Encrypt::sign($id, $time, $key)
        ];
    }

    public static function balance_format($balance)
    {
        return number_format($balance, 8, '.', '');
    }

    public static function money_format($money)
    {
        return number_format($money, 2, '.', '');
    }

    public static function get_balance($seller, $time)
    {
        $order = Order::where('is_send', 2)
            ->where('seller_id', $seller)
            ->where('is_done', 1)
            ->where('pos_id', '<>', '')
            ->where('send_time', '<=', date('Y-m-d H:i:s', $time))
            ->groupBy('pos_id', 'wallet_id')
            ->select(DB::Raw('pos_id, sum(amount) as amount, wallet_id, sum(fee) as fee'))
            ->get();
        $move_order = Order::where('is_send', 1)
            ->where('seller_id', $seller)
            ->where('is_done', 1)
            ->where('pos_id', '<>', '')
            ->where('send_time', '<=', date('Y-m-d H:i:s', $time))
            ->groupBy('pos_id', 'wallet_id')
            ->select(DB::Raw('pos_id, sum(amount) as amount, wallet_id, sum(fee) as fee'))
            ->get();
        $balance = array();
        foreach ($order as $item) {
            switch ($item->wallet_id) {
                case Wallet::CURRENCY_BTC:
                    $balance[$item->pos_id]['btc'] = $item->amount - $item->fee;
                    break;
                case Wallet::CURRENCY_RPZ:
                    $balance[$item->pos_id]['rpz'] = $item->amount - $item->fee;
                    break;
                default:
                    break;
            }
        }
        foreach ($move_order as $item) {
            switch ($item->wallet_id) {
                case Wallet::CURRENCY_BTC:
                    $balance[$item->pos_id]['btc'] = $balance[$item->pos_id]['btc'] - $item->amount + $item->fee;
                    break;
                case Wallet::CURRENCY_RPZ:
                    $balance[$item->pos_id]['rpz'] = $balance[$item->pos_id]['rpz'] - $item->amount + $item->fee;
                    break;
                default:
                    break;
            }
        }
        foreach ($balance as &$item) {
//            $item['rpz'] -= Wallet::CASH_PLEDGE;
        }
        return $balance;
    }

    public static function get_pos_balance($pos_id, $time)
    {
        $order = Order::where('is_send', 2)
            ->where('pos_id', $pos_id)
            ->where('send_time', '<=', date('Y-m-d H:i:s', $time))
            ->groupBy('wallet_id')
            ->select(DB::Raw('sum(amount) as amount, wallet_id, sum(fee) as fee'))
            ->get();
        $move_order = Order::where('is_send', 1)
            ->where('pos_id', $pos_id)
            ->where('send_time', '<=', date('Y-m-d H:i:s', $time))
            ->groupBy('wallet_id')
            ->select(DB::Raw('sum(amount) as amount, wallet_id, sum(fee) as fee'))
            ->get();
        $balance = array();
        foreach ($order as $item) {
            switch ($item->wallet_id) {
                case Wallet::CURRENCY_BTC:
                    $balance[$pos_id]['btc'] = $item->amount - $item->fee;
                    break;
                case Wallet::CURRENCY_RPZ:
                    $balance[$pos_id]['rpz'] = $item->amount - $item->fee;
                    break;
                default:
                    break;
            }
        }
        foreach ($move_order as $item) {
            switch ($item->wallet_id) {
                case Wallet::CURRENCY_BTC:
                    $balance[$pos_id]['btc'] = $balance[$pos_id]['btc'] - $item->amount + $item->fee;
                    break;
                case Wallet::CURRENCY_RPZ:
                    $balance[$pos_id]['rpz'] = $balance[$pos_id]['rpz'] - $item->amount + $item->fee;
                    break;
                default:
                    break;
            }
        }
        $balance[$pos_id]['rpz'] -= Wallet::CASH_PLEDGE;
        return $balance;
    }
}