<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 2018/11/8
 * Time: 15:21
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessOrder;
use App\Models\Currency;
use App\Models\Business\Pos;
use App\Models\Order;
use App\Models\Business\PosBuy;
use App\Models\Business\PosNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Business\User;
use App\Models\Bank;
use App\Models\Recommend;
use App\Models\BusinessVipCharge;
use App\Models\BusinessCharge;
use App\Models\BusinessWallet;
use Illuminate\Support\Facades\DB;
use Hash;
use App\Models\Regions;
use App\Libs\Common;
use App\Models\Business\PosWallet;
use App\Models\Business;
use App\Models\UsersWallet;
use App\Jobs\UpdateBalance;
use Monolog\Handler\IFTTTHandler;
use Session;
use Cookie;
use Crypt;
use App\Models\Coupons;
use App\Models\Business\BusinessDayTurnover;
use App\Models\Admin\SystemOperationLog;

/**
 * @group 60管理员后台客户管理
 * - author mengyawei
 * */
class ManageController extends Controller
{
    /**
     * 60.1管理员查询商家列表
     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token|是|string|csrftoken|
    |country |否  |int |国家id   |
    |start_time |否  |date |最早创建时间   |
    |end_time |否  |date |最晚创建时间   |
    |page |是  |int |页码,默认为1   |
    |export|否|string|是否导出，默认为0，不导出|

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Get Data Successful",
    "data": {
    "page": 2,
    "total": 7,
    "path": "http://rapidz.com/api/manage/businessList",
    "prev_page": "http://rapidz.com/api/manage/businessList?page=1",
    "current_page": "http://rapidz.com/api/manage/businessList?page=1",
    "next_page": "http://rapidz.com/api/manage/businessList?page=3",
    "last_page": 3,
    "list": [
    {
    "id": 128,
    "created_at": "2018-10-31 14:01:39",
    "username": "mengyawei",
    "actual_name": "mengyawei",
    "phone_number": "13995124456",
    "email": "904884739@qq.com",
    "country": "中国",
    "btc_balance": "0.00000000",
    "rpz_balance": "0.00000000"
    },
    {
    "id": 131,
    "created_at": "2018-11-05 18:23:13",
    "username": "admin",
    "actual_name": "sssssssssass",
    "phone_number": "15915844503",
    "email": "linlicai1991@163.com",
    "country": "美国",
    "btc_balance": "9994.50000000",
    "rpz_balance": "9995.60000000"
    },
    {
    "id": 134,
    "created_at": "2018-11-12 14:39:34",
    "username": "15915844502",
    "actual_name": "",
    "phone_number": "15915844502",
    "email": "linlicai@163.com",
    "country_id": "美国",
    "btc_balance": "0.00000000",
    "rpz_balance": "0.00000000"
    }
    ]
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |page|int|当前页码|
    |total|int|总记录数|
    |path|string|url地址|
    |prev_page|string|上一页url地址|
    |current_page|string|当前页url地址|
    |next_page|string|下一页url地址|
    |last_page|int|总页码|
    |created_at |date   |加入时间  |
    |username |string   |登录账户  |
    |actual_name |string   | 商家名称 |
    |phone_number |string   |电话号码  |
    |email |string   |邮箱  |
    |country |int   |国家  |
    |btc_balance |string   |btc余额  |
    |rpz_balance |string   |rpz余额  |

     **备注**
     * */
    public function businessList(Request $request){
        $export = 0;
        $list = $this->getList($request,$export);
        return response_json(200,trans('web.getDataSuccess'),$list);
    }

    //商家列表
    public function getList($request,$export)
    {

        $validator = Validator::make($request->all(), [
            'country_id' => 'nullable|integer',
            'specific' => 'nullable|integer',
            'seller' => 'nullable|string',
            'source' => 'nullable|integer',
            'page' => 'nullable|integer',
            'count' => 'integer',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $lang = Auth('admin')->user()->language;
        $country = intval($request->input('country_id'));
        $specific = intval($request->input('specific'));
        $seller = trim($request->input('seller'));
        $start_time = trim($request->input('start_time'));
        $end_time = trim($request->input('end_time'));
        $page = intval($request->input('page', 1));
        $count = intval($request->input('count', 1));
        $source = trim($request->input('source'));

        $list = DB::table('business')
            ->select('id', 'sellerId', 'created_at', 'username', 'nickname', 'phone_number', 'email', 'area', 'country', 'source', 'portRaitUri');
            //->where('status', 1);
        if ($country) {
            $list->where('country', $country);
        }

        //查询是否为特定商家
        if ($specific) {
            $list->where('specific', $specific);
        }
        //查询是平台商家还是普通商家
        if ($source) {
            $list->where('source', $source);
        }

        //根据登录名或商家姓名查询
        if (isset($seller)) {
            //$list->where('username','like',"%$seller%")->orWhere('actual_name','like',"%$seller%");
            $list->where('nickname', 'like', "%$seller%");
        }
        //根据订单时间查询
        //传入相同日期则查询当天数据
        if ($start_time && $end_time && $start_time == $end_time) {
            $end_time = date('Y-m-d', strtotime($end_time) + 60 * 60 * 24);
            $list->where('created_at', '>', $start_time);
            $list->where('created_at', '<', $end_time);
        } else {
            if ($start_time) {
                $list->where('created_at', '>', $start_time);
            }
            if ($end_time) {
                $list->where('created_at', '<', date('Y-m-d H:i:s', strtotime($end_time) + 60 * 60 * 24));
            }
        }
        if (!$export) {
            $paginate = $list->orderBy("sellerId", "desc")->paginate($count)->toArray();
            $list = $paginate['data'];
            $current_page = $paginate['current_page'];
            $first_page = $paginate['first_page_url'];
            $last_page = $paginate['last_page'];
            $next_page = $paginate['next_page_url'];
            $total = $paginate['total'];
            $path = $paginate['path'];
            $prev_page = $paginate['prev_page_url'];
        } else {
            $list = $list->orderBy("sellerId", "desc")->get();
        }
        if (!$export) {
            $data = array(
                'total' => $total,
                'current_page' => $current_page,
                'last_page' => $last_page,
                'path' => $path,
                'prev_page' => $prev_page,
                'first_page' => $first_page,
                'next_page' => $next_page,
                'list' => $list
            );
        } else {
            $data = array(
                'list' => $list,
            );
        }
        $list = json_decode(json_encode($data['list']), true);
        foreach ($list as $k => $v) {
            $list[$k]['area'] = str_replace('+', '', $v['area']);
            //$list[$k]['phone_number'] = '+'.$list[$k]['area'].$list[$k]['phone_number'];
            if ($lang == 'cn') {
                $country = DB::table('regions')->select('country')->where(array('country_id' => $v['country']))->first();
            } elseif ($lang == 'en') {
                $country = DB::table('regions')->select('en_country')->where(array('country_id' => $v['country']))->first();
                $country->country = $country->en_country;
            } else {
                $country = DB::table('regions')->select('country')->where(array('country_id' => $v['country']))->first();
            }

            if ($country) {
                $list[$k]['country'] = $country->country;
            } else {
                $list[$k]['country'] = '';
            }
            $code = Recommend::select('recommend_code')->where(array('recommend_id' => $v['sellerId']))->orderBy('id','desc')->first();
            if ($code) {
                $list[$k]['recommend_code'] = $code->recommend_code;
            } else {
                $list[$k]['recommend_code'] = '';
            }
            //商家btc手续费
            $btc_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1001))->first();
            if ($btc_charge){
                $list[$k]['btc_charge'] = $btc_charge->charge;
            }else{
                $list[$k]['btc_charge'] = '';
            }
            //商家rpz手续费
            $rpz_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1002))->first();
            if ($rpz_charge){
                $list[$k]['rpz_charge'] = $rpz_charge->charge;
            }else{
                $list[$k]['rpz_charge'] = '';
            }
            //商家ETH手续费
            $eth_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1003))->first();
            if ($eth_charge){
                $list[$k]['eth_charge'] = $eth_charge->charge;
            }else{
                $list[$k]['eth_charge'] = '';
            }
            //商家BCH手续费
            $bch_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1006))->first();
            if ($bch_charge){
                $list[$k]['bch_charge'] = $bch_charge->charge;
            }else{
                $list[$k]['bch_charge'] = '';
            }
            //商家BSV手续费
            $bsv_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1008))->first();
            if ($bsv_charge){
                $list[$k]['bsv_charge'] = $bsv_charge->charge;
            }else{
                $list[$k]['bsv_charge'] = '';
            }
            //商家LTC手续费
            $ltc_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1005))->first();
            if ($ltc_charge){
                $list[$k]['ltc_charge'] = $ltc_charge->charge;
            }else{
                $list[$k]['ltc_charge'] = '';
            }
            //商家NEM手续费
            $nem_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1009))->first();
            if ($nem_charge){
                $list[$k]['nem_charge'] = $nem_charge->charge;
            }else{
                $list[$k]['nem_charge'] = '';
            }
            //商家RPZX手续费
            $rpzx_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1010))->first();
            if ($rpzx_charge){
                $list[$k]['rpzx_charge'] = $rpzx_charge->charge;
            }else{
                $list[$k]['rpzx_charge'] = '';
            }

            //商家USDT手续费
            $usdt_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1011))->first();
            if ($usdt_charge){
                $list[$k]['usdt_charge'] = $usdt_charge->charge;
            }else{
                $list[$k]['usdt_charge'] = '';
            }

            //商家BNB手续费
            $bnb_charge = BusinessVipCharge::select('current_id','charge')->where(array('sellerId'=>$v['sellerId'],'current_id'=>1012))->first();
            if ($bnb_charge){
                $list[$k]['bnb_charge'] = $bnb_charge->charge;
            }else{
                $list[$k]['bnb_charge'] = '';
            }
            /*$current = Currency::where('is_virtual', 1)->orderBy('current_id')->get()->toArray();
            foreach ($current as $kk => $vv) {
                $list[$k][strtolower($vv['short_en']) . '_fee'] = BusinessVipCharge::where(array('sellerId' => $v['sellerId'], 'current_id' => $vv['current_id']))->value('charge') ?: '';

                $list[$k][strtolower($vv['short_en']) . '_charge']['charge'] = BusinessVipCharge::where(array('sellerId' => $v['sellerId'], 'current_id' => $vv['current_id']))->value('charge') ?: '';
                $list[$k][strtolower($vv['short_en']).'_charge']['current_id'] = $vv['current_id'];
                $name[] = $vv['short_en'] . ' ' . trans('web.fee');
                $business_name[] = $vv['short_en'] . ' ' . trans('web.address');

            }
            $names = array();
            foreach($name as $kkk=>$vvv){
                $names[$kkk]['label'] = $vvv;
                $prop = strtolower(explode(' ',$vvv)[0]).'_fee';
                $names[$kkk]['prop'] = $prop;
                $names[$kkk]['type'] = 'sort';
            }
            $charge_title = array(
                array('label'=>trans('web.loginAccount'),'prop'=>'username','type'=>'normal'),
                array('label'=>trans('web.shopName'),'prop'=>'nickname','type'=>'normal'),
                array('label'=>trans('web.phoneNumber'),'prop'=>'phone_number','type'=>'normal'),
                array('label'=>trans('web.email'),'prop'=>'email','type'=>'normal'),
            );
            $business_title = array(trans('web.createTime'), trans('web.loginAccount'), trans('web.shopName'), trans('web.phoneNumber'), trans('web.email'), trans('web.country'));
            $business_title = array_merge($business_title, $business_name);
            $business_title = array_merge($business_title, array(trans('web.businessType')));
            $charge_title = array_merge($charge_title, $names);*/
