<?php

namespace App\Http\Controllers\Member;

use App\Jobs\SendMessageText;
use App\Jobs\SendTransferEmail;
use App\Models\Currency;
use App\Models\Friend;
use App\Models\MailCode;
use App\Models\Order;
use App\Models\RedPacket;
use App\Models\User;
use App\Models\UsersWallet;
use App\Models\UsersWithdrawAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;


if(isset($_SERVER['HTTP_ORIGIN'])){
    header("access-control-allow-origin: ".$_SERVER['HTTP_ORIGIN']);
    header("access-control-allow-headers: Origin, Content-Type, Cookie, X-CSRF-TOKEN, Accept, Authorization, X-XSRF-TOKEN");
    header("access-control-expose-headers: Authorization, authenticated");
    header("access-control-allow-methods: POST, GET, PATCH, PUT, OPTIONS");
    header("access-control-allow-credentials: true");
    header("access-control-max-age: 2592000");
}


/**
 * @group 钱包信息
 * - author whm
 */

class WalletController extends Controller
{

    // 获取充值地址
    public function getRechargeAddress(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $can_withdraw = $request->input('can_withdraw', 0);
        $list = (new User())->getRechargeAddress($user, $can_withdraw);

        return response_json(200, trans('web.getDataSuccess'), array(
            'list' => $list
        ));

    }


