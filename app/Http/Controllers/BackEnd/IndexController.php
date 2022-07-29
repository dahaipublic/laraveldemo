<?php

namespace App\Http\Controllers\BackEnd;

use App\Models\AppErrror;
use App\Models\Business;
use App\Models\BusinessWallet;
use App\Models\Business\PosWallet;
use App\Models\Order;
use App\Models\BusinessOrder;
use App\Models\User;
use App\Models\UsersWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class IndexController extends Controller
{
    /**
     * 显示后台管理模板首页
     */
    public function index()
    {
        return view('backend.index');
    }

    //错误列表
    public function errorList(Request $request)
    {
        $start_date = $request->input('start_date', '');
        $end_date = $request->input('end_date', '');
        $os = $request->input('os', '');
        $error_info = trim($request->input('error_info', ''));
        $path = trim($request->input('path', ''));

        $query = AppErrror::select('*');

        $os and $query = $query->where('os', $os);
        $error_info and $query = $query->where('error', 'like', "%{$error_info}%");
        $path and $query = $query->where('route', 'like', "%{$path}%");

        if ($start_date && $end_date) {
            $query->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $query->where('created_at', '>', $start_date);
        } elseif ($end_date) {
            $query->where('created_at', '<', $end_date);
        }

        $error_list = $query->orderBy('id', 'desc')->paginate(50);

        $list = $error_list->toArray()['data'];

        return view('backend.error_list', ['error_list' => $error_list, 'list' => $list]);
    }

    //用户信息详情
    public function detail(Request $request)
    {
        $User = new User();
        $user_info = $User->getUser($request->id);
        $current_info = UsersWallet::where('uid', $request->id)->select('total_balance', 'used_balance', 'usable_balance', 'sql_total_balance', 'sql_used_balance', 'sql_usable_balance', 'current_id')->get();
        return view('backend.detail')->with(['user_info'=>$user_info, 'current_info'=>$current_info]);
    }

    //用户订单列表
    public function orderList(Request $request)
    {
        $Order = new Order();
        $UsersWallet = new UsersWallet();
        $current_id = $request->current_id??'1002';
        $wallet_id = $request->input('current_id');
        if(isset($wallet_id)){
            $current_id = $wallet_id;
        }
        $order_list = $Order->where('uid', $request->id)->where('is_done', '1')->where('status', '803')->where('current_id', $current_id)
            ->select('amount', 'uid', 'fee', 'category', 'is_send', 'current_id', 'order_sn', 'rebate', 'send_time')
            ->orderBy('send_time', 'desc')
            ->get()->toArray();
        $current_ids = ['1001', '1002', '1003', '1005', '1006', '1008'];
        $balance = $UsersWallet->update_calculate_balance($request->id, $current_id, $seller = '', $pos_id = '');
        return view('backend.order_list')->with(['id'=>$request->id, 'current_id'=>$current_id, 'order_list'=>$order_list, 'current_ids'=>$current_ids, 'balance'=>$balance]);
    }

    public function orderDetail(Request $request)
    {
        return view('backend.order_detail')->with(['order_detail'=>$order_detail]);
    }

    //商家列表
    public function businessList(Request $request)
    {
        $Business = new Business();
        $BusinessWallet = new BusinessWallet();
        $search = $request->input('search', '');
        if($search){
            $business_list = $Business->select('id', 'sellerId', 'email')->where(function ($sql) use ($search){
                return $sql->orWhere([
                    ['sellerId', $search]
                ])
                    ->orWhere([
                        ['email', $search]
                    ])
                    ->orWhere([
                        ['phone_number', $search]
                    ]);

            })->distinct()->get();
            $list = $business_list->toArray();
        }else{
            $business_list = $Business->select('id', 'sellerId', 'email')->paginate(10);
            $list = $business_list->toArray()['data'];
        }

        $current_ids = ['1001', '1002', '1003', '1005'];
//        dd($business_list);
        foreach ($list as $k =>$v){
            foreach ($current_ids as $key => $current_id){
                $a = $BusinessWallet->where('sellerId', $v['sellerId'])->where('current_id', $current_id)->select('total_balance', 'used_balance', 'usable_balance', 'sql_total_balance', 'sql_used_balance', 'sql_usable_balance', 'current_id', 'sellerId')->get()->toArray();
                $list[$k][$v['sellerId']][$current_id] = $a[0]??['usable_balance'=>0, 'sql_usable_balance'=>0];
            }
        }
        if($search){
            return view('backend.business_list', ['business_list'=>'', 'list'=>$list]);
        }
        return view('backend.business_list', ['business_list'=>$business_list, 'list'=>$list]);
    }

    //商家信息详情
    public function businessDetail(Request $request)
    {
        $Business = new Business();
        $seller_info = $Business->get_one($request->id, ['sellerId', 'email', 'phone_number']);
        $current_info = PosWallet::where('sellerId', $request->id)->select('total_balance', 'used_balance', 'usable_balance', 'sql_total_balance', 'sql_used_balance', 'sql_usable_balance', 'current_id', 'pos_id')->get();
        return view('backend.business_detail')->with(['seller_info'=>$seller_info, 'current_info'=>$current_info]);
    }

    //商家订单列表
    public function businessOrderList(Request $request)
    {
        $BusinessOrder = new BusinessOrder();
        $UsersWallet = new UsersWallet();
        $current_id = $request->current_id??'1002';
        $wallet_id = $request->input('current_id');
        if(isset($wallet_id)){
            $current_id = $wallet_id;
        }
        $order_list = $BusinessOrder->where('seller_id', $request->id)->where('is_done', '1')->where('status', '803')->where('current_id', $current_id)
            ->select('amount', 'uid', 'fee', 'category', 'is_send', 'current_id', 'order_sn', 'rebate', 'send_time')
            ->orderBy('send_time', 'desc')
            ->get()->toArray();
        $current_ids = ['1001', '1002', '1003', '1005', '1006', '1008'];
        $balance = $UsersWallet->update_calculate_balance($request->id, $current_id, $seller = '', $pos_id = '');
        return view('backend.business_order_list')->with(['id'=>$request->id, 'current_id'=>$current_id, 'order_list'=>$order_list, 'current_ids'=>$current_ids, 'balance'=>$balance]);
    }
}