//            $balance = BusinessWallet::select('usable_balance', 'current_id')->where(array('sellerId' => $v['sellerId']))->get();
            $balance = BusinessWallet::select('usable_balance', 'current_id','total_balance')->where(array('sellerId' => $v['sellerId']))->get();
            $balance = json_decode(json_encode($balance), true);
            $temptotalallbalance= 0;
            if ($balance) {
                //if (count($balance) == 6){
                foreach ($balance as $m => $n) {
                    $key = Currency::select('short_en')->where(array('current_id' => $n['current_id']))->first();
                    if ($key) {
                        $key = strtolower($key->short_en);
                        $list[$k][$key . '_balance'] =$n['usable_balance'];
                        $list[$k][$key . '_total_balance'] = $n['total_balance'];
                        if (!empty($n['total_balance'])){

                            $change = Currency::changeCurrency($n['current_id'], 8012, $n['total_balance']);//换成台币
                            if ($change['code'] == 200) {
                                $temptotalallbalance += $change['data']['change_balanced'];//换成台币
                            }
                        }

                    }
                }
            }

            $list[$k]['total_all_balance'] = $temptotalallbalance;
            if (empty($list[$k]['rpz_balance'])){
                $list[$k]['rpz_balance'] = bcadd(0.00000000,0,6);
            }
            if (empty($list[$k]['rpz_total_balance'])){
                $list[$k]['rpz_total_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['btc_balance'])){
                $list[$k]['btc_balance'] = bcadd(0.00000000,0,6);
            }
            if (empty($list[$k]['btc_total_balance'])){
                $list[$k]['btc_total_balance'] = bcadd(0.00000000,0,8);
            }


            if (empty($list[$k]['xem_balance'])){
                $list[$k]['xem_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['xem_total_balance'])){
                $list[$k]['xem_total_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['usdt_balance'])){
                $list[$k]['usdt_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['usdt_total_balance'])){
                $list[$k]['usdt_total_balance'] = bcadd(0.00000000,0,8);
            }

            if (empty($list[$k]['nbn_balance'])){
                $list[$k]['nbn_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['nbn_total_balance'])){
                $list[$k]['nbn_total_balance'] = bcadd(0.00000000,0,8);
            }

            if (empty($list[$k]['bch_balance'])){
                $list[$k]['bch_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['bch_total_balance'])){
                $list[$k]['bch_total_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['ltc_balance'])){
                $list[$k]['ltc_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['ltc_total_balance'])){
                $list[$k]['ltc_total_balance'] = bcadd(0.00000000,0,8);
            }

            if (empty($list[$k]['rpzx_balance'])){
                $list[$k]['rpzx_balance'] = bcadd(0.00000000,0,8);
            }
            if (empty($list[$k]['rpzx_total_balance'])){
                $list[$k]['rpzx_total_balance'] = bcadd(0.00000000,0,8);
            }

            if (empty($list[$k]['eth_balance'])){
                $list[$k]['eth_balance'] = bcadd(0.00000000,0,8);
            }

            if (empty($list[$k]['eth_total_balance'])){
                $list[$k]['eth_total_balance'] = bcadd(0.00000000,0,8);
            }
        }
            $data['list'] = $list;
            //$data['business_title'] = $business_title;
            //$data['charge_title'] = $charge_title;
            return $data;

    }

    /**
     * 60.2管理员下载商家列表
     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token|是|string|csrftoken|
    |country_id |否  |int | 国家id    |
    |start_time     |否  |date |开始时间     |
    |end_time     |否  |date | 结束时间    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Get Data Successful",
    "data": {
    "list": [
    {
    "id": 1,
    "created_at": "2018-10-16 17:39:47",
    "username": "tianchuang",
    "nickname": "asdsa",
    "phone_number": "adsasdasd",
    "email": "adsads",
    "country_id": "美国",
    "btc_balance": "10.00000000",
    "rpz_balance": "10.00000000"
    },
    {
    "id": 2,
    "created_at": "2018-10-24 16:00:22",
    "username": "15915844503",
    "nickname": "dsds",
    "phone_number": "15915844503",
    "email": "15658690@qq.com",
    "country_id": "欧洲",
    "btc_balance": "0.00000000",
    "rpz_balance": "0.00000000"
    },
    {
    "id": 125,
    "created_at": "2018-10-16 17:39:47",
    "username": "tianchuang",
    "nickname": "asdsa",
    "phone_number": "adsasdasd",
    "email": "tianchuang@qq.com",
    "country_id": "英国",
    "btc_balance": "10.00000000",
    "rpz_balance": "10.00000000"
    },
    {
    "id": 128,
    "created_at": "2018-10-31 14:01:39",
    "username": "mengyawei",
    "nickname": "mengyawei",
    "phone_number": "13995124456",
    "email": "904884739@qq.com",
    "country_id": "中国",
    "btc_balance": "0.00000000",
    "rpz_balance": "0.00000000"
    },
    {
    "id": 131,
    "created_at": "2018-11-05 18:23:13",
    "username": "admin",
    "nickname": "sssssssssass",
    "phone_number": "15915844503",
    "email": "linlicai1991@163.com",
    "country_id": "美国",
    "btc_balance": "9994.50000000",
    "rpz_balance": "9995.60000000"
    },
    {
    "id": 134,
    "created_at": "2018-11-12 14:39:34",
    "username": "15915844502",
    "nickname": "",
    "phone_number": "15915844502",
    "email": "linlicai@163.com",
    "country_id": "美国",
    "btc_balance": "0.00000000",
    "rpz_balance": "0.00000000"
    },
    {
    "id": 135,
    "created_at": "2018-11-12 14:39:38",
    "username": "15915844502",
    "nickname": "",
    "phone_number": "15915844502",
    "email": "linlicai@163.com",
    "country_id": "美国",
    "btc_balance": "0.00000000",
    "rpz_balance": "0.00000000"
    }
    ]
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |created_at |datetime   |创建时间  |
    |username |string   |登陆账户  |
    |nickname |string   |商家名称  |
    |phone_number |string   |电话号码  |
    |email |string   |邮箱  |
    |country_id |string   |国家  |
    |btc_balance |string   |btc余额  |
    |rpz_balance |string   |rpz余额  |
     * */
    public function downloadBusiness(Request $request){
        $export = 1;
        $info = $this->getList($request,$export);
        $list = $info['list'];
        foreach($list as $k=>$v){
            $list[$k]['username'] = "\t".$v['username']."\t";
            $list[$k]['nickname'] = "\t".$v['nickname']."\t";
            $list[$k]['phone_number'] = "\t".$v['phone_number']."\t";
            $list[$k]['business_type'] = $this->business_type($v['source']);
        }

        $file_name = trans('web.merchantList');
        $columns_arr = array(
            array('title' => trans('web.createTime'), 'field' => 'created_at', 'width' => 20),
            array('title' => trans('web.loginAccount'), 'field' => 'username', 'width' => 20),
            array('title' => trans('web.merchantsName'), 'field' => 'nickname', 'width' => 20),
            array('title' => trans('web.phoneNumber'), 'field' => 'phone_number', 'width' => 20),
            array('title' => trans('web.email'), 'field' => 'email', 'width' => 20),
            array('title' => trans('web.country'), 'field' => 'country', 'width' => 20),
            array('title' => '一开始到现在总法币余额(新台币)', 'field' => 'total_all_balance', 'width' => 20),
            array('title' => "一开始到现在".trans('web.btcBalance'), 'field' => 'btc_total_balance', 'width' => 20),
            array('title' => "一开始到现在".trans('web.rpzBalance'), 'field' => 'rpz_total_balance', 'width' => 20),
            array('title' => '一开始到现在bch余额', 'field' => 'bch_total_balance', 'width' => 20),
            array('title' => '一开始到现在nem余额', 'field' => 'xem_total_balance', 'width' => 20),
            array('title' => '一开始到现在usdt余额', 'field' => 'usdt_total_balance', 'width' => 20),
            array('title' => '一开始到现在ltc余额', 'field' => 'ltc_total_balance', 'width' => 20),
            array('title' => '一开始到现在rpzx余额', 'field' => 'rpzx_total_balance', 'width' => 20),
            array('title' => '一开始到现在eth余额', 'field' => 'eth_total_balance', 'width' => 20),
//            array('title' => '一开始到现在bnb余额', 'field' => 'nbn_total_balance', 'width' => 20),


            array('title' => trans('web.btcBalance'), 'field' => 'btc_balance', 'width' => 20),
            array('title' => trans('web.rpzBalance'), 'field' => 'rpz_balance', 'width' => 20),

            array('title' => 'bch余额', 'field' => 'bch_balance', 'width' => 20),
            array('title' => 'nem余额', 'field' => 'xem_balance', 'width' => 20),
            array('title' => 'usdt余额', 'field' => 'usdt_balance', 'width' => 20),
            array('title' => 'ltc余额', 'field' => 'ltc_balance', 'width' => 20),
            array('title' => 'rpzx余额', 'field' => 'rpzx_balance', 'width' => 20),
            array('title' => 'eth余额', 'field' => 'eth_balance', 'width' => 20),


            array('title' => trans('web.businessType'), 'field' => 'business_type', 'width' => 20),
        );
        excel_export($file_name, $list, $columns_arr);
    }

    //商家类型
    public function business_type($source){
        switch($source){
            case 1:
                $business_type = trans('web.ordinaryMerchant');
                break;
            case 2:
                $business_type = trans('web.platformMerchant');
                break;
            case 3:
                $business_type = trans('web.interfaceMerchant');
                break;
        }
        return $business_type;
    }

    /**
     * 60.3推荐码列表
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    	|:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token|是|string|csrftoken|
    |seller 	|否  |string |卖家姓名   |
    |start_time |否  |date 	| 开始时间    |
    |end_time   |否  |date 	| 结束时间    |
    |page   	|是  |int 	| 页码    		|
    |pageSize   |是  |int 	| 每页显示的数量    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "成功",
    "data": {
    "total": 6,
    "last_page": 2,
    "list": [
    {
    "created_at": "2018-10-25 14:28:53",
    "seller": "a",
    "recommender": "z",
    "recommend_code": "aaa",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-12 15:12:54",
    "seller": "b",
    "recommender": "a",
    "recommend_code": "zzz",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-25 14:29:38",
    "seller": "c",
    "recommender": "b",
    "recommend_code": "bbb",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-25 14:31:40",
    "seller": "d",
    "recommender": "c",
    "recommend_code": "ccc",
    "status": "未激活"
    },
    {
    "created_at": "2018-10-25 14:30:34",
    "seller": "e",
    "recommender": "d",
    "recommend_code": "ddd",
    "status": "已激活"
    }
    ]
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |created_at 	|date   	|创建时间  |
    |seller 		|string 	|卖家姓名  |
    |recommender	|string   	|推荐人姓名  |
    |recommend_code |string   	|推荐码  |
    |status 		|string   	|状态  |
    |total 		|int   	|总条数  |
    |last_page 		|int   	|最后一页页码  |
     * */
    public function recommendList(Request $request){
        $export = 0;
        $list = $this->recommend($request,$export);
        return response_json(200,trans('web.getDataSuccess'),$list);
    }

    /**
     * 60.4管理员导出推荐码列表excel
     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token|是|string|csrftoken|
    |seller |否  |string |用户名   |
    |start_time |否  |datetime | 开始时间    |
    |end_time     |否  |datetime | 结束时间    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "成功",
    "data": [
    {
    "created_at": "2018-10-25 14:28:53",
    "seller": "a",
    "recommender": "z",
    "recommend_code": "aaa",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-12 15:12:54",
    "seller": "b",
    "recommender": "a",
    "recommend_code": "zzz",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-25 14:29:38",
    "seller": "c",
    "recommender": "b",
    "recommend_code": "bbb",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-25 14:31:40",
    "seller": "d",
    "recommender": "c",
    "recommend_code": "ccc",
    "status": "未激活"
    },
    {
    "created_at": "2018-10-25 14:30:34",
    "seller": "e",
    "recommender": "d",
    "recommend_code": "ddd",
    "status": "已激活"
    },
    {
    "created_at": "2018-10-25 14:30:58",
    "seller": "f",
    "recommender": "e",
    "recommend_code": "eee",
    "status": "已激活"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |created_at |datetime   |创建时间  |
    |seller |string   |卖家名称  |
    |recommender |string   |推荐人名称  |
    |recommend_code |string   |推荐码  |
    |status |string   |状态  |
     * */
    public function downloadRecommend(Request $request){
        $export = 1;
        $info = $this->recommend($request,$export);
        $list = $info['list'];
        $file_name = trans('web.referralCode');
        $columns_arr = array(
            array('title' => trans('web.createTime'), 'field' => 'created_at', 'width' => 20),
            array('title' => trans('web.seller'), 'field' => 'seller', 'width' => 20),
            array('title' => trans('web.recommender'), 'field' => 'recommender', 'width' => 20),
            array('title' => trans('web.referralCode'), 'field' => 'recommend_code', 'width' => 30),
            array('title' => trans('web.status'), 'field' => 'status', 'width' => 20),
        );
        excel_export($file_name, $list, $columns_arr);
    }

    //推荐状态
    public function status($status){
        if ($status){
            $status = trans('web.activated');
        }else{
            $status = trans('web.notActive');
        }
        return $status;
    }

    //推荐码列表信息
    public function recommend($request,$export){
        $seller = trim($request->input('seller'));
        $start_time = trim($request->input('start_time'));
        $end_time = trim($request->input('end_time'));
        $page = intval($request->input('page'));
        $count = intval($request->input('count',3));
        $list = Recommend::select('created_at','seller','recommender','recommend_code','status');
        if (isset($seller)){
            $list->where('seller','like',"%$seller%");
        }
        //传入相同日期则查询当天数据
        if ($start_time &&$end_time &&$start_time == $end_time){
            $end_time = date('Y-m-d',strtotime($end_time)+60*60*24);
            $list->where('created_at','>',$start_time);
            $list->where('created_at','<',$end_time);
        }else{
            if ($start_time){
                $list->where('created_at','>',$start_time);
            }
            if ($end_time){
                $list->where('created_at','<',date('Y-m-d H:i:s',strtotime($end_time)+60*60*24));
            }
        }
        if(!$export){
            $paginate = $list->orderBy('id','desc')->paginate($count)->toArray();
            $list = $paginate['data'];
            $first_page = $paginate['first_page_url'];
            $current_page = $paginate['current_page'];
            $last_page = $paginate['last_page'];
            $next_page = $paginate['next_page_url'];
            $total = $paginate['total'];
            $path = $paginate['path'];
            $prev_page = $paginate['prev_page_url'];
        }else{
            $list = $list->orderBy('id','desc')->get();
        }

        $list = json_decode(json_encode($list),true);
        foreach($list as $k=>$v){
            $list[$k]['status'] = $this->status($v['status']);
        }
        if (!$export){
            $data = array(
                'page'=>$page,
                'total'=>$total,
                'current_page'=>$current_page,
                'last_page'=>$last_page,
                'path'=>$path,
                'prev_page'=>$prev_page,
                'first_page'=>$first_page,
                'next_page'=>$next_page,
                'list'=>$list
            );
        }else{
            $data = array(
                'list'=>$list
            );
        }
        return $data;
    }
    /**
     * 60.5管理员获取商家个人信息
     ***参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |_token |是  |string |登陆后的返回值   |
    |cookie |是  |string |登录成功后的cookie   |
    |business_id|是|int|商家id|

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Get Data Successful",
    "data": {
    "info": {
    "id": 128,
    "username": "mengyawei",
    "actual_name": "myw",
    "phone_number": "13396093830",
    "email": "904884739@qq.com",
    "company_type": "酒吧",
    "address": "深圳",
    "country": 5,
    "btc_self_address": "123456",
    "rpz_self_address": "111111",
    "password": "$2y$10$hTFCnFBlQxXuZ5tgOW2yeeUcwIGyOiygwGKiXXruT.0Ua4.8wv9By",
    "payment_password": "$2y$10$A7rCEevKE46p03HDtGn.H./xs93CNNGm6BuhBxig98RrYUhfLus/K"
    },
    "bank": {
    "bank_name": "建设银行",
    "bank_card_number": "6217002870002436085",
    "bank_subbranch": "光谷支行",
    "fiat_currency": 8005,
    "swift_code": "cn666",
    "cardholder_name": "孟亚伟"
    },
    "recommend": {
    "seller": "",
    "recommender": "zxy",
    "recommend_code": "123456666666666666",
    "status": 1,
    "created_at": "2018-11-08 16:11:18"
    },
    "business_id": 128
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |id |int   |商家id  |
    |username|string|商家名称|
    |actual_name|string|商家姓名|
    |phone_number|string|手机号|
    |email|string|电子邮箱|
    |company_type|string|企业行业类型|
    |address|string|地址|
    |country|int|国家id|
    |btc_self_address|string|btc提现地址|
    |rpz_self_address|string|rpz提现地址|
    |password|string|登录密码|
    |payment_password|string|支付密码|
    |bank_name|string|银行名称|
    |bank_card_number|string|银行卡号|
    |bank_subbranch|string|支行名称|
    |fiat_currency|int|法定货币|
    |swift_code|string|银行国际代码|
    |cardholder_name|string|持卡人姓名|
    |seller|string|卖方|
    |recommender|string|推荐人姓名|
    |recommend_code|string|推荐码|
    |status|int|状态|
    |created_at|string|时间|
    |business_id|int|商家id|
     * */
    public function info(Request $request){
        $sellerId = intval($request->input('business_id'));
        $lang = Auth('admin')->user()->language;
        $validator = Validator::make($request->all(), [
            'business_id'     =>'required|int',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        $info = Business::where(array('sellerId'=>$sellerId))
            ->select('id','nickname','username','actual_name','area','phone_number','email','job','address','country','house_number','longitude','latitude','password','payment_password','portRaitUri','is_test')
            ->first();
        $info['portRaitUri'] = url($info['portRaitUri']);
        $address = BusinessWallet::select('id','current_id','address')->where(array('sellerId'=>$sellerId))->orderBy('current_id')->get();
        $info['password'] = '******';
        if ($info['payment_password']){
            $info['payment_password'] = '******';
        }else{
            $info['payment_password'] = '';
        }
        $draw = array();
        if (!$address->first()){
            $draw['btc'] = '';
            $draw['rpz'] = '';
        }else{
            $address = json_decode(json_encode($address),true);
            foreach($address as $k=>$v){
                $unit = Currency::select('short_en','current_id')->where(array('current_id'=>$v['current_id']))->where('enabled',1)->first();
                if ($unit){
                    $unit = strtolower($unit->short_en);
                    $draw[$k]['address'] = $v['address'];
                    $draw[$k]['id'] = $v['id'];
                    $draw[$k]['unit'] = strtoUpper($unit).' '.ucfirst(trans('web.address'));
                }
            }
        }
        $draw = json_decode(json_encode($draw),true);
        $bank = Bank::where(array('sellerId'=>$sellerId))
            ->select('bank_name','bank_card_number','bank_subbranch','fiat_currency','swift_code','cardholder_name')
            ->first();
        $recommend = Recommend::where(array('recommend_id'=>$sellerId))
            ->select('recommender','recommend_code','status','created_at')
            ->orderBy('id','desc')
            ->first();
        if ($recommend){
            $recommend['status'] = $recommend['status']?trans('web.alreadyUsed'):trans('web.notUsed');
            $recommend['recommender'] = $recommend['recommender'];
        }else{
            $recommend['recommender'] = $info['nickname'];
            $recommend['recommend_id'] = $sellerId;
            $recommend['created_at'] = date('Y-m-d H:i:s',time());
            $recommend['status'] = 0;
            $recommend['recommend_code'] = strtoupper(md5(bin2hex(openssl_random_pseudo_bytes(16))));
            $b_recommend = new Recommend();
            $re = $b_recommend->insert($recommend);
            if (!$re){
                return response_json(403,trans('web.recommendMakeFail'));
            }
        }
        $recommend['seller'] = $recommend['recommender'];

        $data = array(
            'info'=>$info,
            'draw'=>$draw,
            'bank'=>$bank,
            'recommend'=>$recommend,
            'sellerId'=>$sellerId
        );
        return response_json(200,trans('web.getDataSuccess'),$data);
    }
    /**
     * 60.6管理员修改商家个人信息
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登陆成功后返回值   |
    |_token |是  |string |登录成功后的cookie    |
    |business_id|是|int|商家id|
    |username     |是  |string | 商家登录名    |
    |area|是|int|电话区号|
    |phone_number     |是  |string |手机号     |
    |email     |是  |string | 电子邮箱    |
    |address     |是  |string | 地址    |
    |job     |是  |string | 企业行业类型    |
    |country     |是  |int | 国家id    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Edit Success"
    }
    ```
     * */
    public function editInfo(Request $request){
        $admin = Auth('admin')->user();
        $sellerId = intval($request->input('business_id'));
        $seller = User::where('sellerId',$sellerId)->first();
        $validator = Validator::make($request->all(), [
            'nickname'     =>'required|string|max:40',
            'business_id'   =>'required|int',
            'area'          =>'required|int',
            'phone_number' =>'required|string',
            'email'        =>'required|string',
            'job'           =>'required|string',
            'country'       =>'required|integer',
            'house_number' => 'required|string',
            'is_test'       =>'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        $nickname = trim($request->input('nickname'));
        $phone_number = trim($request->input('phone_number'));
        $email = trim($request->input('email'));
        $job = trim($request->input('job'));
        $country = intval($request->input('country'));
        $area = trim($request->input('area'));
        $area = str_replace('+','',$area);
        $house_number = $request->input('house_number');
        $is_test = $request->input('is_test',0);
        //根据国家查询商家法定货币
        $fc_current_id = Regions::where('country_id',$country)->value('current_id');
        //验证手机号
        if(!preg_match('/^\d{5,15}$/', $phone_number)){
            return response_json(402,trans('web.mobileFormatError'));
        }
        //验证邮箱号
        if (!preg_match('/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',$email)){
            return response_json(402,trans('web.emailFormatError'));
        }
        $face_image = $request->file('face_image');
        DB::beginTransaction();

        //修改头像
        if ($face_image){
            $ext = $face_image->getClientOriginalExtension();     // 扩展名
            $size =$face_image->getSize();
            $allow_ext = array('jpeg','gif','png','bmp','jpg');
            //通过后缀判断图片格式是否正确
            if (!in_array($ext,$allow_ext)){
                return response_json(402,trans('web.avatorFormatError'));
            }
            //限制上传图片大小不超过2M
            if ($size >2*1024*1024){
                return response_json(402,trans('web.picturesShouldNotBeLargerThan2M'));
            }
            //$face_image = $face_image->store('business_face_image', 'public');
            $face_image = $face_image->store('storage/business_face_image/'.date('Y-m-d',time()),'ftp');
            //$face_image = 'storage/'.$face_image;
            $face_image = $face_image;
        }

        //更新geo
        //$geo_hash = geo_hash_encode($latitude,$longitude,12);

        $data = array(
            'nickname' =>$nickname,
            'phone_number'=>$phone_number,
            'area'        =>$area,
            'email'       =>$email,
            'job'         =>$job,
            'country'     =>$country,
            'house_number'  =>$house_number,
            'is_test'       =>$is_test,
            'updated_at'  =>date('Y-m-d H:i:s',time()),
            'fc_current_id'=>$fc_current_id
        );
        if ($face_image){
            $data['portRaitUri'] =$face_image;
        }
        $re = Business::where('sellerId',$sellerId)->update($data);

        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.updateFail'),1);
        }

        //查询商家是否创建优惠券
        $coupon = Coupons::where('sellerId',$sellerId)->get();
        if (count($coupon)){
            //修改商家创建的优惠券名称
            $re = Coupons::where('sellerId',$sellerId)->update([
                'name'=>$nickname,
                'updated_at'=>date('Y-m-d H:i:s',time())
            ]);
            if (!$re){
                DB::rollBack();
                return response_json(403,trans('web.updateFail'),2);
            }
        }
        //修改user表商家用户信息
        $user = \App\Models\User::where('sellerId',$sellerId)->first();
        if ($user){
            $user->username = $nickname;
            $user->nickname = $nickname;
            if ($face_image){
                $user->portRaitUri = $face_image;
            }
            if (!$user->save()){
                DB::rollBack();
                return response_json(403,trans('web.updateFail'),3);
            }
        }
        //记录日志，管理员修改商家信息
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'个人信息';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' edit Business '.$seller->nickname.' info';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'个人信息';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        DB::commit();
        return response_json(200,trans('web.updateSuccess'));
    }

    /**
     * 60.7管理员修改商家提现地址
     ***参数：**
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |用户名   |
    |_token |是  |string | 密码    |
    |business_id|是|int|商家id|
    |btc_self_address |是  |string |btc提现地址    |
    |rpz_self_address|是|string|rpz提现地址|

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Edit Success"
    }
    ```
     * */
    public function editDraw(Request $request){
        $admin = Auth('admin')->user();
        $sellerId = intval($request->input('business_id'));
        $seller = User::where('sellerId',$sellerId)->first();
        $validator = Validator::make($request->all(), [
            'business_id'     =>'required|integer',
            'address'         =>'required',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        $ids = $request->input('ids');
        $address = $request->input('address');

        foreach($address as $k=>$v){
            if (preg_match_all("/([\x{4e00}-\x{9fa5}]+)/u", $v, $match)) {
                return response_json(402,'web.theWithdrawalAddressCannotBeChinese');
            }
        }

        $data = array();
        foreach ($ids as $k=>$v){
            $data[$k][$v] = $address[$k];
        }
        //return $data;
        DB::beginTransaction();
        //DB::connection()->enableQueryLog();
        foreach($data as $k=>$v){
            foreach($v as $kk=>$vv){
                $re = BusinessWallet::where('id',$kk)->update(['address'=>$vv]);
                //dd(DB::getQueryLog());
                if (!$re){
                    DB::rollBack();
                    return response_json(403,trans('web.updateFail'));
                }
            }
        }
        //记录日志，管理员修改商家信息
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'提现信息';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' edit Business '.$seller->nickname.' draw info';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'提现信息';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        DB::commit();

        return response_json(200,trans('web.updateSuccess'));
    }

    /**
     * 60.8商家修改绑定银行信息
     * **参数：**
    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功后返回值   |
    |_token |是  |string |登录成功后的cookie   |
    |business_id|是|int|商家id|
    |bank_name     |是  |string | 银行名称    |
    |bank_subbranch     |是  |string | 支行名称    |
    |bank_card_number     |是  |string |银行卡号   |
    |cardholder_name     |是  |string | 持卡人姓名    |
    |swift_code     |是  |string | 银行国际代码    |
    |fiat_currency     |是  |int |法定货币    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Edit Success"
    }
    ```
     * */
    public function editBank(Request $request){
        $sellerId = intval($request->input('business_id'));
        $bank_name = trim($request->input('bank_name'));
        $bank_subbranch = trim($request->input('bank_subbranch'));
        $bank_card_number = trim($request->input('bank_card_number'));
        $cardholder_name = trim($request->input('cardholder_name'));
        $swift_code = trim($request->input('swift_code'));
        $fiat_currency = intval($request->input('fiat_currency'));
        $admin = Auth('admin')->user();
        $seller  = User::where('sellerId',$sellerId)->first();

        $validator = Validator::make($request->all(), [
            'business_id'       =>'nullable|integer',
            'bank_name'         =>'nullable|string',
            'bank_subbranch'    =>'nullable|string',
            'bank_card_number'  =>'nullable|string',
            'cardholder_name'   =>'nullable|string',
            'swift_code'        =>'nullable|string',
            'fiat_currency'     =>'nullable|int'
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }


        $updated = array(
            'bank_name'         =>$bank_name,
            'bank_subbranch'    =>$bank_subbranch,
            'bank_card_number'  =>$bank_card_number,
            'cardholder_name'   =>$cardholder_name,
            'swift_code'        =>$swift_code,
            'fiat_currency'     =>$fiat_currency,
            'updated_at'        =>date('Y-m-d H:i:s',time())
        );
        $bank = Bank::select('id')->where(array('sellerId'=>$sellerId))->first();
        if ($bank){
            $re = Bank::where(array('sellerId'=>$sellerId))->update($updated);
        }else{
            $updated['sellerId'] = $sellerId;
            $updated['created_at'] = date('Y-m-d H:i:s',time());
            $re = Bank::insert($updated);
        }

        if (!$re){
            return response_json(403,trans('web.updateFail'));
        }

        //记录日志，管理员修改商家信息
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'绑定银行信息';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' edit Business '.$seller->nickname.' bank info';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'绑定银行信息';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        DB::commit();
        return response_json(200,trans('web.updateSuccess'));
    }


    /**
     * 60.11设置特定商家手续费
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功后的cookie   |
    |_token |是  |string |登录成功返回值    |
    |business_id|是|int|商家id|
    |current_id     |是  |int | 币种    |
    |charge     |是  |float | 手续费,小数     |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Add Success"
    }
    ```
     * */
    public function setPosCharge(Request $request){
        $sellerId = intval($request->input('business_id'));
        $current_id = intval($request->input('current_id'));
        $charge = trim($request->input('charge'));
        $admin = Auth('admin')->user();
        $seller = User::where('sellerId',$sellerId)->first();

        $validator = Validator::make($request->all(), [
            'business_id'      =>'required|int',
            'current_id'     =>'required|string',
            'charge'        =>'required|string',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }

        DB::beginTransaction();
        //查询是否已经设置某种币种手续费
        $vip_charge = BusinessVipCharge::select('id')->where(array('sellerId'=>$sellerId,'current_id'=>$current_id))->first();
        if ($vip_charge){
            $vip_charge->charge = $charge;
            $re = $vip_charge->save();
        }else{
            //设置手续费
            $data = array(
                'current_id'=>$current_id,
                'charge'=>$charge,
                'sellerId'=>$sellerId,
                'created_at'=>date('Y-m-d H:i:s',time()),
                'updated_at'=>date('Y-m-d H:i:s',time()),
            );
            $re = DB::table('business_vip_charge')->insert($data);
        }
        if (!$re){
            DB::rollback();
            return response_json(403,trans('web.addFail'));
        }
        //修改商家为特定商家
        $update = array(
            'specific'=>2,
            'updated_at'=>date('Y-m-d H:i:s',time())
        );
        $re = Business::where(array('sellerId'=>$sellerId))->update($update);
        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.updateFail'));
        }
        DB::commit();
        //记录日志，管理员修改商家信息
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'手续费';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' edit Business '.$seller->nickname.' fees';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'修改商家'.$seller->nickname.'手续费';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        return response_json(200,trans('web.updateSuccess'));
    }

    /**
     * 60.12设置普通商家手续费
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生的cookie   |
    |_token |是  |string |登录成功返回值    |
    |btc_min     |是  |int | btc交易额最小值    |
    |btc_middle     |是  |int |btc交易额中等值   |
    |btc_max     |是  |int | btc交易额最大值    |
    |btc_min_charge     |是  |float | btc最小交易额手续费    |
    |btc_middle_charge     |是  |float | btc中等交易额手续费    |
    |btc_max_charge     |是  |float | btc最大交易额手续费    |
    |rpz_min     |是  |int | rpz交易额最小值    |
    |rpz_middle     |是  |int | rpz交易额中等值    |
    |rpz_max     |是  |int | rpz交易额最大值    |
    |rpz_min_charge     |是  |float | rpz最小交易额手续费    |
    |rpz_middle_charge     |是  |float | rpz中等交易额手续费    |
    |rpz_max_Charge     |是  |float | rpz最大交易额手续费     |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Add Success"
    }
    ```
     * */
    public function setCharge(Request $request){

        $current_id = trim($request->input('current_id'));

        $small = trim($request->input('small'));
        $middle = trim($request->input('middle'));
        $max = trim($request->input('max'));

        $min_small = trim($request->input('min_small'));
        $middle_small = trim($request->input('middle_small'));
        $max_small = trim($request->input('max_small'));

        $min_big = trim($request->input('min_big'));
        $middle_big = trim($request->input('middle_big'));
        $max_big = trim($request->input('max_big'));

        $min_charge = trim($request->input('min_charge'));
        $middle_charge = trim($request->input('middle_charge'));
        $max_charge = trim($request->input('max_charge'));

        $validator = Validator::make($request->all(), [
            'current_id'          =>'required|integer',
            'min_small'           =>'nullable|string',
            'middle_small'        =>'nullable|string',
            'max_small'           =>'nullable|string',
            'min_big'             =>'nullable|string',
            'middle_big'          =>'nullable|string',
            'max_big'             =>'nullable|string',
            'min_charge'          =>'nullable|string',
            'middle_charge'       =>'nullable|string',
            'max_charge'          =>'nullable|string',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        if ($min_charge>=1 || $middle_charge>=1 ||$max_charge>=1){
            return response_json(402,trans('web.theChargeShouldLessThanOne'));
        }
        DB::beginTransaction();
        //最小交易额，最大交易额和手续费三者必须同时填写
        //设置第一行手续费
        if ($min_small || $min_big || $min_charge){
            if ( !strlen($min_small) || !$min_big || !$min_charge){
                return response_json(402,trans('web.feesAndTransactionCanNotBeNull'),1);
            }else{
                //已经设置则清空数据，重新添加
                if ($small){
                    $re = BusinessCharge::where('id',$small)->where('current_id',$current_id)->delete();
                    if (!$re){
                        DB::rollBack();
                        return response_json(403,trans('web.updateFail'),2);
                    }
                }
                //重新设置手续费
                $data_min = array(
                    'min_revenue'=>$min_small,
                    'max_revenue'=>$min_big,
                    'charge'=>$min_charge,
                    'current_id'=>$current_id,
                    'created_at'=>date('Y-m-d H:i:s',time())
                );
                $re = BusinessCharge::insert($data_min);
                if (!$re){
                    DB::rollBack();
                    return response_json(403,trans('web.updateFail'),3);
                }
            }
        }
        //设置第二行手续费
        if ($middle_small || $middle_big || $middle_charge){
            if (!strlen($middle_small) || !$middle_big || !$middle_charge){
                return response_json(402,trans('web.feesAndTransactionCanNotBeNull'));
            }else{
                //已经设置则清空数据，重新添加
                if ($middle){
                    $re = BusinessCharge::where('id',$middle)->where('current_id',$current_id)->delete();
                    if (!$re){
                        DB::rollBack();
                        return response_json(403,trans('web.updateFail'),4);
                    }
                }
                $data_middle = array(
                    'min_revenue'=>$middle_small,
                    'max_revenue'=>$middle_big,
                    'charge'=>$middle_charge,
                    'current_id'=>$current_id,
                    'created_at'=>date('Y-m-d H:i:s',time())
                );
                $re = BusinessCharge::insert($data_middle);
                if (!$re){
                    DB::rollBack();
                    return response_json(403,trans('web.updateFail'),5);
                }
            }
        }

        //设置第三行手续费
        if ($max_small || $max_big || $max_charge){
            if (!strlen($max_small) || !$max_big || !$max_charge){
                return response_json(402,trans('web.feesAndTransactionCanNotBeNull'));
            }else{
                if ($max){
                    $re = BusinessCharge::where('id',$max)->where('current_id',$current_id)->delete();
                    if (!$re){
                        DB::rollBack();
                        return response_json(403,trans('web.updateFail'),6);
                    }
                }
                $data_max = array(
                    'min_revenue'=>$max_small,
                    'max_revenue'=>$max_big,
                    'charge'=>$max_charge,
                    'current_id'=>$current_id,
                    'created_at'=>date('Y-m-d H:i:s',time())
                );
                $re = BusinessCharge::insert($data_max);
                if (!$re){
                    DB::rollBack();
                    return response_json(403,trans('web.addFail'));
                }
            }
        }
        DB::commit();
        return response_json(200,trans('web.addSuccess'));
    }

    /**
     * 60.13获取商家所有pos机列表
     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string | 登录成功返回值    |
    |business_id     |是  |int | 商家id    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": {
    "list": [
    {
    "id": 58,
    "pos_id": "105250684697863284",
    "nickname": "米兰咖啡_23",
    "created_at": "2018-05-03 14:15:44",
    "address": "",
    "status": 1,
    "deposit": "300 RPZ",
    "pos_wallet": [
    {
    "sellerId": 128,
    "usable_balance": "0.00000000",
    "current_id": 1001,
    "unit": "BTC"
    },
    {
    "sellerId": 128,
    "usable_balance": "80.77527600",
    "current_id": 1002,
    "unit": "RPZ"
    }
    ],
    "turnover": [
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-02-27",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-02-28",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-01",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-02",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-03",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-04",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-05",
    "amount": "0.00000000"
    }
    ]
    ]
    },
    {
    "id": 38,
    "pos_id": "034797213478205008",
    "nickname": "米兰咖啡_3",
    "created_at": "2018-05-03 11:20:28",
    "address": "",
    "status": 1,
    "deposit": "300 RPZ",
    "pos_wallet": [
    {
    "sellerId": 128,
    "usable_balance": "0.00000000",
    "current_id": 1001,
    "unit": "BTC"
    },
    {
    "sellerId": 128,
    "usable_balance": "0.00000000",
    "current_id": 1002,
    "unit": "RPZ"
    }
    ],
    "turnover": [
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-02-27",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-02-27",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-02-28",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-02-28",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-01",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-01",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-02",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-02",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-03",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-03",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-04",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-04",
    "amount": "0.00000000"
    }
    ],
    [
    {
    "current_id": 1001,
    "unit": "BTC",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "date": "2019-03-05",
    "amount": "0.00000000"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "date": "2019-03-05",
    "amount": "0.00000000"
    }
    ]
    ]
    }
    ],
    "currency": [
    {
    "current_id": 1001,
    "unit": "BTC",
    "color": "#E88D38"
    },
    {
    "current_id": 1002,
    "unit": "RPZ",
    "color": "#B63C38"
    },
    {
    "current_id": 1005,
    "unit": "LTC",
    "color": "#494949"
    },
    {
    "current_id": 1003,
    "unit": "ETH",
    "color": "#454A75"
    },
    {
    "current_id": 1006,
    "unit": "BCH",
    "color": "#FF8E00"
    },
    {
    "current_id": 1008,
    "unit": "BSV",
    "color": "#EAB300"
    }
    ]
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |id |int   |POS机自增id  |
    |pos_id|string|pos机id|
    |nickname|string|pos机昵称|
    |created_at|date|pos机创建时间|
    |status|int|pos机状态，1为已绑定，0为未绑定|
    |deposit|string|pos机押金|
    |pos_wallet|float|pos机余额|
    |turnover|array|营业额|
    |currency|array|货币信息|

     * */
    public function getPos(Request $request){
        $sellerId = intval($request->input('business_id'));

        $validator = Validator::make($request->all(), [
            'business_id'     =>'required|integer',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        //查询是主商家还是分店
        $subsuer = Business::where('sellerId',$sellerId)->value('parent_id');
        if ($subsuer){
            //分店查询改分店的pos机
            $pos_list = Pos::from('pos as p')
                ->select("p.id", "p.pos_id", "p.nickname", "p.created_at", "p.address","p.status")
                ->join('business as b', 'p.sellerId', '=', 'b.sellerId')
                ->where('p.sellerId', $sellerId)
                ->orderBy('p.id', 'desc')
                ->get()
                ->toArray();
        }else{
            //主商家查询主商家所有的pos机，包含子商家创建的pos机
            $sellerIds = User::where('sellerId',$sellerId)->orWhere('parent_id',$sellerId)->get()->toArray();
            $ids = array();
            foreach($sellerIds as $value){
                $ids[] = $value['sellerId'];
            }
            $pos_list = Pos::from('pos as p')
                ->select("p.id", "p.pos_id", "p.nickname", "p.created_at", "p.address","p.status")
                ->join('business as b', 'p.sellerId', '=', 'b.sellerId')
                ->whereIn('p.sellerid', $ids)
                ->orWhere('p.main_sellerid',$sellerId)
                ->orderBy('p.id', 'desc')
                ->get()
                ->toArray();
        }

        $seller = User::where('sellerId',$sellerId)->first();
        if(!empty($pos_list)){
            $users_model = new UsersWallet();
            foreach ($pos_list as &$p_item){
                if($p_item['status']){
                    $users_model->add_pos_address($p_item['pos_id'], $sellerId, $seller->userId);
                }
                $p_item['address'] = Pos::where('sellerId', $sellerId)->where('pos_id', $p_item['pos_id'])->value('address') ? : '';
            }
        }

        $last_7_days_arr = last_7_days();
        $last_7_day = $last_7_days_arr['day'];

        $list = array();
        $currency_arr = Currency::getAllCurrency();
        if(!empty($pos_list)){

            $pos_wallet_model = new PosWallet();
            // turnover
            foreach ($pos_list as $pos_key => &$pos_item) {
                $pos_item['deposit'] = '';
                $pos_item['pos_wallet'] = array();
                $pos_item['status_str'] = ($pos_item['status']==1) ? trans('web.bound') : trans('web.unbound');
                $turnover_arr = array();
                if (!empty($pos_item['pos_id']) && $pos_item['status'] == 1) {
                    $pos_item['pos_wallet'] = $pos_wallet_model->getPosWallet($sellerId, $pos_item['pos_id']);
                    $turnover_list = BusinessDayTurnover::select("sellerId", "pos_id", "amount", "ymd", "current_id")
                        //->where('sellerId', $sellerId)
                        ->where('pos_id', $pos_item['pos_id'])
                        ->whereIn('ymd', $last_7_day)
                        ->orderBy('current_id', 'asc')
                        ->orderBy('day_timestamp', 'asc')
                        ->get()
                        ->toArray();
                    if(!empty($turnover_list)){
                        foreach ($currency_arr as $currency) {
                            // 更新下sql余额
                            UpdateBalance::dispatch(['id'=> $sellerId, 'current_id'=> $currency['current_id'], 'seller' => 'seller', 'pos_id'=>$pos_item['pos_id']])->onQueue('update_balance');
                            $turnover = array();
                            foreach ($last_7_day as $ymd_key => $ymd) {
                                $turnover_tmp = array(
                                    'current_id' => $currency['current_id'],
                                    'unit' => $currency['unit'],
                                    'date' => $ymd,
                                    'amount' => '0.00000000'
                                );
                                if($ymd_key < 6){
                                    foreach ($turnover_list as $turnover_item) {
                                        if (($turnover_item['ymd'] == $ymd) && ($currency['current_id'] == $turnover_item['current_id'])) {
                                            $turnover_tmp['amount'] = $turnover_item['amount'];
                                        }
                                    }
                                }else{
                                    $where = [
                                        ['seller_id', '=', $sellerId],
                                        ['current_id', '=', $currency['current_id']],
                                        ['pos_id', '=', $pos_item['pos_id']],
                                        ['status', '=', 803],
                                        ['is_send', '=', 2],
                                        ['updated_at', '>=', date('Y-m-d 00:00:00')],
                                        ['updated_at', '<', date('Y-m-d H:i:s')],
                                    ];
                                    $today_amount = BusinessOrder::where($where)->sum('total');
                                    $turnover_tmp['amount'] = bcadd($today_amount, 0, 8);
                                }
                                $turnover[] = $turnover_tmp;
                            }
                            $turnover_arr[] = $turnover;
                        }
                    }else{
                        foreach ($currency_arr as $currency) {
                            $turnover = array();
                            foreach ($last_7_day as $ymd_key => $ymd) {
                                $turnover_tmp = array(
                                    'current_id' => $currency['current_id'],
                                    'unit' => $currency['unit'],
                                    'date' => $ymd,
                                    'amount' => '0.00000000'
                                );
                                if($ymd_key == 6){
                                    $where = [
                                        ['seller_id', '=', $sellerId],
                                        ['current_id', '=', $currency['current_id']],
                                        ['pos_id', '=', $pos_item['pos_id']],
                                        ['status', '=', 803],
                                        ['is_send', '=', 2],
                                        ['updated_at', '>=', date('Y-m-d 00:00:00')],
                                        ['updated_at', '<', date('Y-m-d H:i:s')],
                                    ];
                                    $today_amount = BusinessOrder::where($where)->sum('total');
                                    $turnover_tmp['amount'] = bcadd($today_amount, 0, 8);
                                }
                                $turnover[] = $turnover_tmp;
                            }
                            $turnover_arr[] = $turnover;
                        }
                    }
                }else{
                    foreach ($currency_arr as $currency) {
                        $turnover = array();
                        foreach ($last_7_day as $ymd_key => $ymd) {
                            $turnover_tmp = array(
                                'current_id' => $currency['current_id'],
                                'unit' => $currency['unit'],
                                'date' => $ymd,
                                'amount' => '0.00000000'
                            );
                            if($ymd_key == 6){
                                $where = [
                                    ['seller_id', '=', $sellerId],
                                    ['current_id', '=', $currency['current_id']],
                                    ['pos_id', '=', $pos_item['pos_id']],
                                    ['status', '=', 803],
                                    ['is_send', '=', 2],
                                    ['updated_at', '>=', date('Y-m-d 00:00:00')],
                                    ['updated_at', '<', date('Y-m-d H:i:s')],
                                ];
                                $today_amount = BusinessOrder::where($where)->sum('total');
                                $turnover_tmp['amount'] = bcadd($today_amount, 0, 8);
                            }
                            $turnover[] = $turnover_tmp;
                        }
                        $turnover_arr[] = $turnover;
                    }
                }
                $pos_item['turnover'] = $turnover_arr;
                $pos_item['pos_wallet'] = $pos_item['pos_wallet'] ? $pos_item['pos_wallet'] : $pos_wallet_model->getVirtualWallet($sellerId, $pos_item['pos_id'], $currency_arr);
            }
            $list['list'] = $pos_list;
            $list['currency'] = $currency_arr;
            $list['date'] = $last_7_day;
        }else{
            $list['list'] = [];
            $list['currency'] = $currency_arr;
            $list['date'] = $last_7_day;
        }
        return response_json(200, trans('app.getDataSuccess'), $list);
    }

    /**
     * 60.14获取pos机具体信息
     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |用户名   |
    |_token |是  |string | 密码    |
    |business_id     |是  |int | 商家id    |
    |pos_id|是|string|pos机id|

     **返回示例**

    ```
      {
        "code": 200,
        "msg": "获取数据成功",
        "data": {
            "pos": {
                "id": 39,
                "nickname": "米兰咖啡_4",
                "username": "米兰咖啡_4",
                "pos_id": "105250684697863284",
                "country_id": 1,
                "address": "",
                "email": "",
                "tel": ""
            }
        }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |id |int   |pos机自增id  |
    |nickname|string|pos机昵称|
    |username|string|pos机登录名称|
    |pos_id|string|pos机id|
    |country_id|int|国家id|
    |address|string|地理位置|
    |email|string|电子邮箱|
    |tel|string|手机号码|

     * */
    public function getPosDetails(Request $request){
        $sellerId = intval($request->input('business_id'));
        $id = intval($request->input('id'));
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $pos = DB::table('pos')->select("id", "nickname", "username", "pos_id", "country_id", "address", "email", "tel","password","area","house_number")
            //->where('sellerId', $sellerId)
            ->where('id', $id)->first();
        if(!empty($pos)){
            return response_json(200, trans('web.getDataSuccess'), array(
                'pos' => $pos
            ));
        }else{
            response_json(403, trans('web.getDataFail'));
        }

    }

    /**
     * 60.15修改pos机信息
     * **参数：** 

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功后的cookie   |
    |_token |是  |string | 管理员登录成功返回值    |
    |id|是|int|pos机自增id|
    |nickname     |是  |string | pos机昵称    |
    |username|是|string|pos机登录账户|
    |password|是|string|pos机登录密码|
    |country_id|是|int|国家id|
    |address|是|string|地址|
    |email|是|string|邮箱|
    |tel|是|string|手机号|

     **返回示例**

    ```
      {
        "code": 200,
        "msg": "编辑成功"
    }
    ```
     * */
    public function editPos(Request $request){
        $sellerId = intval($request->input('business_id'));
        $admin = Auth('admin')->user();
        $validator = Validator::make($request->all(), [
            'nickname' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
            'country_id' => 'required|string',
            'address' => 'required|string|max:255',
            'house_number'=>'required|string',
            'area'=>    'required|string',
            'email' => 'required|string|max:50',
            'tel' => 'required|string|max:16',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $id = intval($request->input('id'));
        $nickname = trim($request->input('nickname'));
        $username = trim($request->input('username'));
        $password = trim($request->input('password'));
        $country_id = intval($request->input('country_id'));
        $address = trim($request->input('address'));
        $email = trim($request->input('email'));
        $tel = trim($request->input('tel'));
        $house_number = trim($request->input('house_number'));
        $area = trim($request->input('area'));
        $area = str_replace('+','',$area);
        // 电子邮件格式
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
            return response_json(403, trans('web.emailFormatError'));
        }

        $time = time();
        $data = array(
            'nickname' => $nickname,
            'username' => $username,
            'password' => bcrypt($password),
            'btc_address' => '',
            'rpz_address' => '',
            'updated_at' => date('Y-m-d H:i:s', $time),
            'country_id' => $country_id,
            'address' => $address,
            'email' => $email,
            'tel' => $tel,
            'area'=>$area,
            'house_number'=>$house_number
        );
        DB::beginTransaction();
        $pos = Pos::where('id', $id)->first();
        //修改pos机信息
        if(!$pos){
            return response_json(403, trans('web.posUndefined'));
        }
        $update = Pos::where('id', $id)->update($data);
        if(!$update){
            DB::rollBack();
            return response_json(403, trans('web.updateFail'));
        }
        //修改商家信息
        $business = Business::where('sellerId',$pos->sellerId)->first();
        $business->nickname = $nickname;
        $business->password = bcrypt($password);
        $business->email = $email;
        $business->area = $area;
        $business->phone_number = $tel;
        if (!$business->save()){
            DB::rollBack();
            return response_json(403, trans('web.updateFail'));
        }
        DB::commit();
        //记录日志，管理员修改商家信息
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'修改POS机'.$pos->nickname.'信息';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' edit POS机 '.$pos->nickname.' info';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'修改POS机'.$pos->nickname.'信息';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        return response_json(200, trans('web.updateSuccess'));
    }

    /**
     * 60.16管理员解绑商家pos机
     * **请求方式：**
    - GET

    **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生的cookie   |
    |_token |是  |string |登录成功返回值    |
    |pos_id     |否  |int | pos_id    |

     **返回示例**

    ```
      {
        "code": 200,
        "msg": "恭喜，pos id已成功解绑"
    }
    ```

     **返回参数说明**

     * */
    public function unbind(Request $request){

        $validator = Validator::make($request->all(), [
            'pos_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $pos_id = $request->input('pos_id');
        $pos = Pos::where('pos_id', $pos_id)
            ->first();
        if(empty($pos)){
            return response_json(403, trans('web.posUndefined'));
        }

        // 不可delete， 只能置为 0
        $update = Pos::where('pos_id', $pos_id)
            ->update([
                'sellerId' => 0,
                'status' => 0
            ]);
        if(!$update){
            return response_json(403, trans('web.posUnbindFail'));
        }
        $number = PosNumber::where('PosId',$pos_id)
            ->update([
                'sellerId' => 0,
                'is_used' => 0
            ]);
        if (!$number){
            return response_json(403, trans('web.posUnbindFail'));
        }
        return response_json(200, trans('web.posUnbindSuccess'));
    }

    /**
     * 60.17刷新推荐码
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功cookie   |
    |_token |是  |string |管理员登录成功返回值    |
    |business_id     |是  |int | 商家id    |

     **返回示例**

    ```
      {
        "code": 200,
        "msg": "生成成功，请复制推荐码",
        "data": "D5E6C2CD08B64FD741A2E3EB0E1F4509"
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |data |string   |生成的推荐码  |
     * */
    public function make(Request $request){
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|numeric'
        ]);
        if ($validator->fails()) return ['code'=>400, 'message'=>$validator->errors()->first()];
        try {
            $recommend = new Recommend();
            $recommend->recommend_code = strtoupper(md5(bin2hex(openssl_random_pseudo_bytes(16))));
            $recommend->recommender = Business::where('sellerid', $request->input('business_id'))->first(['nickname'])->nickname;
            $recommend->recommend_id = $request->input('business_id');
            $recommend->status = Recommend::STATUS_UNUSED;
            $recommend->save();
            return response_json(200,trans('web.recommendMakeSuccess'),$recommend->recommend_code);
        } catch (\Exception $exception) {
            return response_json(403,trans('web.recommendMakeFail'));
        }
    }

    /**
     * 60.19APP已开通的服务国家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生cookie   |
    |_token |是  |string |登录成功返回值    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "country_id": 1,
    "country": "中国",
    "en_country": "China",
    "tw_country": "中國"
    },
    {
    "country_id": 2,
    "country": "香港",
    "en_country": "Hongkong",
    "tw_country": "香港"
    },
    {
    "country_id": 3,
    "country": "澳门",
    "en_country": "Macao",
    "tw_country": "澳門"
    },
    {
    "country_id": 4,
    "country": "台湾",
    "en_country": "Taiwan",
    "tw_country": "臺灣"
    },
    {
    "country_id": 5,
    "country": "马来西亚",
    "en_country": "Malaysia",
    "tw_country": "馬來西亞"
    },
    {
    "country_id": 6,
    "country": "印度尼西亚",
    "en_country": "Indonesia",
    "tw_country": "印度尼西亞"
    },
    {
    "country_id": 7,
    "country": "菲律宾",
    "en_country": "Philippines",
    "tw_country": "菲律賓"
    },
    {
    "country_id": 8,
    "country": "新加坡",
    "en_country": "Singapore",
    "tw_country": "新加坡"
    },
    {
    "country_id": 9,
    "country": "泰国",
    "en_country": "Thailand",
    "tw_country": "泰國"
    },
    {
    "country_id": 10,
    "country": "日本",
    "en_country": "Japan",
    "tw_country": "日本"
    },
    {
    "country_id": 11,
    "country": "韩国",
    "en_country": "South Korea",
    "tw_country": "韓國"
    },
    {
    "country_id": 12,
    "country": "塔吉克斯坦",
    "en_country": "Tajikistan",
    "tw_country": "塔吉克斯坦"
    },
    {
    "country_id": 13,
    "country": "哈萨克斯坦",
    "en_country": "Kazakhstan",
    "tw_country": "哈薩克斯坦"
    },
    {
    "country_id": 14,
    "country": "越南",
    "en_country": "Vietnam",
    "tw_country": "越南"
    },
    {
    "country_id": 15,
    "country": "土耳其",
    "en_country": "Turkey",
    "tw_country": "土耳其"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |country_id |int   |国家id  |
    |country|string|国家简体中文名称|
    |en_country|string|国家英文名称|
    |tw_country|string|国家繁体中文名称|
     * */
    public function isOpen(){
        $lang = Auth('admin')->user()->language;

        if ($lang == 'cn'){
            $data = Regions::select('country_id','country')->where(array('is_open'=>1))->get()->toArray();
        }elseif($lang == 'en'){
            $data = Regions::select('country_id','en_country')->where(array('is_open'=>1))->get()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['country'] = $v['en_country'];
            }
        }else{
            $data = Regions::select('country_id','country')->where(array('is_open'=>1))->get()->toArray();
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.20APP可开通的的服务国家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生cookie   |
    |_token |是  |string |登录成功返回值    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "country_id": 16,
    "country": "印度",
    "en_country": "India",
    "tw_country": "印度"
    },
    {
    "country_id": 17,
    "country": "巴基斯坦",
    "en_country": "Pakistan",
    "tw_country": "巴基斯坦"
    },
    {
    "country_id": 18,
    "country": "阿富汗",
    "en_country": "Afghanistan",
    "tw_country": "阿富汗"
    },
    {
    "country_id": 19,
    "country": "斯里兰卡",
    "en_country": "Sri Lanka",
    "tw_country": "斯裏蘭卡"
    },
    {
    "country_id": 20,
    "country": "缅甸",
    "en_country": "Burma",
    "tw_country": "緬甸"
    },
    {
    "country_id": 21,
    "country": "伊朗",
    "en_country": "Iran",
    "tw_country": "伊朗"
    },
    {
    "country_id": 22,
    "country": "亚美尼亚",
    "en_country": "Armenia",
    "tw_country": "亞美尼亞"
    },
    {
    "country_id": 23,
    "country": "东帝汶",
    "en_country": "East Timor",
    "tw_country": "東帝汶"
    },
    {
    "country_id": 24,
    "country": "文莱",
    "en_country": "Brunei",
    "tw_country": "文萊"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |country_id |int   |国家id  |
    |country|string|国家简体中文名称|
    |en_country|string|国家英文名称|
    |tw_country|string|国家繁体中文名称|
     * */
    public function canOpen(){
        $lang = Auth('admin')->user()->language;
        if ($lang == 'cn'){
            $data = Regions::select('country_id','country')->where(array('is_open'=>0))->get()->toArray();
        }elseif($lang == 'en'){
            $data = Regions::select('country_id','en_country')->where(array('is_open'=>0))->get()->toArray();
            foreach($data as $k=>$v){
                $data[$k]['country'] = $v['en_country'];
            }
        }else{
            $data = Regions::select('country_id','country','en_country','tw_country')->where(array('is_open'=>0))->get()->toArray();
        }

        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.21删除App服务国家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生cookie   |
    |_token |是  |string |登录成功返回值    |
    |country_ids     |是  |array |国家id数组    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "删除数据成功"
    }
    ```
     * */
    public function del(Request $request){
        $validator = Validator::make($request->all(), [
            'country_ids' => 'required'
        ]);
        if ($validator->fails()) return ['code'=>400, 'message'=>$validator->errors()->first()];
        $country_ids = $request->input('country_ids');
        $data = array(
            'is_open'=>0,
            'updated_at'=>date('Y-m-d H:i:s',time())
        );
        foreach($country_ids as $k=>$v){
            $re = Regions::where(array('country_id'=>$v))->update($data);
        }
        if (!$re){
            return response_json(403,trans('web.deleteFail'));
        }
        $admin = Auth('admin')->user();
        //记录日志，管理员修改APP已开通服务国家
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'删除APP开通服务国家';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' del service country';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'删除APP开通服务国家';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);

        return response_json(200,trans('web.deleteSuccess'));
    }

    /**
     * 60.22添加App服务国家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生cookie   |
    |_token |是  |string |登录成功返回值    |
    |country_ids[]     |是  |array | 国家id数组    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "更新数据成功"
    }
    ```
     * */
    public function add(Request $request){
        $validator = Validator::make($request->all(), [
            'country_ids' => 'required'
        ]);
        if ($validator->fails()) return ['code'=>400, 'message'=>$validator->errors()->first()];
        $country_ids = $request->input('country_ids');
        $data = array(
          'is_open'=>1,
          'updated_at'=>date('Y-m-d H:i:s',time())
        );
        foreach($country_ids as $k=>$v){
            $re = Regions::where(array('country_id'=>$v))->update($data);
        }
        if (!$re){
            return response_json(403,trans('web.updateFail'));
        }
        $admin = Auth('admin')->user();
        //记录日志，管理员修改APP已开通服务国家
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'添加APP开通服务国家';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' add service country';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'添加APP开通服务国家';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        return response_json(200,trans('web.updateSuccess'));
    }

    /**
     * 60.23根据洲查询国家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |用户名   |
    |_token |是  |string | 密码    |
    |area     |是  |string | 洲的名称    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "country_id": 1,
    "country": "中国",
    "en_country": "China",
    "tw_country": "中國"
    },
    {
    "country_id": 2,
    "country": "香港",
    "en_country": "Hongkong",
    "tw_country": "香港"
    },
    {
    "country_id": 3,
    "country": "澳门",
    "en_country": "Macao",
    "tw_country": "澳門"
    },
    {
    "country_id": 4,
    "country": "台湾",
    "en_country": "Taiwan",
    "tw_country": "臺灣"
    },
    {
    "country_id": 5,
    "country": "马来西亚",
    "en_country": "Malaysia",
    "tw_country": "馬來西亞"
    },
    {
    "country_id": 6,
    "country": "印度尼西亚",
    "en_country": "Indonesia",
    "tw_country": "印度尼西亞"
    },
    {
    "country_id": 7,
    "country": "菲律宾",
    "en_country": "Philippines",
    "tw_country": "菲律賓"
    },
    {
    "country_id": 8,
    "country": "新加坡",
    "en_country": "Singapore",
    "tw_country": "新加坡"
    },
    {
    "country_id": 9,
    "country": "泰国",
    "en_country": "Thailand",
    "tw_country": "泰國"
    },
    {
    "country_id": 10,
    "country": "日本",
    "en_country": "Japan",
    "tw_country": "日本"
    },
    {
    "country_id": 11,
    "country": "韩国",
    "en_country": "South Korea",
    "tw_country": "韓國"
    },
    {
    "country_id": 12,
    "country": "塔吉克斯坦",
    "en_country": "Tajikistan",
    "tw_country": "塔吉克斯坦"
    },
    {
    "country_id": 13,
    "country": "哈萨克斯坦",
    "en_country": "Kazakhstan",
    "tw_country": "哈薩克斯坦"
    },
    {
    "country_id": 14,
    "country": "越南",
    "en_country": "Vietnam",
    "tw_country": "越南"
    },
    {
    "country_id": 15,
    "country": "土耳其",
    "en_country": "Turkey",
    "tw_country": "土耳其"
    },
    {
    "country_id": 16,
    "country": "印度",
    "en_country": "India",
    "tw_country": "印度"
    },
    {
    "country_id": 17,
    "country": "巴基斯坦",
    "en_country": "Pakistan",
    "tw_country": "巴基斯坦"
    },
    {
    "country_id": 18,
    "country": "阿富汗",
    "en_country": "Afghanistan",
    "tw_country": "阿富汗"
    },
    {
    "country_id": 19,
    "country": "斯里兰卡",
    "en_country": "Sri Lanka",
    "tw_country": "斯裏蘭卡"
    },
    {
    "country_id": 20,
    "country": "缅甸",
    "en_country": "Burma",
    "tw_country": "緬甸"
    },
    {
    "country_id": 21,
    "country": "伊朗",
    "en_country": "Iran",
    "tw_country": "伊朗"
    },
    {
    "country_id": 22,
    "country": "亚美尼亚",
    "en_country": "Armenia",
    "tw_country": "亞美尼亞"
    },
    {
    "country_id": 23,
    "country": "东帝汶",
    "en_country": "East Timor",
    "tw_country": "東帝汶"
    },
    {
    "country_id": 24,
    "country": "文莱",
    "en_country": "Brunei",
    "tw_country": "文萊"
    },
    {
    "country_id": 25,
    "country": "朝鲜",
    "en_country": "DPRK",
    "tw_country": "朝鮮"
    },
    {
    "country_id": 26,
    "country": "柬埔寨",
    "en_country": "Kampuchea",
    "tw_country": "柬埔寨"
    },
    {
    "country_id": 27,
    "country": "老挝",
    "en_country": "Laos",
    "tw_country": "老撾"
    },
    {
    "country_id": 28,
    "country": "孟加拉国",
    "en_country": "Bangladesh",
    "tw_country": "孟加拉國"
    },
    {
    "country_id": 29,
    "country": "马尔代夫",
    "en_country": "Maldives",
    "tw_country": "馬爾代夫"
    },
    {
    "country_id": 30,
    "country": "黎巴嫩",
    "en_country": "Lebanon",
    "tw_country": "黎巴嫩"
    },
    {
    "country_id": 31,
    "country": "约旦",
    "en_country": "Jordan",
    "tw_country": "約旦"
    },
    {
    "country_id": 32,
    "country": "叙利亚",
    "en_country": "Syria",
    "tw_country": "敘利亞"
    },
    {
    "country_id": 33,
    "country": "伊拉克",
    "en_country": "Iraq",
    "tw_country": "伊拉克"
    },
    {
    "country_id": 34,
    "country": "科威特",
    "en_country": "Kuwait",
    "tw_country": "科威特"
    },
    {
    "country_id": 35,
    "country": "沙特阿拉伯",
    "en_country": "Saudi Arabia",
    "tw_country": "沙特阿拉伯"
    },
    {
    "country_id": 36,
    "country": "也门",
    "en_country": "Yemen",
    "tw_country": "也門"
    },
    {
    "country_id": 37,
    "country": "阿曼",
    "en_country": "Oman",
    "tw_country": "阿曼"
    },
    {
    "country_id": 38,
    "country": "巴勒斯坦",
    "en_country": "Palestine",
    "tw_country": "巴勒斯坦"
    },
    {
    "country_id": 39,
    "country": "阿联酋",
    "en_country": "United Arab Emirates",
    "tw_country": "阿聯酋"
    },
    {
    "country_id": 40,
    "country": "以色列",
    "en_country": "Israel",
    "tw_country": "以色列"
    },
    {
    "country_id": 41,
    "country": "巴林",
    "en_country": "Bahrain",
    "tw_country": "巴林"
    },
    {
    "country_id": 42,
    "country": "卡塔尔",
    "en_country": "Qatar",
    "tw_country": "卡塔爾"
    },
    {
    "country_id": 43,
    "country": "不丹",
    "en_country": "Bhutan",
    "tw_country": "不丹"
    },
    {
    "country_id": 44,
    "country": "蒙古",
    "en_country": "Mongolia",
    "tw_country": "蒙古"
    },
    {
    "country_id": 45,
    "country": "尼泊尔",
    "en_country": "Nepal",
    "tw_country": "尼泊爾"
    },
    {
    "country_id": 46,
    "country": "土库曼斯坦",
    "en_country": "Turkmenistan",
    "tw_country": "土庫曼斯坦"
    },
    {
    "country_id": 47,
    "country": "阿塞拜疆",
    "en_country": "Azerbaijan",
    "tw_country": "阿塞拜疆"
    },
    {
    "country_id": 48,
    "country": "乔治亚",
    "en_country": "Georgia",
    "tw_country": "喬治亞"
    },
    {
    "country_id": 49,
    "country": "吉尔吉斯斯坦",
    "en_country": "Kyrgyzstan",
    "tw_country": "吉爾吉斯斯坦"
    },
    {
    "country_id": 50,
    "country": "乌兹别克斯坦",
    "en_country": "Uzbekistan",
    "tw_country": "烏茲別克斯坦"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |country_id |int   |国家id  |
    |country|string|国家简体中文名称|
    |en_country|string|国家英文名称|
    |tw_Country|string|国家繁体中文名称|

     * */
    public function getCountry(Request $request){
        $lang = Auth('admin')->user()->language;
        $area = trim($request->input('area','亚洲'));
        if ($lang == 'cn'){
            $data = Regions::select('country_id','country')
                ->where(array('area'=>$area,'is_open'=>0))
                ->get()
                ->toArray();
        }elseif($lang == 'en'){
            $data = Regions::select('country_id','en_country')
                ->where(array('area'=>$area,'is_open'=>0))
                ->get()
                ->toArray();
            foreach($data as $k=>$v){
                $data[$k]['country'] = $v['en_country'];
            }
        }else{
            $data = Regions::select('country_id','country','en_country','tw_country')
                ->where(array('area'=>$area,'is_open'=>0))
                ->get()
                ->toArray();
        }


        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    public function tree($recommend_id = 0)
    {
        $rows = Recommend::from('business_recommend_code as brc')
            ->join('business as b','brc.sellerId','=','b.sellerId')
            ->where('brc.recommend_id', $recommend_id)
            ->where('brc.status',1)
            //->where('b.is_test',0)
            ->select('brc.id','brc.sellerId','brc.recommend_id','b.username','b.nickname','b.created_at','b.country')
            ->get()
            ->toArray();
        $arr = array();

        if (sizeof($rows) != 0){
            foreach ($rows as $key => $val){
                $val['list'] = $this->tree($val['sellerId']);
                $val['country_name'] = Regions::where('country_id', $val['country'])->first(['en_country'])->en_country;
                if (is_array($val['list'])){
                    $val['count'] = count($val['list']);
                }else{
                    $val['count'] = 0;
                }
                $arr[] = $val;
            }
            return $arr;
        }
    }

    /**
     * 60.24推荐码推荐树
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录成功产生cookie   |
    |_token |是  |string | csrftoken    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "sellerId": 132,
    "recommend_id": 128,
    "username": "华都园大厦",
    "created_at": "2018-11-28 10:44:07",
    "country": "1",
    "list": [
    {
    "sellerId": 126,
    "recommend_id": 132,
    "username": "阳光酒店",
    "created_at": "2018-11-28 10:44:18",
    "country": "1",
    "list": [
    {
    "sellerId": 151,
    "recommend_id": 126,
    "username": "国贸商业大厦",
    "created_at": "2018-11-28 10:44:14",
    "country": "1",
    "list": null,
    "country_name": "中国",
    "count": 0
    },
    {
    "sellerId": 152,
    "recommend_id": 126,
    "username": "国贸",
    "created_at": "2018-11-28 10:44:20",
    "country": "1",
    "list": null,
    "country_name": "中国",
    "count": 0
    }
    ],
    "country_name": "中国",
    "count": 2
    },
    {
    "sellerId": 141,
    "recommend_id": 132,
    "username": "罗湖区人民医院",
    "created_at": "2018-11-28 10:44:12",
    "country": "1",
    "list": null,
    "country_name": "中国",
    "count": 0
    }
    ],
    "country_name": "中国",
    "count": 2
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |sellerId |int   |商家id  |
    |recommend_id|int|推荐人id|
    |username|string|商家登录名|
    |created_at|string|创建时间|
    |country|int|国家id|
    |country_name|string|国家名称|
    |count|int|推荐数|
     * */
    public function recommendTree(Request $request){
        $data = $this->tree();
        return response_json(200,trans('web.getDataSuccess'),$data);

    }

    /**
     * 60.25管理员登录商家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token |是  |string |csrftoken    |
    |sellerId     |是  |int | 商家id    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "登录成功！",
    "data": {
    "access_token": "FH77jg5PQlNYpbZmYIawLUkxcLWO0Gc905kAU6vu",
    "token_type": "bearer",
    "laravelcookie": "eyJpdiI6ImJcL0xVT21hUEwwemxTRVwvK3NRd25adz09IiwidmFsdWUiOiJrUDF1QW96NHowS2JUUGoxemRxTnZ3OFprN2JVWTFGSERuampnY3BHaWRZcml5eXd0cDlKditsSVJDanBaQmc2MUFORyt6bnJHQ2I3bXArZTAzUE5BZz09IiwibWFjIjoiODc1YjQ5Y2EzMjRiMmMzMDlkZDgzZmYyY2ZjNTdkZWE0NWNjZGQzMThmZDc1Y2ZhN2YxNThhODY3YmIyY2FjNSJ9",
    "expires_in": 7200,
    "sellerId": 128
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |access_token |string   |csrftoken  |
    |laravelcookie|string|商家登录成功产生cookie|
    |expires_in|int|有效期(秒)|
    |sellerId|int|商家id|
     * */
    public function login(Request $request){
        $sellerId = trim($request->input('sellerId'));
        Auth::guard('business')->logout();
        Auth::guard('business')->loginUsingId($sellerId);
        $laravelcookie =Crypt::encrypt(Session::getId());
        return response_json(200,trans('web.loginSuccess'),[
            'access_token' => csrf_token(),
            'token_type' => 'bearer',
            'laravelcookie'=>$laravelcookie,
            'expires_in' => env('SESSION_LIFETIME')*60,
            'sellerId'=>$sellerId
        ]);
    }
    
    /**
     * 60.26获取pos机列表
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功返回cookie   |
    |_token |是  |string | csrftoken    |
    |seller     |否  |string | 商家名称，登录或真实姓名    |
    |status|否|int|pos机绑定状态|
    |start_time|否|date|开始日期|
    |end_time|否|date|结束日期|

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "getDataSuccess",
    "data": {
    "current_page": 1,
    "data": [
    {
    "created_at": "2018-04-13 20:58:51",
    "username": "whm",
    "email": "15113993183@163.com",
    "phone_number": "15113993183",
    "pos_id": "622164964196213006",
    "status": "已绑定"
    },
    {
    "created_at": "2018-10-19 11:21:38",
    "username": "whm",
    "email": "15113993183@163.com",
    "phone_number": "15113993183",
    "pos_id": "123456",
    "status": "已绑定"
    }
    ],
    "first_page_url": "http://rapidz.com/adm/manage/posList?page=1",
    "from": 1,
    "last_page": 6,
    "last_page_url": "http://rapidz.com/adm/manage/posList?page=6",
    "next_page_url": "http://rapidz.com/adm/manage/posList?page=2",
    "path": "http://rapidz.com/adm/manage/posList",
    "per_page": "2",
    "prev_page_url": null,
    "to": 2,
    "total": 11
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |created_at |date   |创建时间  |
    |username|string|商家|
    |email|string|邮箱|
    |phone_number|string|电话号码|
    |pos_id|string|pos机pos_id|
    |status|string|状态|
    |first_page_url|string|第一页url|
    |last_page|int|总页码|
    |last_page_url|string|最后一页url|
    |next_page_url|string|下一页url|
    |total|int|总记录数|
     * */
    public function posList(Request $request){
        $export = 0;
        $data = $this->getPosList($request,$export);
        foreach($data['data'] as $k=>$v){
            $data['data'][$k]['status'] = $this->bind_status($v['status']);
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    public function bind_status($status){
        if (!$status){
            $status = trans('web.unbound');
        }else{
            $status = trans('web.bound');
        }
        return $status;
    }

    public function getPosList($request,$export){
        $seller = trim($request->input('seller'));
        $start_time = trim($request->input('start_time'));
        $end_time = trim($request->input('end_time'));
        $status = trim($request->input('status'));
        $page = trim($request->input('page',1));
        $count = trim($request->input('count',5));

        $list = Pos::from('pos')
            ->join('business','pos.sellerId','=','business.sellerId')
            ->select('pos.id','pos.sellerId','pos.created_at','business.actual_name','business.nickname','business.email','business.phone_number','pos.pos_id','pos.status','business.area');

        if (isset($seller)) {
            $list->where('business.nickname', 'like', "%{$seller}%");
        }

        if (strlen($status)){
            $list->where('pos.status',$status);
        }

        //传入相同日期则查询当天数据
        if ($start_time &&$end_time &&$start_time == $end_time){
            $end_time = date('Y-m-d',strtotime($end_time)+60*60*24);
            $list->where('pos.created_at','>',$start_time);
            $list->where('pos.created_at','<',$end_time);
        }else{
            if ($start_time){
                $list->where('pos.created_at','>',$start_time);
            }
            if ($end_time){
                $list->where('pos.created_at','<',date('Y-m-d',strtotime($end_time)+60*60*24));
            }
        }

        if (!$export){
            $list = $list->orderBy('pos.created_at','desc')->paginate($count)->toArray();
            foreach($list['data'] as $k=>$v){
                $list['data'][$k]['phone_number'] = '+'.str_replace('+','',$v['area']).$list['data'][$k]['phone_number'];
            }
        }else{
            $list = $list->orderBy('pos.created_at','desc')->get()->toArray();
            foreach($list as $k=>$v){
                $list[$k]['phone_number'] = '+'.str_replace('+','',$v['area']).$list[$k]['phone_number'];
            }
        }
        return $list;
    }

    /**
     * 60.27导出pos机列表
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token |是  |string |csrftoken    |
    |seller|否  |string | 商家名称，登录或真实姓名    |
    |status|否|int|绑定状态，0为未绑定，1为已绑定|
    |start_time|否|date|开始时间|
    |end_time|否|date|结束时间|

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "created_at": "2018-04-13 20:58:51",
    "username": "whm",
    "email": "15113993183@163.com",
    "phone_number": "15113993183",
    "pos_id": "622164964196213006",
    "status": "已绑定"
    },
    {
    "created_at": "2018-05-22 14:47:00",
    "username": "15915844503",
    "email": "linlicai1991@163.com",
    "phone_number": "15915844503",
    "pos_id": "927044501869208157",
    "status": "未绑定"
    },
    {
    "created_at": "2018-05-22 18:01:03",
    "username": "15915844503",
    "email": "linlicai1991@163.com",
    "phone_number": "15915844503",
    "pos_id": "528178005195607254",
    "status": "未绑定"
    },
    {
    "created_at": "2018-05-22 18:01:52",
    "username": "15915844503",
    "email": "linlicai1991@163.com",
    "phone_number": "15915844503",
    "pos_id": "975621083635215882",
    "status": "未绑定"
    },
    {
    "created_at": "2018-10-19 11:21:38",
    "username": "whm",
    "email": "15113993183@163.com",
    "phone_number": "15113993183",
    "pos_id": "123456",
    "status": "已绑定"
    },
    {
    "created_at": "2018-11-08 14:41:40",
    "username": "whm",
    "email": "15113993183@163.com",
    "phone_number": "15113993183",
    "pos_id": "1234567",
    "status": "已绑定"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |created_at |date   |创建时间  |
    |username|string|卖家|
    |email|string|电子邮箱|
    |phone_number|string|电话号码|
    |pos_id|string|pos机id|
    |status|string|状态|
     * */
    public function exportPos(Request $request){
        $export = 1;
        $list = $this->getPosList($request,$export);
        foreach($list as $k=>$v){
            $list[$k]['status'] = $this->bind_status($v['status']);
            $list[$k]['pos_id'] = "\t".$v['pos_id']."\t";
            $list[$k]['phone_number'] = "\t".$v['phone_number']."\t";
            $list[$k]['action'] = $this->edit_status($v['status']);
        }
        $file_name = trans('web.posList');
        $columns_arr = array(
            array('title' => trans('web.createTime'), 'field' => 'created_at', 'width' => 20),
            array('title' => trans('web.seller'), 'field' => 'actual_name', 'width' => 20),
            array('title' => trans('web.email'), 'field' => 'email', 'width' => 20),
            array('title' => trans('web.phone'), 'field' => 'phone_number', 'width' => 30),
            array('title' => trans('web.posId'), 'field' => 'pos_id', 'width' => 20),
            array('title' => trans('web.action'), 'field' => 'action', 'width' => 30),
            array('title' => trans('web.status'), 'field' => 'status', 'width' => 20),
        );
        excel_export($file_name, $list, $columns_arr);
    }

    public function edit_status($status){
        if ($status){
            $status = trans('web.editPosId');
        }else{
            $status = trans('web.bindPosId');
        }
        return $status;
    }

    /**
     * 60.28获取所有未绑定pos_id
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |管理员登录成功产生cookie   |
    |_token |是  |string |csrf_token    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "pos_id": 15,
    "posId": "450562785886469759"
    },
    {
    "pos_id": 16,
    "posId": "948250717501958206"
    },
    {
    "pos_id": 17,
    "posId": "666981139284406639"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |pos_id |int   |pos机自增id  |
    |posId|string|posId|
     * */
    public function getPosId(){
        $data = PosNumber::select('pos_id','posId')->where('is_used',0)->get();
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.29查找指定pos_id
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |用户名   |
    |_token |是  |string | 密码    |
    |posId     |是  |string | 昵称    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "pos_id": 215,
    "posId": "267574350273371916"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |pos_id |int   |pos机自增id  |
    |posId|string|posId|
     * */
    public function findPosId(Request $request){
        $posId = trim($request->input('posId'));
        $data = PosNumber::select('pos_id','posId')->where('is_used',0)->where('posId','like',"%$posId%")->get();
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.30pos机绑定posId
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |用户名   |
    |_token |是  |string | 密码    |
    |sellerId     |是  |int | 商家id    |
    |id|是|int|pos机自增id|

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "更新数据成功"
    }
    ```
     * */
    public function bindPosId(Request $request){
        $validator = Validator::make($request->all(), [
            'sellerId' => 'required|int',
            'id' => 'required|int',
            'posId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $sellerId = trim($request->input('sellerId'));
        $id = trim($request->input('id'));
        $posId = trim($request->input('posId'));
        //查询pos_id是否可用
        //$re = PosNumber::where('posId',$posId)->where('is_used',0)->first();
        //if (!$re){
        //    return response_json(402,trans('web.posUndefined'));
        //}
        DB::beginTransaction();
        $re = Pos::where(array('id'=>$id,'sellerId'=>$sellerId))->update(['pos_id'=>$posId,'status'=>1,'updated_at'=>date('Y-m-d H:i:s',time())]);
        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.bindPosIdFail'),1);
        }
        $re = PosNumber::where('posId',$posId)->update([
            'sellerId'=>$sellerId,
            'is_used'=>1,
            'key'=>str_random(64),
            'updated_at'=>date('Y-m-d H:i:s',time())
        ]);
        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.bindPosIdFail'),2);
        }
        //根据商家id商家用户id
        //$uid = \App\Models\User::where('sellerId',$sellerId)->first(['id'])->id;
        // 生成pos钱包地址
        //$wallet_model = new UsersWallet();
        //$wallet_model->add_pos_address($posId, $sellerId,$uid);
        DB::commit();
        $admin = Auth('admin')->user();
        $pos = Pos::where('id',$id)->first();
        //记录日志，管理员修改APP已开通服务国家
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'为POS机'.$pos->nickname.'绑定pos_id'.$posId;
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.'POS machine'.$pos->nickname.'  bind pos_id '.$posId;
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'为POS机'.$pos->nickname.'绑定pos_id'.$posId;
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        return response_json(200,trans('web.bindPosIdSuccess'));
    }

    /**
     * 60.31pos机更换posId
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |用户名   |
    |_token |是  |string | 密码    |
    |id|是|int|pos机自增id|
    |sellerId|是|int|商家id|
    |oldPosId     |是  |string | pos机原绑定posId    |
    |newPosId|是|string|pos机新绑定posId|


     **返回示例**

    ```
    {
    "code": 200,
    "msg": "更新数据成功"
    }
    ```
     * */
    public function changePosId(Request $request){
        $validator = Validator::make($request->all(), [
            'sellerId' => 'required|int',
            'id' => 'required|int',
            'oldPosId'=>'required|string',
            'newPosId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }

        $sellerId = trim($request->input('sellerId'));
        $id = trim($request->input('id'));
        $oldPosId = trim($request->input('oldPosId'));
        $newPosId = trim($request->input('newPosId'));
        //查询pos_id是否可用
        $re = PosNumber::where('posId',$newPosId)->where('is_used',0)->first();
        if (!$re){
            return response_json(402,trans('web.posUndefined'));
        }
        //查询pos_id是否是第一次修改
        DB::beginTransaction();
        //修改pos机绑定pos_id
        $re = Pos::where(array('id'=>$id))->update(['pos_id'=>$newPosId,'updated_at'=>date('Y-m-d H:i:s',time())]);
        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.bindPosIdFail'));
        }
        //修改新posId状态为1,已使用
        $re = PosNumber::where('posId',$newPosId)
            ->update([
                'sellerId'=>$sellerId,
                'is_used'=>1,
                'key'=>str_random(64),
                'updated_at'=>date('Y-m-d H:i:s',time())
            ]);
        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.bindPosIdFail'));
        }
        //修改原posId状态为0，未使用
        $re = PosNumber::where('posId',$oldPosId)->update(['is_used'=>0,'updated_at'=>date('Y-m-d H:i:s',time())]);
        if (!$re){
            DB::rollBack();
            return response_json(403,trans('web.bindPosIdFail'));
        }
        //根据商家id商家用户id
        $uid = \App\Models\User::where('sellerId',$sellerId)->first(['id'])->id;
        // 生成pos钱包地址
        $wallet_model = new UsersWallet();
        $wallet_model->add_pos_address($oldPosId, $sellerId,$uid);
        //查询原pos_id是否有订单生成，有的话修改
        $order = BusinessOrder::where(array('pos_id'=>$oldPosId,'seller_id'=>$sellerId))->first();
        if ($order){
            //修改商家订单表的pos_id，备份原pos_id
            $re = BusinessOrder::where(array('pos_id'=>$oldPosId,'seller_id'=>$sellerId))->update(
                [
                    'pos_id'=>$newPosId,
                    'old_pos_id'=>$oldPosId,
                    'updated_at'=>date('Y-m-d H:i:s',time())]
            );
            if (!$re){
                return response_json(403,trans('web.updateFail'),1);
            }
        }
       //修改pos_wallet表pos_id，备份原pos_id
        $re = PosWallet::where(array('pos_id'=>$oldPosId,'sellerId'=>$sellerId))->update([
            'pos_id'=>$newPosId,
            'old_pos_id'=>$oldPosId,
            'updated_at'=>date('Y-m-d H:i:s',time())
        ]);
        if (!$re){
            return response_json(403,trans('web.updateFail'),2);
        }
        DB::commit();
        return response_json(200,trans('web.bindPosIdSuccess'));
    }

    /**
     * 60.32获取所有国家
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "country_id": 1,
    "country": "中国",
    "en_country": "China",
    "tw_country": "中國",
    },
    {
    "country_id": 2,
    "country": "香港",
    "en_country": "Hongkong",
    "tw_country": "香港",
    },
    {
    "country_id": 3,
    "country": "澳门",
    "en_country": "Macao",
    "tw_country": "澳門",
    },
    {
    "country_id": 4,
    "country": "台湾",
    "en_country": "Taiwan",
    "tw_country": "臺灣",
    },
    {
    "country_id": 5,
    "country": "马来西亚",
    "en_country": "Malaysia",
    "tw_country": "馬來西亞",
    },
    {
    "country_id": 6,
    "country": "印度尼西亚",
    "en_country": "Indonesia",
    "tw_country": "印度尼西亞",
    },
    {
    "country_id": 7,
    "country": "菲律宾",
    "en_country": "Philippines",
    "tw_country": "菲律賓",
    },
    {
    "country_id": 8,
    "country": "新加坡",
    "en_country": "Singapore",
    "tw_country": "新加坡",
    },
    {
    "country_id": 9,
    "country": "泰国",
    "en_country": "Thailand",
    "tw_country": "泰國",
    },
    {
    "country_id": 10,
    "country": "日本",
    "en_country": "Japan",
    "tw_country": "日本",
    },
    {
    "country_id": 11,
    "country": "韩国",
    "en_country": "South Korea",
    "tw_country": "韓國",
    }
    ]
    }
    ```
     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |country_id|int|国家id|
    |country|string|国家中文名称|
    |en_country|string|国家英文名称|
    |tw_Country|string|国家中文繁体名称|
     * */
    public function getAllCountry(){
        $admin = Auth('admin')->user();
        if ($admin) {
            $lang = $admin->language;
        }else{
            $lang = 'cn';
        }
        $data = DB::table('regions')
            ->select('country_id','country','en_country','tw_country')
            ->where('is_open',1)
            ->orderBy('en_country')
            ->get()
            ->toArray();
        $data = json_decode(json_encode($data),true);
        if ($lang == 'en'){
            foreach($data as $k=>$v){
                $data[$k]['country'] = $v['en_country'];
            }
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.33获取所有法定货币
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "Get Data Successful",
    "data": [
    {
    "current_id": 8001,
    "short_en": "USD"
    },
    {
    "current_id": 8002,
    "short_en": "EUR"
    },
    {
    "current_id": 8005,
    "short_en": "CNY"
    },
    {
    "current_id": 8004,
    "short_en": "JPY"
    },
    {
    "current_id": 8009,
    "short_en": "HKD"
    },
    {
    "current_id": 10,
    "short_en": "KRW"
    },
    {
    "current_id": 11,
    "short_en": "RUB"
    },
    {
    "current_id": 8003,
    "short_en": "GBP"
    },
    {
    "current_id": 8010,
    "short_en": "SGD"
    },
    {
    "current_id": 8012,
    "short_en": "TWD"
    },
    {
    "current_id": 8007,
    "short_en": "CAD"
    },
    {
    "current_id": 8006,
    "short_en": "AUD"
    },
    {
    "current_id": 17,
    "short_en": "BRL"
    },
    {
    "current_id": 18,
    "short_en": "INR"
    },
    {
    "current_id": 8008,
    "short_en": "CHF"
    },
    {
    "current_id": 8011,
    "short_en": "THB"
    },
    {
    "current_id": 21,
    "short_en": "MOP"
    },
    {
    "current_id": 22,
    "short_en": "NZD"
    },
    {
    "current_id": 23,
    "short_en": "ZAR"
    },
    {
    "current_id": 24,
    "short_en": "SEK"
    },
    {
    "current_id": 8014,
    "short_en": "IDR"
    },
    {
    "current_id": 174,
    "short_en": "SVC"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |current_id |int   |法定货币id  |
    |short_en|string|货币短英文名|
     * */
    public function getLegalCurrency(){
        $data = Currency::select('current_id','short_en')->where(array('enabled'=>1,'is_virtual'=>0))->get();
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.34获取所有虚拟货币
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |cookie |是  |string |登录cookie   |
    |token |是  |string | csrftoken    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "current_id": 1001,
    "short_en": "BTC"
    },
    {
    "current_id": 1002,
    "short_en": "RPZ"
    },
    {
    "current_id": 1005,
    "short_en": "LTC"
    },
    {
    "current_id": 1003,
    "short_en": "ETH"
    },
    {
    "current_id": 1006,
    "short_en": "BCHABC"
    },
    {
    "current_id": 1008,
    "short_en": "BCHSV"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |current_id |int   |币种id  |
    |short_en|string|货币名称|
     * */
    public function getVirtualCurrency(){
        $data = Currency::select('current_id','short_en')->where(array('enabled'=>1,'is_virtual'=>1))->get();
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.35获取所有区号
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |_token |是  |string |csrftoken   |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": [
    {
    "country": "中国",
    "en_country": "China",
    "tw_country": "中國",
    "region": "86"
    },
    {
    "country": "香港",
    "en_country": "Hongkong",
    "tw_country": "香港",
    "region": "852"
    },
    {
    "country": "澳门",
    "en_country": "Macao",
    "tw_country": "澳門",
    "region": "853"
    },
    {
    "country": "台湾",
    "en_country": "Taiwan",
    "tw_country": "臺灣",
    "region": "886"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |country |string   |地区简体中文名称  |
    |en_country|string|地区英文名称|
    |tw_country|string|国家繁体中文名称|
    |region|int|电话区号|
     * */
    public function getArea(){
        $admin = Auth('admin')->user();
        if ($admin) {
            $lang = $admin->language;
        }else{
            $lang = 'cn';
        }
        $data = Regions::select('country','en_country','tw_country','region')->where('is_open',1)->orderBy('en_country')->get();
        $data = json_decode(json_encode($data),true);
        if ($lang == 'en'){
            foreach($data as $k=>$v){
                $data[$k]['country'] = $v['en_country'];
            }
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.36获取普通商家手续费
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |token |是  |string |管理员登录token   |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": {
    "btc_charge": [
    {
    "id": 1,
    "max_revenue": "50.00000000",
    "min_revenue": "10.00000000",
    "charge": "0.05",
    "current_id": 1001,
    "created_at": "2018-12-25 17:09:17",
    "updated_at": "2018-12-25 17:09:17"
    },
    {
    "id": 2,
    "max_revenue": "100.00000000",
    "min_revenue": "60.00000000",
    "charge": "0.02",
    "current_id": 1001,
    "created_at": "2018-12-25 17:09:19",
    "updated_at": "2018-12-25 17:09:19"
    },
    {
    "id": 3,
    "max_revenue": "500.00000000",
    "min_revenue": "200.00000000",
    "charge": "0.01",
    "current_id": 1001,
    "created_at": "2018-12-25 17:09:20",
    "updated_at": "2018-12-25 17:09:20"
    }
    ],
    "rpz_charge": [
    {
    "id": 4,
    "max_revenue": "60.00000000",
    "min_revenue": "20.00000000",
    "charge": "0.04",
    "current_id": 1002,
    "created_at": "2018-12-25 17:09:21",
    "updated_at": "2018-12-25 17:09:21"
    },
    {
    "id": 5,
    "max_revenue": "200.00000000",
    "min_revenue": "80.00000000",
    "charge": "0.03",
    "current_id": 1002,
    "created_at": "2018-12-25 17:09:23",
    "updated_at": "2018-12-25 17:09:23"
    },
    {
    "id": 6,
    "max_revenue": "500.00000000",
    "min_revenue": "300.00000000",
    "charge": "0.01",
    "current_id": 1002,
    "created_at": "2018-12-25 17:09:26",
    "updated_at": "2018-12-25 17:09:26"
    }
    ]
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |btc_charge |array   |btc手续费数组  |
    |rpz_charge |array   |rpz手续费数组|
    |id|int|手续费id|
    |max_revenue|string|最大营业额|
    |min_revenue|string|最小营业额|
    |charge|string|手续费|
    |current_id|int|货币id,1001为btc,1002为rpz|
     * */
    public function getCharge(Request $request){
        $current_id = trim($request->input('current_id'));
        $data = BusinessCharge::where('current_id',$current_id)->get();
        $data = json_decode(json_encode($data),true);
        if (!$data){
            $data = array(
                array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$current_id),
                array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$current_id),
                array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$current_id),
            );
        }
        if (count($data) == 2){
            $data[] = array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$current_id);
        }
        if (count($data) == 1){
            $data[] = array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$current_id);
            $data[] = array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$current_id);
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.37删除特定商家手续费
     * **请求方式：**
    - POST

     **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |token 	  |是  |string |管理员登录token   |
    |business_id |是  |string | 商家sellerId    |
    |current_id     |是  |int | 货币id    |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "删除数据成功"
    }
    ```
     * */
    public function delPosCharge(Request $request){
        $sellerId = trim($request->input('business_id'));
        $current_id = trim($request->input('current_id'));
        DB::beginTransaction();
        //删除特定商家汇率表中的数据
        $re = BusinessVipCharge::where(array('sellerId'=>$sellerId,'current_id'=>$current_id))->first();
        if (!$re->delete()){
            DB::rollBack();
            return response_json(403,trans('web.deleteFail'));
        }
        //判断是否设置btc或rpz手续费，两者均没有则把商家修改为普通商家
        $charge = BusinessVipCharge::where('sellerId',$sellerId)->first();
        if (!$charge){
            $re = User::where('sellerId',$sellerId)->update(['specific'=>1,'updated_at'=>date('Y-m-d H:i:s',time())]);
            if (!$re){
                DB::rollBack();
                return response_json(403,trans('web.deleteFail'));
            }
        }
        DB::commit();
        $admin = Auth('admin')->user();
        $seller = User::where('sellerId',$sellerId)->first();
        //记录日志，管理员修改APP已开通服务国家
        if ($admin->language == 'cn'){
            $msg = '管理员'.$admin->username.'删除商家'.$seller->nickname.'手续费';
        }elseif($admin->language == 'en'){
            $msg = 'Administrators '.$admin->username.' del business '.$seller->nickname.' fees';
        }elseif($admin->language == 'hk'){
            $msg = '管理员'.$admin->username.'删除商家'.$seller->nickname.'手续费';
        }
        SystemOperationLog::add_log($admin->id,$request,$msg);
        return response_json(200,trans('web.deleteSuccess'));
    }

    /**
     * 60.38获取所有币种手续费
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |token |是  |string |管理员登录token   |

     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": {
    "btc": [
    {
    "id": 277,
    "max_revenue": "50.00000000",
    "min_revenue": "0.00000000",
    "charge": "0.05000000",
    "current_id": 1001,
    "created_at": "2019-01-18 15:47:19",
    "updated_at": "2019-01-18 15:55:47"
    },
    {
    "id": 278,
    "max_revenue": "100.00000000",
    "min_revenue": "50.00000000",
    "charge": "0.03000000",
    "current_id": 1001,
    "created_at": "2019-01-18 15:47:19",
    "updated_at": "2019-01-18 15:55:47"
    },
    {
    "id": 279,
    "max_revenue": "1000.00000000",
    "min_revenue": "100.00000000",
    "charge": "0.01000000",
    "current_id": 1001,
    "created_at": "2019-01-18 15:47:19",
    "updated_at": "2019-01-18 15:55:47"
    }
    ],
    "rpz": [
    {
    "id": 280,
    "max_revenue": "50.00000000",
    "min_revenue": "0.00000000",
    "charge": "0.05000000",
    "current_id": 1002,
    "created_at": "2019-01-18 15:56:13",
    "updated_at": null
    },
    {
    "id": 281,
    "max_revenue": "100.00000000",
    "min_revenue": "50.00000000",
    "charge": "0.03000000",
    "current_id": 1002,
    "created_at": "2019-01-18 15:56:13",
    "updated_at": null
    },
    {
    "id": 282,
    "max_revenue": "1000.00000000",
    "min_revenue": "100.00000000",
    "charge": "0.01000000",
    "current_id": 1002,
    "created_at": "2019-01-18 15:56:13",
    "updated_at": null
    }
    ],
    "ltc": [],
    "eth": [],
    "bch": [],
    "bsv": []
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |btc |array   |普通商家btc手续费  |
    |rpz |array   |普通商家rpz手续费|
    |ltc |array   |普通商家ltc手续费|
    |eth |array   |普通商家etc手续费|
    |bch |array   |普通商家bch手续费|
    |bsv |array   |普通商家bsv手续费|
    |id  |int     |普通商家手续费id||
    |max_revenue|decimal|交易额较大值|
    |min_revenue|decimal|交易额较小值|
    |charge     |decimal|手续费|
    |current_id |int|币种id|
     * */
    public function getAllCharge(){
        //获取所有可用虚拟币种
        $current = Currency::where('is_virtual',1)->where('enabled',1)->select('current_id','short_en')->get()->toArray();
        foreach($current as $k=>$v){
            $current[$k]['short_en'] = strtoupper($v['short_en']);
        }
        $res = array();
        foreach ($current as $k=>$v){
            $list = BusinessCharge::where('current_id',$v['current_id'])
                ->select('max_revenue','min_revenue','charge','current_id')->get()->toArray();
            foreach($list as $kk=>$vv){
                $list[$kk]['name'] = $v['short_en'];
            }
            if (count($list)==0){
                $list = array(
                    array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$v['current_id'], 'name'=>$v['short_en']),
                    array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$v['current_id'], 'name'=>$v['short_en']),
                    array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$v['current_id'], 'name'=>$v['short_en']),
                );
            }elseif (count($list)==1){
                $add = array(
                    array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$v['current_id'], 'name'=>$v['short_en']),
                    array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$v['current_id'], 'name'=>$v['short_en']),
                );
                $list = array_merge($list,$add);
            }elseif (count($list)==2){
                $add = array(
                    array('max_revenue'=>'', 'min_revenue'=>'', 'charge'=>'', 'current_id'=>$v['current_id'], 'name'=>$v['short_en']),
                );
                $list = array_merge($list,$add);
            }else{
                $list = $list;
            }
            $unit = strtolower($v['short_en']);
            $res[$unit] = $list;
        }
        $data = array();
        foreach ($res as $k=>$v){
            $data = array_merge($data,$v);
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.39删除设置的手续费
     * */
    public function delCharge(Request $request){
        $id = trim($request->input('id'));
        $re = BusinessCharge::where('id',$id)->delete();
        if (!$re){
            return response_json(403,trans('web.deleteFail'));
        }
        return response_json(200,trans('web.deleteSuccess'));
    }

    /**
     * 60.40查询下一级地区
     * */
    public function getChild(Request $request){
        $id = trim($request->input('id'));
        $list = DB::table('world')->where('pid',$id)->get()->toArray();
        return response_json(200,trans('web.getDataSuccess'),$list);
    }

    /**
     * 60.41商家推荐人列表
     * */
    public function recomList(Request $request){
        $sellerId = trim($request->input('sellerId'));
        $page = trim($request->input('page',10));
        $count = trim($request->input('count',10));
        $data = Recommend::from('business_recommend_code as brc')
            ->join('business as b','b.sellerId','brc.sellerId')
            ->where('brc.recommend_id',$sellerId)
            ->where('brc.status',1)
            ->select('brc.recommend_code','b.nickname','b.phone_number','b.email','b.created_at')
            ->paginate($count)
            ->toArray();
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    /**
     * 60.42商家pos机收款码
     ***参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |seller_id |是  |int|商家id   |
    |pos_id |是  |string | pos_id    |


     **返回示例**

    ```
    {
    "code": 200,
    "msg": "获取数据成功",
    "data": {
    "qrcode": "http://rapidz.com/storage/qrcodes/receive_money_tmp/VMVIDOYC8JC7DUUJB2P2HH84348XWHU4.png"
    }
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |qrcode |string   |pos机收款二维码地址  |
     * */
    public function downloadQrCode(Request $request){
        $sellerId = trim($request->input('seller_id'));
//        $sellerId = 50000;
        $validator = Validator::make($request->all(), [
            'pos_id' => 'required|string',
            'seller_id'=>'required|integer'
        ]);
        if ($validator->fails()) {
            return response_json(402, $validator->errors()->first());
        }
        $pos_id = $request->input('pos_id');
        $isRenew = 0;
        $amount = '0.00000000';
        $file_path = Business::getReceiveMoney($sellerId, $pos_id, $isRenew, $amount);
        $data = array(
          'qrcode'=>$file_path
        );
        //判断文件是否存在
        return response_json(200, trans('web.getDataSuccess'), $data);

    }

    /**
     * 60.43获取所有公司类型
     * **参数：**

    |参数名|必选|类型|说明|
    |:----    |:---|:----- |-----   |
    |token|否|string|登录token和lang二选一|
    |lang|否|string|英文en,中文cn|


     **返回示例**

    ```
    {
    "code": 200,
    "msg": "data acquisition success",
    "data": [
    {
    "id": 1,
    "name": "Automotive"
    },
    {
    "id": 2,
    "name": "Construction"
    },
    {
    "id": 3,
    "name": "Education / Training"
    },
    {
    "id": 4,
    "name": "Entertainment"
    },
    {
    "id": 5,
    "name": "Fashion"
    },
    {
    "id": 6,
    "name": "Finance & Insurance"
    },
    {
    "id": 7,
    "name": "Food & Beverage"
    },
    {
    "id": 8,
    "name": "IT / Internet"
    },
    {
    "id": 9,
    "name": "Legal"
    },
    {
    "id": 10,
    "name": "Media"
    },
    {
    "id": 11,
    "name": "Health Services"
    },
    {
    "id": 12,
    "name": "Procurement Trade"
    },
    {
    "id": 13,
    "name": "Real Estate"
    },
    {
    "id": 14,
    "name": "Supply Chain"
    },
    {
    "id": 15,
    "name": "Transportation"
    },
    {
    "id": 16,
    "name": "Retail"
    },
    {
    "id": 17,
    "name": "Service"
    }
    ]
    }
    ```

     **返回参数说明**

    |参数名|类型|说明|
    |:-----  |:-----|-----                           |
    |id |int   |类型id  |
    |name|string|名称|
     * */
    public function companyType(){
        $lang = Auth('admin')->user()->language;
        $data = DB::table('company_type')->select('id','name_cn','name_en','name_hk')->where('is_del',0)->get()->toArray();
        $data = json_decode(json_encode($data),true);
        foreach ($data as $k=>$v){
            if ($lang == 'cn'){
                $data[$k]['name'] = $v['name_cn'];
            }elseif($lang == 'en'){
                $data[$k]['name'] = $v['name_en'];
            }elseif($lang == 'hk'){
                $data[$k]['name'] = $v['name_hk'];
            }
            unset($data[$k]['name_cn']);
            unset($data[$k]['name_en']);
            unset($data[$k]['name_hk']);
        }
        return response_json(200,trans('web.getDataSuccess'),$data);
    }

    public function editLoginPwd(Request $request){
        $validator = Validator::make($request->all(), [
            'business_id'      =>'required|integer',
            //'old_password'     =>'required|string|min:6|max:18',
            'new_password'     =>'required|string|min:6|max:18',
            're_password'      =>'required|string|min:6|max:18',
        ]);
        if ($validator->fails()) {
            return response_json(402,$validator->errors()->first());
        }
        $sellerId = trim($request->input('business_id'));
        $new_password = trim($request->input('new_password'));
        $re_password = trim($request->input('re_password'));

        if ($new_password != $re_password){
            return response_json(402,trans('web.passwordConfirmError'));
        }
        $update = array(
            'password'=>bcrypt($new_password),
            'updated_at'=>date('Y-m-d H:i:s',time())
        );
        DB::beginTransaction();
        

        //修改business表
        $result = Business::where('sellerId',$sellerId)->update($update);
        if (!$result){
            DB::rollBack();
            return response_json(403,trans('web.passwordChangeFail'));
        }
        //修改user表
        $user = \App\Models\User::where('sellerId',$sellerId)->first();
        if ($user){
            $user->password = bcrypt(think_md5(md5($new_password)));
            if (!$user->save()){
                DB::rollBack();
                return response_json(403,trans('web.passwordChangeFail'));
            }
        }
        DB::commit();
        return response_json(200,trans('web.passwordChangeSuccess'));
    }
}