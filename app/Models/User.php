<?php

namespace App\Models;
use App\Jobs\UpdateBalance;
//use Laravel\Passport\HasApiTokens;
use App\Models\Admin\AdminAcount;
use App\Models\Business\PosWallet;
use App\Models\Business\QrcodeReceive;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use App\Libs\Easemob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class User extends Model implements Authenticatable
{

    // use HasApiTokens;
    use Notifiable;
    protected $table = 'users';
    //禁用
    const STATUS_DISABLED = 0;
    //启用
    const STATUS_ALLOW = 1;
    //token标识符
    const USER_INFO = 'API_USER_INFO_';
    const STRING_SINGLETOKEN = 'API_STRING_SINGLETOKEN_';
    const STRING_APPTOKEN = 'API_STRING_APPTOKEN_';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const DEFAULT_AVATAR = 'storage/img/defaultlogo.png';

    protected $guarded = ['btc_balance'];
    // protected $fillable = [
    //     'api_token',
    // ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $primaryKey = 'id';

    //public $incrementing = false;

    protected $keyType = 'string';

    /**
     * 获取唯一标识的，可以用来认证的字段名，比如 id，guid
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return $this->primaryKey;
    }

    /**
     * 获取主键的值
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        $id = $this->{$this->getAuthIdentifierName()};
        return $id;
    }


    public function getAuthPassword()
    {
        return '';
    }

    public function getRememberToken()
    {
        return '';
    }

    public function setRememberToken($value)
    {
        return true;
    }

    public function getRememberTokenName()
    {
        return '';
    }
    /**
     * 获得与用户关联的电话记录。
     */
    public function familyMartMember()
    {
        return $this->hasOne('App\Models\FamilyMartMember', 'uid', 'id');

    }


    /**
     * 获得与用户关联的推荐码记录。
     */
    public function recommends()
    {
        return $this->hasOne('App\Models\AppRecommend','recommend_id','id')->select(['recommend_code']);
    }
    // protected static function getBaseUri()
    // {
    //     return config('api-host.user');
    // }

    // public static $apiMap = [
    //     'getUserByToken' => ['method' => 'GET', 'path' => 'login/user/token'],
    //     'getUserByGuId'  => ['method' => 'GET', 'path' => 'user/guid/:guid'],
    // ];


    /**
     * 获取用户信息 (by guid)
     * @param string $guid
     * @return User|null
     */
    public static function getUser($id)
    {
        return User::from('users as a')
            ->select('a.id', 'a.email', 'a.phone' ,'a.username','a.nickname','a.headimg_url','a.headimg_thumb','a.sex',
            'a.area','a.signature','a.customer_type','a.phone_status','a.email_status','a.language','a.recommend_code','a.status','b.*')
            ->join('users_info as b','a.id','=','b.uid')
            ->where('a.id', $id)
            ->first();
    }

    /**
     * 获取用户头像 (by guid)
     * @param string $guid
     * @return User|null
     */
    public function getUserUrl($id)
    {
        $inform=DB::table($this->table)
            ->select('id','portRaitUri')
            ->whereIn('id',$id)
            ->get();
        return $inform;
    }


    /**
     * 获取用户信息 (by token)
     * @param string $token
     * @return User|null
     */
    public static function getUserByToken(string $token)
    {
        try {
            $response = self::getItem('getUserByToken', [
                'Authorization' => $token
            ]);
        } catch (RestApiException $e) {
            return null;
        }

        return $response;
    }

    /*
     *查询总用户
     */
    public function user_count(){
        $list=DB::table($this->table)->where('sellerId','=','0')->count();
        return $list;
    }


    //获取七天用户
    public function get_week_user($time){
        $list=DB::table($this->table)
            ->select('created_at')
            ->where([
                ['created_at', '>', $time],
            ])
            ->get();
        return $list;
    }




    /**
     * 生成环信账号
     * @param type $uid
     * @param type $isAdmin 是否为管理员账号
     * @return json
     */
    public static function createEasemob($uid, $isAdmin = 0, $isTest = 0){

        $Easemob    = new Easemob();
        if (!empty($isTest)){
            $username   =  'test_' . $uid;
        }else{
            $username   = $isAdmin ? 'admin' . $uid : 'user' . $uid;
        }

        $password   = randomkeys(16);
        $result     = $Easemob->createUser($username, $password);

        if(!$result){
            $msg = '账号：'.$username.' 注册失败';
            return response_json(403, $msg);
        }
        if(isset($result['error'])){
            $msg = '账号：'.$username.' 错误信息：'.$result['error_description'];
            return response_json(403, $msg);
        }
        if(empty($isAdmin)){
            User::where('id', $uid)
                ->update(['easemob_u'=>$username, 'easemob_p'=>$password]);
        }else{
            AdminAcount::where('id', $uid)
                ->update(['easemob_u'=>$username, 'easemob_p'=>$password]);
        }
        // return response_json(200, '成功', $username);
        return response_json(200, '成功', array(
            'username' => $username,
            'password' => $password,
        ));
    }


    /**
     *
     * @param type $sellerId
     * @param type $type    1所有，2附近，3收藏
     * @return 用户环信账号（*需要其他字段可以加）
     */
    public static function getUserListByType($sellerId, $type = 1){

        $userList = self::select('easemob_u')->where('sellerId', 0)->where('status', 1)->where('sreceive_notice', 1); //  1 可通知
        switch ($type) {
            case 1://所有用户
                break;
            case 2://附近的用户，距离与附近的商家一致
                if(empty($sellerId)){
                    break;
                }
                $businessInfo = Business::select('longitude', 'latitude', 'geo_hash')
                    ->where('sellerId', $sellerId)
                    ->first();
                if(empty($businessInfo)){
                    break;
                }
                $geo_hash = substr($businessInfo->geo_hash, 0, 4);
                $userList = $userList->where('geo_hash', 'like', $geo_hash.'%');
                break;
            case 3://收藏过的用户
                if(empty($sellerId)){
                    break;
                }
                $collectList = NewsCollect::select('uid')
                    ->where('business_id', $sellerId)
                    ->groupBy('uid')
                    ->get();
                if(empty($collectList)){
                    break;
                }
                $collectList = array_column($collectList->toArray(), 'uid');
                $userList = $userList->whereIn('id', $collectList);
                break;
            default:
                break;
        }
        $userList = $userList->get();

        if(!empty($userList)){
            return $userList->toArray();
        }else{
            return [];
        }

    }


    /**
     * @desc 获取用户存在redis的所有token
     * @param $uid 用户id
     * @return array
     */
    public function getRedisToken($uid){

        $list = array();
        if($uid){
            $redis_list = Redis::keys('*');
            if(!empty($redis_list)){
                foreach ($redis_list as $redis_key){
                    if(strlen($redis_key) == 40){
                        $redis_item = Redis::get($redis_key);
                        if(strstr($redis_item, "a:") && strstr($redis_item, "{")){
                            $redis_item = unserialize($redis_item);
                            $user_id = isset($redis_item['uid']) ? $redis_item['uid'] : 0;
                            if(!empty($redis_item) && $user_id == $uid){
                                $temp = array(
                                    'redis_key' => $redis_key,
                                    'redis_item' => $redis_item,
                                    'user_id' => $user_id
                                );
                                $list[] = $temp;
                            }
                        }
                    }
                }
            }
        }
        return $list;

    }



    public function getUsersWallet(){

        /*
        * @param [string] [name] [需要关联的模型类名]
        * @param [string] [foreign] [参数一指定数据表中的字段]
        * */
        return $this->hasMany('App\Models\UsersWallet', 'uid', 'id');

    }





    /**
     * @desc 获取数据所用时间数组
     * @param int $wave_type  1 好友 2 社区 3 群聊 4 文章
     * @param $start_time 开始时间戳
     * @param $end_time 结束时间戳
     * @return array
     */
    public function getWaveDay($wave_type = 1, $start_time, $end_time){

        $time = time();
        $day_arr = array();
        $day_num = 0;
        $start_i = 1;
        if($wave_type == 1){
            $day_num = 7;
        }elseif ($wave_type == 2){
            $day_num = 15;
        }elseif ($wave_type == 3){
            $day_num = 30;
        }

        // 组合数据
        if($wave_type > 0){
            for ($i = $start_i; $i <= $day_num; $i++){
                $date_timestamp = strtotime( '+' . $i-$day_num .' days', $time);
                $date_tmp = array(
                    'ymd' => date('Y-m-d', $date_timestamp),
                    'ymd_his' => date('Y-m-d H:i:s', $date_timestamp),
                    'timestamp' => $date_timestamp
                );
                $day_arr[] = $date_tmp;
            }
        }else{
            // 计算日期段内有多少天
            $days = ($end_time-$start_time)/86400+1;
            for($i = 0; $i < $days; $i++){
                $date_timestamp = $start_time+(86400*$i);
                $date_tmp = array(
                    'ymd' => date('Y-m-d', $date_timestamp),
                    'ymd_his' => date('Y-m-d H:i:s', $date_timestamp),
                    'timestamp' => $date_timestamp
                );
                $day_arr[] = $date_tmp;
            }
        }

        return $day_arr;

    }


    /**
     * @desc 获取账号类型
     * @param int $customer_type 1为普通用户，2为官方账号， 3公众号
     * @param string $lang
     * @return mixed
     */
    public static function getCustomerType($customer_type = 0, $lang = 'cn'){

        //
        if($lang == 'cn'){
            $type_arr =  array(
                '', '普通用户', '官方账号', '公众号'
            );
        }else{
            $type_arr =  array(
                '', 'Ordinary Users', 'Official Account', 'The Public'
            );
        }
        return $type_arr[$customer_type];

    }


    /**
     * @desc 获取商家收款码二维码地址
     * @param string $sellerId    商家ID
     * @param string $money    二维码金额
     * @param boolean $refresh 是否重新生成current_id
     * @return string
     */
    public function getReceiveMoney($sellerId, $money = 0, $refresh = 0){

        try{

            $seller = User::from('users as u')
                ->select('u.receive_money', 'u.portRaitUri', 'c.current_id', 'c.short_en as unit')
                ->join('currency as c', 'u.fc_current_id',  '=', 'c.current_id')
                ->where('u.id', $sellerId)
                ->where('u.customer_type', 3)
                ->first();
            if(empty($seller) || !is_numeric($money)){
                return '';
            }elseif ($money<=0 && trim($seller->receive_money)!='' && empty($refresh)){
                return $seller->receive_money;
            }elseif ($money>0 && empty($refresh)){
                $receive_money = QrcodeReceive::where('sellerId', $sellerId)
                    ->where('money', $money)
                    ->orderBy('updated_at', 'desc')
                    ->value("receive_money");
                if(!empty($receive_money)){
                    return $receive_money;
                }
            }

            $current_id = $seller->current_id;
            $unit       = $seller->unit;
            $fileName   = randomkeys(32);
            // openssl 加密
            $aes_key = config('rp.PAY_QRCODE_KEY');
            $encrypted  = urlencode(encrypt_openssl('sellerId='.$sellerId.'&amount='.$money.'&current_id='.$current_id.'&unit='.$unit, $aes_key));
            $receive_money = url('download?businessReceive='.$encrypted.'&app=HamdantokenApp');

            if($money<=0){
                //不存在金额，生成默认
                $qr_path = 'qrcode/receive_money/'.date('Ymd').'/';
            }else{
                //存在金额，生成到固定可删除的目录
                $qr_path = 'qrcode/receive_money_tmp/'.date('Ymd').'/';
            }

            $path = storage_path('app/public/'.$qr_path);
            if(!is_dir($path)){
                create_dir($path);
            }

            // 二维码中间的logo图片
            //$facePath   = url($seller->portRaitUri);
            // $facePath   = 'https://www.baidu.com/img/bd_logo1.png';
            //if(!@fopen($facePath, 'r')){ //头像文件是否存在
            //    $facePath = storage_path('app/public/img/defaultlogo.png');
            //}
            $facePath = storage_path('app/public/qrcode/app_logo1.png');
            if(!@fopen($facePath, 'r')){
                $facePath = 'https://apptest.hamdantoken.io/storage/qrcode/app_logo.png';
            }
            $receive_money_path = $path.$fileName.'.png';

            ////////////// Storage::put 生成二维码改变
            $qrcode_create = \QrCode::format('png')->size(295)->merge($facePath, .20, true)->generate($receive_money, $receive_money_path);
            if($qrcode_create){
                $code_path = 'storage/'.$qr_path.$fileName.'.png';
                if($money<=0){
                    User::where('id', $sellerId)->update([
                        'receive_money' => $code_path
                    ]);
                }else{
                    (new QrcodeReceive())->saveQrcodeReceive($sellerId, $current_id, $money, $code_path);
                }
                return $code_path;
            }else{
                return '';
            }

        }catch(\Exception $exception){

            Log::useFiles(storage_path('getReceiveMoney.log'));
            Log::info('sellerId:'.$sellerId.', refresh:'.$refresh.',money:'.$money.',message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

        }

    }


    /**
     * 校验用户的登录信息
     */
    public static function checkUserInfo($authuser)
    {
        if (empty($authuser->pin)) {
            $authuser->pin = false;
        } else {
            $authuser->pin = true;
        }
        //指纹登录是否开启
        if ($authuser->fingerprint_login_status == 2) {
            $authuser->fingerlogin = 1;

        } else {
            $authuser->fingerlogin = 0;
        }
        unset($authuser->fingerprint_login_status);
        //指纹支付是否开启
        if ($authuser->fingerprint_pay_status == 2) {
            $authuser->fingerpay = 1;
        } else {
            $authuser->fingerpay = 0;
        }
        unset($authuser->fingerprint_pay_status);

        //人脸支付是否开启
        if ($authuser->face_pay_status == 2) {
            $authuser->facepay = 1;
        } else {
            $authuser->facepay = 0;
        }
        unset($authuser->face_pay_status);

        //人脸登录是否开启
        if ($authuser->face_login_status == 2) {
            $authuser->facelogin = 1;

        } else {
            $authuser->facelogin = 0;
        }
        unset($authuser->face_login_status);

        if (empty($authuser->birthday)) {
            $authuser->birthday = '';
        } else {
            $authuser->birthday = date("Y-m-d", $authuser->birthday);
        }

        if (empty($authuser->sellerId)) {
            $authuser->sellerId = 0;
        }

        if (empty($authuser->portRaitUri)) {
            $authuser->portRaitUri = url('storage/img/defaultlogo.png');
        } else {
            $authuser->portRaitUri = url($authuser->portRaitUri);
        }

        if (empty($authuser->img_thumb)) {
            $authuser->img_thumb = url('storage/img/defaultlogo.png');
        } else {
            $authuser->img_thumb = url($authuser->img_thumb);
        }
        return $authuser;
    }
}