    // 数字货币提现
    public function digitalCurrencyWithdraw(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        if(empty($user->status)){
            return response_json(403, trans('web.forbidden'));
        }
        // 商家不能转账
        if($user->sellerId){
            return response_json(403, trans('web.sellerCanNotTransfer'));
        }

        $users = new User();
        $integral = 0;
        $order_id = 0;

        $order_model = new Order();
        // 转账类型 ： 1 为 钱包中转账 ， 2 为聊天中转账
        // 1 为 钱包中转账（1时传to_address） ， 2 为聊天中转账（2时传to_uid）
        $type = $request->input('type', 1);
        if($type == 1){
            $validator = Validator::make($request->all(), [
                'current_id' => 'required',
                'to_address' => 'required',
                'amount' => 'required|numeric',
                'code' => 'required|string|min:6|max:6',
                'form_token' => 'required|string',
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'current_id' => 'required|int',
                'to_uid' => 'required',
                'amount' => 'required|numeric',
                'code' => 'required|string|min:6|max:6',
                'form_token' => 'required|string',
            ]);
        }
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $form_token = $request->input('form_token');
        $form_token_key = 'form_token_wallet_transfer'.$uid;
        if (Redis::get($form_token_key) === $form_token) {
            $order_sn = Order::getOrderSnByFormToken($uid, $form_token);
            if($order_sn){
                return response_json(402, trans('web.orderHasBeenSubmit').$order_sn);
            }else{
                return response_json(402, trans('web.doNotSubmitAgain'));
            }
        }else{
            // 缓存个10分钟
            Redis::setex($form_token_key, 600, $form_token);
        }

        $redis_key = 'api_wallet_pay'.$uid;
        if(Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])){

            try{

                $code = $request->input('code');
                // 验证邮件验证码
                $record = MailCode::where('email', $user->email)
                    ->where('expire_time', '>', date('Y-m-d H:i:s'))
                    ->where('user_id', $uid)
                    ->where('type', MailCode::CASH_WITHDRAWAL)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->where('verify_status', 0)
                    ->orderBy('expire_time', 'desc')
                    ->first();
                if (!$record) {
                    return response_json(402, trans('web.youdonotsendtheemail'));
                }elseif ($record->code != $code){
                    return response_json(402, trans('web.codeUndefined'));
                }
                // 已使用
                $record->status = 1;
                $record->save();

                $current_id = $request->input('current_id');
                $transfer_account = $request->input('amount', 0);
                $fc_money = $request->input('fc_money', 0);
                $to_address = $request->input('to_address', '');
                $to_uid = $request->input('to_uid', 0);

                // 开始一个事务
                DB::beginTransaction();

                $check_amount = check_amount($transfer_account, $current_id);
                if($check_amount['code'] != 200){
                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json($check_amount['code'], $check_amount['msg']);
                }

                // 用哪个钱包转账
                $wallet_model = new UsersWallet();
                $wallet = $wallet_model->get_users_wallet($uid, $current_id);
                if(empty($wallet)){
                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json(403, trans('web.noSuchWallet'));
                }else{
                    if($type == 1){
                        if($wallet->address == $to_address){
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, trans('web.unableToTransferToYourself')); //// 不能转账给自己
                        }
                        if($wallet->can_transfer != 1){
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, trans('web.suchCurrencyDoNotTransfer'));
                        }
                    }else{
                        if($wallet->can_friend_transfer != 1){
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, trans('web.suchCurrencyDoNotTransfer'));
                        }
                    }
                }

                $to_username = $to_address;
                //////////////////////////////////////////////////
                $usable_balance = $wallet->usable_balance;
                // 1 为 钱包中转账（1时传to_address） ， 2 为聊天中转账（2时传to_uid）
                $to_wallet = null;
                $to_portRaitUri = 'storage/img/defaultlogo.png';
                if($type != 1){
                    // 是不是好友
                    $friend_model = new Friend();
                    $is_friend = $friend_model->isFriends($uid, $to_uid);
                    if(!$is_friend){
                        DB::rollBack();
                        Redis::del($redis_key);
                        return response_json(403, trans('web.isNotYourGoodFriend'));
                    }
                    $to_wallet = UsersWallet::from('users_wallet as w')
                        ->select("w.id as wallet_id", "w.uid", "w.address", "w.current_id", "w.total_balance", "w.usable_balance", "w.address", "u.nickname", "u.username", "u.portRaitUri")
                        ->join('currency as c', 'w.current_id', '=', 'c.current_id')
                        ->join('users as u', 'w.uid', '=', 'u.id')
                        ->where('w.current_id', $current_id)
                        ->where('w.uid', $to_uid)
                        ->first();
                    if(empty($to_wallet)){
                        DB::rollBack();
                        Redis::del($redis_key);
                        return response_json(403, trans('web.transferUsersIsNonexistent'));
                    }
                    $to_address = $to_wallet->address;
                    // 有备注则用 好友备注
                    $to_username = $is_friend->display_name ?  : $to_wallet->username;
                }else{
                    // BTC 转账暂时不能大于1个， 转账BTC过大记得发邮件
                    if($current_id == UsersWallet::BTC){
                        if($transfer_account >= 1){
                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, trans('web.amountIsTooLarge'));
                        }
                    }
                    // 验证地址
                    $wallet_model = new UsersWallet($current_id);
                    if(!$wallet_model->validate_address($to_address)){
                        DB::rollBack();
                        Redis::del($redis_key);
                        return response_json(403, trans('web.addressNotFound'));
                    }
                    $to_wallet = UsersWallet::from('users_wallet as w')
                        ->select("w.id as wallet_id", "w.uid", "w.address", "w.current_id", "w.total_balance", "w.usable_balance", "w.address", "u.nickname", "u.username", "u.portRaitUri")
                        ->join('currency as c', 'w.current_id', '=', 'c.current_id')
                        ->join('users as u', 'w.uid', '=', 'u.id')
                        ->where('w.current_id', $current_id)
                        ->where('w.address', $to_address)
                        ->first();
                    if(!empty($to_wallet)){
                        $to_address = $to_wallet->address;
                        $to_portRaitUri = $to_wallet->portRaitUri;
                        // 是不是好友 有备注则用 好友备注
                        $friend_model = new Friend();
                        $is_friend = $friend_model->isFriends($uid, $to_uid);
                        if($is_friend){
                            $to_username = $is_friend->display_name ? : $to_wallet->username;
                        }else{
                            $to_username = $to_wallet->nickname ? : $to_wallet->username;
                        }
                    }
                }
                $usable_balance = $wallet->usable_balance;
                // $fee = ($type==1) ? $wallet->fee : 0;
                $fee = ($type==1) ? Currency::getTransferFee($current_id, $transfer_account, $wallet->fee) : 0;
                $decimals = UsersWallet::get_decimals($current_id);
                // 转账金额 + 费率(预算手续费) > 用户余额
                if(bcsub(bcadd($transfer_account, $fee, $decimals), $usable_balance, $decimals) > 0){
                    DB::rollBack();
                    Redis::del($redis_key);
                    // 最大实际可转余额
                    if($type == 1){
                        $not_enough_str = Currency::getBalanceNotEnoughStr($wallet->fee, $wallet->unit);
                        return response_json(403, $not_enough_str);
                    }else{
                        return response_json(403, trans('web.creditNotEnough'));
                    }
                }

                $wallet_model = new UsersWallet($current_id);
                $now_time = date('Y-m-d H:i:s');
                $order_sn = create_order_sn('TR');
                $after_balance = bcsub($wallet->usable_balance, bcadd($transfer_account, $fee, $decimals), $decimals);

                // 1 为 钱包中转账（1时传to_address） ， 2 为聊天中转账（2时传to_uid）
                if($type != 1){

                    // canTransfer 用户是否可以转账
                    $consume_amount = bcadd($transfer_account, $fee, $decimals);
                    $can_transfer = $order_model->canTransfer($user, $consume_amount, $wallet);
                    if($can_transfer['code'] != 200){
                        DB::rollBack();
                        Redis::del($redis_key);
                        return response_json($can_transfer['code'], $can_transfer['msg']);
                    }

                    $packet = array(
                        'uid' => $uid,
                        'order_sn' => $order_sn,
                        'red_type' => 2, // 1个人红包，2 个人转账，3普通群红包，4手气群红包
                        'target_id' => $to_uid,
                        'wallet_id' => $wallet->id,
                        'current_id' => $current_id,
                        'amount' => $transfer_account,
                        'number' => 1,
                        'surplus_amount' => $transfer_account,
                        'surplus_number' => 1,
                        'created_at' => $now_time,
                        'updated_at' => $now_time,
                        'address' => $wallet->address,
                        'to_username' => $to_username,
                        'status' => 1
                    );
                    $packet_insert = RedPacket::insert($packet);

                    // send
                    $order = array(
                        'order_sn' => $order_sn,
                        'uid' => $uid,
                        'wallet_id' => $wallet->id,
                        'current_id' => $current_id,
                        'address' => $to_address,
                        'send_time' => $now_time,
                        'confirm_time' => $now_time,
                        'fee' => 0,     // 手续费
                        'money' => $fc_money,
                        'pay_money' => $fc_money,
                        'total_amount' => $transfer_account,
                        'amount' => $transfer_account,   // 订单总价
                        'unit' => $wallet->unit,
                        'category' => 'move',
                        'is_send' => 1,
                        'status' => 803,
                        'is_done' => 1,
                        'integral' => $integral,
                        'rechargeType' => 8,   // 0转账；1手机充值；2智能卡充值；3充值返币；4充值退币；5 领取锐币；6pos消费；7 兑换；8聊天转账；9红包；10邀请注册赠币；11聊天转账退款；12红包退款 ; 14商城订单；15付款码支付;16 向商家付款; 17积分兑换货币; 18 手续费
                        'send_user_name' => $user->username,
                        'before_balance' => $wallet->usable_balance,
                        'after_balance' => $after_balance,
                        'form_token' => $form_token
                    );
                    $order_id = Order::insertGetId($order);

                    // update
                    $wallet_update = UsersWallet::where('uid', $uid)
                        ->where('current_id',$current_id)
                        ->where('used_balance', $wallet->used_balance)
                        ->where('usable_balance', $wallet->usable_balance)
                        ->update([
                            'used_balance' => bcadd($wallet->used_balance, $consume_amount, $decimals),
                            'usable_balance' => bcsub($wallet->usable_balance, $consume_amount, $decimals)
                        ]);

                    if($order_id && $packet_insert && $wallet_update){

                        DB::commit();

                        $message = (new Currency())->getPushMessage(1, $transfer_account, $wallet->unit, $user->language);
                        SendMessageText::dispatch(array(
                            'lang' => $user->language,
                            'uid' => $uid,
                            'title' => trans('web.orderType8'),
                            'message' => $message,
                        ));

                        $member = $users->getRankByIntegral(bcadd($user->member_integral, $integral, 0));
                        return response_json(200, trans('web.transferSuccess'), array(
                            'order_id' => $order_id,
                            'order_sn' => $order_sn,
                            'icon' => url($wallet->icon),
                            'amount' => bcadd($transfer_account, 0, $decimals),
                            'portRaitUri' => url($to_wallet->portRaitUri),
                            'integral' => intval(bcadd($user->integral, $integral)),
                            'member_id' => $member['member_id'],
                            'member_name' => $member['member_name'],
                            'transfer_user_name' =>  $to_username
                        ));
                    }else{

                        DB::rollBack();
                        Redis::del($redis_key);
                        return response_json(403, trans('web.transferFail'), array('error' => 2));

                    }

                }else{

                    // send
                    $order = array(
                        'order_sn' => $order_sn,
                        'uid' => $uid,
                        'wallet_id' => $wallet->id,
                        'current_id' => $current_id,
                        'address' => $to_address,
                        'send_time' => $now_time,
                        'fee' => '-'.$fee,     // 手续费
                        'total_amount' => $transfer_account,
                        'amount' => $transfer_account,   // 订单总价
                        'money' => $fc_money,
                        'pay_money' => $fc_money,
                        'unit' => $wallet->unit,
                        'category' => 'send',
                        'is_send' => 1,
                        'status' => 803,
                        'is_done' => 1,
                        'integral' => $integral,
                        'rechargeType' => 0,   // 0 转账；1手机充值；2智能卡充值；3充值返币；4充值退币；5 领取锐币；6pos消费；7 兑换；8聊天转账；9红包；10邀请注册赠币；11聊天转账退款；12红包退款
                        'send_user_name' => $user->username,
                        'before_balance' => $wallet->usable_balance,
                        'after_balance' => $after_balance,
                        'form_token' => $form_token
                    );
                    // 如果是RPZX BNB，进行ETH手续费抵扣操作
                    if($current_id == UsersWallet::RPZX || $current_id == UsersWallet::BNB){
                        // 判断用户ETH余额够不够扣手续费
                        // gas limit 2019-03-11 田闯说 eth_fee <= 0.00009
                        $eth_model = new UsersWallet(UsersWallet::ETH);
                        $eth_fee = $eth_model->get_eth_gas_limit($wallet->address, $to_address, $transfer_account);
                        $eth_usable_balance = UsersWallet::where('uid', $uid)->where('current_id', UsersWallet::ETH)->value('usable_balance');
                        if(bcsub($eth_usable_balance, $eth_fee, 8) < 0){
                            DB::rollBack();
                            Redis::del($redis_key);
                            $not_enough_str = Currency::getBalanceNotEnoughStr($eth_fee, 'ETH');
                            return response_json(403, $not_enough_str);
                        }
                        $order['fee'] = 0;
                        $order['confirms'] = 8;
                        $order['confirm_time'] = $now_time;
                    }elseif ($current_id == UsersWallet::USDT){
                        $btc_usable_balance = UsersWallet::where('uid', $uid)->where('current_id', UsersWallet::BTC)->value('usable_balance');
                        $btc_fee = $fee;
                        if(bcsub($btc_usable_balance, $btc_fee, 8) < 0){
                            DB::rollBack();
                            Redis::del($redis_key);
                            $not_enough_str = Currency::getBalanceNotEnoughStr($btc_fee, 'BTC');
                            return response_json(403, $not_enough_str);
                        }
                        $order['fee'] = 0;
                        $order['confirms'] = 8;
                        $order['confirm_time'] = $now_time;
                    }
                    $order_id = Order::insertGetId($order);

                    if($order_id){

                        $txid = $wallet_model->send_from($uid, $to_address, $transfer_account, $wallet->address);
                        if($txid) {

                            $txid_transaction = $wallet_model->get_transaction($txid);
                            if($txid_transaction){
                                $fee = number_format(abs($txid_transaction['fee']), 8);
                                $confirms = $txid_transaction['confirmations'];
                                if($current_id == UsersWallet::RPZX){
                                    $wallet_model->deductEthFee($user, $order_sn, $to_address, $fee, $txid);
                                    $fee = 0;
                                }
                                elseif ($current_id == UsersWallet::USDT){
                                    $wallet_model->deductBtcFee($user, $order_sn, $to_address, $fee, $txid);
                                    $fee = 0;
                                }
                                Order::where('id', $order_id)->update(
                                    [
                                        'fee' => '-'.$fee,
                                        'confirm_time' => $now_time,
                                        'confirms' => $confirms,
                                        'after_balance' => bcsub($wallet->usable_balance, bcadd($transfer_account, $fee, $decimals), $decimals)
                                    ]
                                );
                            }
                            $order_model->update_jyXID($order_id, $txid);
                            UsersWallet::where('uid', $uid)
                                ->where('current_id',$current_id)
                                ->where('used_balance', $wallet->used_balance)
                                ->where('usable_balance', $wallet->usable_balance)
                                ->update([
                                    'used_balance' => bcadd($wallet->used_balance, bcadd($transfer_account, $fee, $decimals), $decimals),
                                    'usable_balance' => bcsub($wallet->usable_balance, bcadd($transfer_account, $fee, $decimals), $decimals)
                                ]);

                            $wallet_model->update_calculate_balance($uid, $current_id);

                            DB::commit();

                            $email = array(
                                'uid' => $uid,
                                'email' => $user->email,
                                'username' => $user->username,
                                'to_username' => $to_username,
                                'transfer_account' => $transfer_account,
                                'unit' => $wallet->unit,
                                'lang' => $user->language
                            );
                            SendTransferEmail::dispatch($email)->onQueue('transfer_mail');

                            $message = (new Currency())->getPushMessage(1, $transfer_account, $wallet->unit, $user->language);
                            SendMessageText::dispatch(array(
                                'lang' => $user->language,
                                'uid' => $uid,
                                'title' => trans('web.orderType0'),
                                'message' => $message,
                            ));

                            $member = $users->getRankByIntegral(bcadd($user->member_integral, $integral));
                            return response_json(200, trans('web.transferSuccess'), array(
                                'order_id' => $order_id,
                                'icon' => url($wallet->icon),
                                'amount' => bcadd($transfer_account, 0, $decimals),
                                'portRaitUri' => url($to_portRaitUri),
                                'transfer_user_name' => $to_username,
                                'integral' => intval(bcadd($user->integral, $integral)),
                                'member_id' => $member['member_id'],
                                'member_name' => $member['member_name'],
                            ));

                        }else{

                            DB::rollBack();
                            Redis::del($redis_key);
                            return response_json(403, trans('web.transferFail'), array('error' => 4));

                        }
                    }else{

                        DB::rollBack();
                        Redis::del($redis_key);
                        return response_json(403, trans('web.transferFail'), array('error' => 5));

                    }
                }

            } catch (\Exception $exception) {

                DB::rollBack();
                Redis::del($redis_key);
                $input = $request->all();
                Log::useFiles(storage_path('memberWalletTransfer.log'));
                Log::info('user_id:'.$uid.', input:'.json_encode($input, JSON_UNESCAPED_UNICODE).', order_id'.$order_id.',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

            }
        }else{

            return response_json(402,trans('web.accessFrequent'));

        }


    }


    // 数字货币提现
    public function _digitalCurrencyWithdraw(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $current_id = $request->input('current_id');
        $min_amount =  UsersWallet::get_min_amount($current_id);
        $validator = Validator::make($request->all(), [
            'current_id' => 'required',
            'to_address' => 'required|string',
            'amount' => 'required|numeric|min:'.$min_amount,
            //'pin' => 'required|string',
            'code' => 'required|string|min:6|max:6',
            'form_token' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $form_token = $request->input('form_token');
        $form_token_key = 'form_token_wallet_withdraw'.$uid;
        if (Redis::get($form_token_key) === $form_token) {
            $order_sn = Order::getOrderSnByFormToken($uid, $form_token);
            if($order_sn){
                return response_json(402, trans('web.orderHasBeenSubmit').$order_sn);
            }else{
                return response_json(402, trans('web.doNotSubmitAgain'));
            }
        }else{
            // 缓存个10分钟
            Redis::setex($form_token_key, 600, $form_token);
        }

        $decimals = UsersWallet::get_decimals($current_id);
        $amount = bcadd($request->input('amount'), 0, $decimals);
        $to_address = $request->input('to_address');
        $code = $request->input('code');
        //$pin = sha1($request->input('pin'));

        $redis_key = 'member_wallet_pay'.$uid;

        if(Redis::command('set', [$redis_key, true, 'NX', 'EX', 10])) {

            try{

                // 验证邮件验证码
                $record = MailCode::where('email', $user->email)
                    ->where('expire_time', '>', date('Y-m-d H:i:s'))
                    ->where('user_id', $uid)
                    ->where('type', MailCode::CASH_WITHDRAWAL)
                    ->where('status', MailCode::STATUS_UNVERIFY)
                    ->where('verify_status', 0)
                    ->orderBy('expire_time', 'desc')
                    ->first();
                if (!$record) {
                    return response_json(402, trans('web.youdonotsendtheemail'));
                }elseif ($record->code != $code){
                    return response_json(402, trans('web.codeUndefined'));
                }

                // 已使用
                $record->status = 1;
                $record->save();

                // 开启事务
                DB::beginTransaction();

                $wallet_model = new UsersWallet($current_id);
                $wallet = $wallet_model->get_users_wallet($uid, $current_id);
                if(empty($wallet)){
                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json(403, trans('web.noSuchWallet'));
                }

                if($to_address == $wallet->address){
                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json(402, trans('web.unableToWithdrawToYourself'));
                }

                // 判断余额够不够
                $fee = Currency::getTransferFee($current_id, $amount, $wallet->fee);
                // 转账金额 + 费率(预算手续费) > 用户余额
                if(bcsub(bcadd($amount, $fee, $decimals), $wallet->usable_balance, $decimals) > 0){
                    DB::rollBack();
                    Redis::del($redis_key);
                    // 最大实际可转余额
                    $not_enough_str = Currency::getBalanceNotEnoughStr($wallet->fee, $wallet->unit, 'web');
                    return response_json(403, $not_enough_str);
                }elseif (empty($wallet->can_withdraw)){ // 判断能否提现
                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json(403, trans('web.suchCurrencyDoNotWithdraw'));
                }

                // 验证地址
                if (!$wallet_model->validate_address($to_address)) {
                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json(403, trans('web.addressNotFound'));
                }

                $now_time = date('Y-m-d');
                $order_sn = create_order_sn('WD');
                // 提现 加 手续费 总计
                $consume_amount = bcadd($amount, $fee, $decimals);
                $after_balance = bcsub($wallet->usable_balance, $consume_amount, $decimals);
                // send
                $order = array(
                    'order_sn' => $order_sn,
                    'uid' => $uid,
                    'wallet_id' => $wallet->id,
                    'current_id' => $current_id,
                    'address' => $to_address,
                    'send_time' => $now_time,
                    'confirm_time' => $now_time,
                    'fee' => $fee,     // 手续费
                    'money' => '0.00',
                    'pay_money' => '0.00',
                    'total_amount' => $amount,
                    'amount' => $amount,   // 订单总价
                    'unit' => $wallet->unit,
                    'category' => 'send',
                    'confirms' => 0,
                    'is_send' => 1,
                    'status' => 802,
                    'is_done' => 1,
                    'integral' => 0,
                    'rechargeType' => 25, // 数字货币提现
                    'send_user_name' => $user->username,
                    'before_balance' => $wallet->usable_balance,
                    'after_balance' => $after_balance,
                    'form_token' => $form_token,
                    'currency_rate' => $wallet->rate
                );

                // 其他货币手续费
                if($current_id == UsersWallet::RPZX || $current_id == UsersWallet::BNB){
                    // 判断用户ETH余额够不够扣手续费
                    // gas limit 2019-03-11 田闯说 eth_fee <= 0.00009
                    $eth_model = new UsersWallet(UsersWallet::ETH);
                    $eth_fee = $eth_model->get_eth_gas_limit($wallet->address, $to_address, $amount);
                    $eth_usable_balance = UsersWallet::where('uid', $uid)->where('current_id', UsersWallet::ETH)->lockForUpdate()->value('usable_balance');
                    if(bcsub($eth_usable_balance, $eth_fee, 8) < 0){
                        DB::rollBack();
                        Redis::del($redis_key);
                        $not_enough_str = Currency::getBalanceNotEnoughStr($eth_fee, 'ETH', 'web');
                        return response_json(403, $not_enough_str);
                    }
                    $order['fee'] = 0;
                }elseif ($current_id == UsersWallet::USDT){
                    $btc_usable_balance = UsersWallet::where('uid', $uid)->where('current_id', UsersWallet::BTC)->lockForUpdate()->value('usable_balance');
                    $btc_fee = $fee;
                    if(bcsub($btc_usable_balance, $btc_fee, 8) < 0){
                        DB::rollBack();
                        Redis::del($redis_key);
                        $not_enough_str = Currency::getBalanceNotEnoughStr($btc_fee, 'BTC', 'web');
                        return response_json(403, $not_enough_str);
                    }
                    $order['fee'] = 0;
                }

                $order_id = Order::insertGetId($order);

                // 提现订单
                $withdraw_data = array(
                    'uid' => $uid,
                    'order_id' => $order_id,
                    'jyXID' => '',
                    'order_sn' => $order_sn,
                    'current_id' => $current_id,
                    'amount' => $amount,
                    'fee' => $fee,
                    'legal_money' => $amount.' '.$wallet->unit,
                    'to_address' => $to_address,
                    'status' => 0,
                    'created_at' => $now_time,
                    'updated_at' => $now_time,
                );
                $insert = UsersWithdrawAudit::insert($withdraw_data);

                // update
                $wallet_update = UsersWallet::where('uid', $uid)
                    ->where('current_id',$current_id)
                    ->where('used_balance', $wallet->used_balance)
                    ->where('usable_balance', $wallet->usable_balance)
                    ->update([
                        'used_balance' => bcadd($wallet->used_balance, $consume_amount, $decimals),
                        'usable_balance' => bcsub($wallet->usable_balance, $consume_amount, $decimals)
                    ]);

                // 手续费订单是否成功
                $fee_insert = true;
                // 扣除相应的手续费
                if($current_id == UsersWallet::RPZX){
                    $fee_insert = $wallet_model->deductEthFee($user, $order_sn, $to_address, $fee, '');
                }
                elseif ($current_id == UsersWallet::USDT){
                    $fee_insert = $wallet_model->deductBtcFee($user, $order_sn, $to_address, $fee, '');
                }

                if($order_id && $insert && $wallet_update && $fee_insert){

                    DB::commit();
                    Redis::del($redis_key);
                    return response_json(200, trans('web.applyForSuccess'), array(
                        'order_id' => $order_id,
                        'order_sn' => $order_sn
                    ));

                }else{

                    DB::rollBack();
                    Redis::del($redis_key);
                    return response_json(403, trans('web.applyForFail'));

                }

            } catch (\Exception $exception) {

                DB::rollBack();
                Redis::del($redis_key);
                $input = $request->all();
                Log::useFiles(storage_path('memberDigitalCurrencyWithdraw.log'));
                Log::info('user_id:'.$uid.', input:'.json_encode($input, JSON_UNESCAPED_UNICODE).',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

            }

        }else{

            return response_json(402, trans('web.accessFrequent'));

        }

    }


    // 获取提现状态列表，用于下拉框
    public function getOrderStatus(Request $request){

        $status_arr = array(
            array(
                'status' => 0,
                'status_str' => trans('web.audit'),
            ),
            array(
                'status' => 1,
                'status_str' => trans('web.success'),
            ),
            array(
                'status' => 2,
                'status_str' => trans('web.fail'),
            ),
        );

        return response_json(200, trans('web.getDataSuccess'), array(
            'status' => $status_arr
        ));

    }


    // 获取交易记录
    public function getMyOrderList(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $page = intval($request->input('page', 1));
        $status = intval($request->input('status', -1));
        $current_id = $request->input('current_id', '');
        $rechargeType = $request->input('rechargeType', -1);
        $start_time = strtotime($request->input('start_time'));
        $end_time = strtotime($request->input('end_time'));
        $export = intval($request->input('export', 0));

        if (!empty($start_time) && !empty($end_time)) {
            if ($start_time < $end_time) {
                response_json(403, trans('web.startTimeMustBeGreaterThanEndTime'));
            } else if ($start_time == $end_time) {
                $end_time = $end_time + 3600 * 24;
            }
        }

        if($user->language == 'cn'){
            $name_en = 'name_cn as name_en';
        }else{
            $name_en = 'name_en';
        }

        $query = Order::from('order as o')
            ->select("o.id as order_id", "o.current_id", "o.order_sn", "o.uid", "o.amount", "o.rechargeType", "o.send_time", "c.short_en as unit", "c.icon", "o.pay_money", "o.fee", "o.withdraw_status",
                "o.status", "o.jyXID", "o.rechargeType", "o.is_send", "o.remark", $name_en)
            ->join('currency as c', 'o.current_id', '=', 'c.current_id')
            //->leftJoin('users as bu', 'o.b_uid',  '=', 'bu.id')
            ->where('o.uid', '=', $uid)
            ->where('o.is_show', '=', 1);

        if($current_id > 0){
            $query->where('o.current_id', $current_id);
        }

        if($rechargeType > 0){
            $query->where('o.rechargeType', $rechargeType);
        }

        if (in_array($status, ['801', '802', '803'])) {
            $query->where('o.status', $status);
        }

        if(!empty($start_time) && !empty($end_time)){
            $query->where('o.send_time', '>=', date('Y-m-d H:i;s', $start_time))->where('o.send_time', '<', date('Y-m-d H:i;s', $end_time));
        }

        $last_page = 1;
        $total = 0;
        if(empty($export)){

            $data = $query->orderBy('o.send_time', 'desc')
                ->orderBy('o.id', 'desc')
                ->paginate(10)
                ->appends([
                    'current_id' => $current_id,
                    'status' => $status,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'export' => $export,
                    'page' => $page
                ])
                ->toArray();

            $list = $data['data'];
            $last_page = $data['last_page'];
            $total = $data['total'];

        }else{

            $list = $query->orderBy('o.send_time', 'desc')
                ->orderBy('o.id', 'desc')
                ->get()
                ->toArray();

        }


        if(!empty($list)){
            foreach ($list as $key => &$item){
                if($item['rechargeType']!=19 && $item['rechargeType']!=17){
                    $item['icon'] = url($item['icon']);
                }else{
                    $item['icon'] = ($item['current_id']<8000) ? url($item['icon']) : url(Currency::POINTS_ICON);
                }
                $item['type_str'] = Order::getTypeStr($item['rechargeType']);
                $decimals = UsersWallet::get_decimals($item['current_id']);
                $item['amount'] = bcadd($item['amount'], 0, $decimals);
                if($item['rechargeType'] == 17 || ($item['rechargeType'] == 19 && $item['current_id']>8000)){
                    $item['amount'] = $item['pay_money'];
                }
                if($item['is_send'] == 1){
                    $item['amount'] = '-'.$item['amount'];
                }else{
                    $item['amount'] = '+'.$item['amount'];
                }
                // 订单状态
                $transaction_status =  $item['status'];
                if($item['rechargeType'] == 1){
                    $transaction_status = $item['rechargeStatus'];
                }elseif ($item['rechargeType'] == 25){
                    $transaction_status = $item['withdraw_status'];
                }
                $item['transaction'] = Order::getTransaction($item['rechargeType'], $transaction_status, $item['jyXID']);
                $item['currency'] = $item['name_en'].'('.$item['unit'].')';
                $item['email'] = $user->email;
                $item['rechargeType'] = Order::getTypeStr($item['rechargeType']);

                unset($item['name_en'], $item['status'], $item['withdraw_status']);
            }
        }

        if(empty($export)){
            return response_json(200, trans('web.getDataSuccess'),
                array(
                    'list' => $list,
                    'total' => $total,
                    'last_page' => $last_page
                )
            );
        }else{

            // excel 导出
            $file_name = trans('web.tradeInfo');
            $columns_arr = array(
                array('title' => trans('web.id'), 'field' => 'order_id', 'width' => 10),
                array('title' => trans('web.email'), 'field' => 'email', 'width' => 20),
                array('title' => trans('web.date'), 'field' => 'send_time', 'width' => 20),
                array('title' => trans('web.amount'), 'field' => 'amount', 'width' => 15),
                array('title' => trans('web.fee'), 'field' => 'fee', 'width' => 15),
                array('title' => trans('web.currency'), 'field' => 'currency', 'width' => 15),
                array('title' => trans('web.transactionsId'), 'field' => 'jyXID', 'width' => 50),
                array('title' => trans('web.orderNumber'), 'field' => 'order_sn', 'width' => 50),
                array('title' => trans('web.orderType'), 'field' => 'rechargeType', 'width' => 30),
            );

            excel_export($file_name, $list, $columns_arr);
        }

    }




    // 获取订单筛选条件
    public function getOrderFilter(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $order = array();
        for ($i = 0; $i <= 25; $i++){
            if($i != 13){
                $temp = array(
                    'rechargeType' => $i,
                    'rechargeTypeStr' => trans('web.orderType'.$i)
                );
                $order[] = $temp;
            }
        }

        $status = array(
            array(
                'status' => 801,
                'statusStr' => trans('web.tradeFail')
            ),
            array(
                'status' => 802,
                'statusStr' => trans('web.tradeWait')
            ),
            array(
                'status' => 803,
                'statusStr' => trans('web.tradeSuccess')
            ),
        );

        return response_json(200, trans('web.getDataSuccess'), array(
            'status' => $status,
            'order' => $order,
        ));

    }


}