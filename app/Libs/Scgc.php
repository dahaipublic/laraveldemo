<?php


namespace App\Libs;

use App\Models\UsersWallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Class Scgc 恒升链货币接口
 * @package App\Libs
 */
class Scgc
{

    const PROCESSED_TRANSACTIONS_REDIS_KEY = 'processed_scgc_transactions_key';

    private $host;

    private $access_token;

    public $error_msg = 'unknown error';

    public function __construct($host = null, $access_token = null)
    {
        $this->host = $host ?? config('api.SCGC_IP');
        $this->access_token = $access_token ?? config('api.SCGC_ACCESS_TOKEN');
    }

    /**
     * 生成钱包账户密码
     * @param string $password 密码
     * @return string
     */
    public function createPassword($uid)
    {
        return "w$5K31{$uid}425Pf!BYm";//生成有规则的密码，防止密码丢失后钱包不能转账
    }

    /**
     * 创建钱包
     * @param $member_id 用户id
     * @param $password 钱包密码
     * @return bool|string 钱包地址
     */
    public function getNewAddress($member_id, $password)
    {
        $member_id = time() . '_' . $member_id;//member_id拼接时间戳，防止重复
        $data[] = [
            'member_id' => $member_id,
            'password' => $password
        ];
        $res = $this->curlPost($this->host . '/wallets', $data);
        if ($res === false || empty($res[$member_id]['wallet_address'])) {
            return false;
        }
        return $res[$member_id]['wallet_address'];
    }

    /**
     * 发起交易
     * @param string $from 转出的钱包地址
     * @param string$to 转入的钱包地址
     * @param float $value 转出金额，精确到小数点后6位
     * @param string $password 转出的钱包账户密码
     * @return bool|string 发起交易成功，返回交易hash
     */
    public function transfer($from, $to, $value, $password = '')
    {
        $password = $password ?: UsersWallet::where([['current_id', UsersWallet::SCGC], ['address', $from]])->value('password');
        $data = [
            'from' => $from,
            'to' => $to,
            'value' => $value,
            'password' => $password,
        ];
        $res = $this->curlPost($this->host . '/wallets/transfer', $data);
        if ($res === false || empty($res['hash'])) {
            return false;
        }
        return $res['hash'];
    }

    /**
     * 获取账户余额
     * @param string|array $address 钱包地址
     * @return float|array 钱包余额
     */
    public function getBalance($address)
    {
        if (is_string($address)){
            $data[] = ['address' => $address];
        }else{
            $data = $address;
        }

        $res = $this->curlPost($this->host . '/wallets/balance', $data);
        if (empty($res)) {
            return false;
        }
        return is_string($address) ? $res[$address]['balance'] : $res;
    }

    /**
     * 获取交易详情
     * @param string $hash 交易hash
     * @return array
     */
    public function getTransaction($hash)
    {
        return $this->curlGet($this->host . '/transactions/' . (string)$hash) ?: [];
    }

    /**
     * 确认交易确认数详情
     * @param string $hash 交易hash
     * @return array
     */
    public function getTransactionDetail($hash)
    {
        return $this->curlGet($this->host . '/transactions/' . (string)$hash . '/confirmation') ?: [];
    }

    /**
     * 获取单个钱包结算单列表
     * @param string $address 钱包地址
     * @param int $limit 每次查询的记录条数
     * @param int $page 页面
     * @return array 钱包的结算单列表
     */
    public function getStatements($address, $limit = 30, $page = 1)
    {
        $address = (string)$address;
        return $this->curlGet($this->host . "/wallets/statement/{$address}?page={$page}&limit={$limit}") ?: [];
    }

    /**
     * 获取多个钱包结算单列表
     * @param array $address 钱包地址
     * @return array 钱包的结算单列表
     */
    public function getManyWalletStatements($addresses)
    {
        $addresses = 'addresses[]=' . implode('&addresses[]=', (array)$addresses);
        return $this->curlGet($this->host . "/wallets/statement?{$addresses}") ?: [];
    }

    /**
     * 获取http请求头
     * @return array
     */
    protected function getHeader()
    {
        $access_token = $this->getAccessToken();
        return ["Authorization: {$access_token}", 'Content-Type: application/json', 'Accept: application/json'];
    }

    /**
     * 获取token
     * @return string
     */
    private function getAccessToken()
    {
        return $this->access_token;
    }

    /**
     * 解析API响应结果
     * @param $res
     * @param $status_code
     * @return array|bool
     */
    protected function parseResponse($res, $status_code)
    {
        $res = json_decode($res, true);
        if ($status_code != 200 || empty($res)){
            $this->error_msg = $res['meta']['message'] ?? $this->error_msg;
            return false;
        }
        return $res['data'] ?? [];
    }

    /**
     * GET 请求
     * @param string $url
     */
    protected function curlGet($url, $parse_response = true)
    {
        $curl = curl_init();
        if (stripos($url, 'https://') !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getHeader());
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl);
        curl_close($curl);
        if (intval($status['http_code']) != 200) {
            Log::useFiles(storage_path('scgcApiFail.log'));
            Log::info('http_get：', ['result' => json_decode($result, true), 'status' => $status, 'url' => $url, 'header' => $this->getHeader()]);
        }
        return $parse_response ? $this->parseResponse($result, intval($status['http_code'])) : $result;
    }

    /**
     * POST 请求
     * @param string $url
     * @param $data
     * @param $headers
     * @return mixed
     */
    protected function curlPost($url, $data = [], $parse_response = true)
    {
        $curl = curl_init();
        if (stripos($url, 'https://') !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getHeader());
        $result = curl_exec($curl);
        $status = curl_getinfo($curl);
        curl_close($curl);
        if (intval($status['http_code']) != 200) {
            Log::useFiles(storage_path('scgcApiFail.log'));
            Log::info('http_post：', ['result' => $result, 'status' => $status, 'params' => $data, 'header' => $this->getHeader()]);
        }
        return $parse_response ? $this->parseResponse($result, intval($status['http_code'])) : $result;
    }


    /**
     * 标记交易已处理过
     * @param string $hash 交易hash
     * @return bool|int
     */
    public function markProcessedTransaction($hash)
    {
        return Redis::hSet(self::PROCESSED_TRANSACTIONS_REDIS_KEY, $hash, 1);
    }

    /**
     * 判断交易是否已处理过
     * @param string $hash 交易hash
     * @return bool
     */
    public function transactionIsProcessed($hash)
    {
        return Redis::hExists(self::PROCESSED_TRANSACTIONS_REDIS_KEY, $hash);
    }

    /**
     * 将用户钱包集合进行分片处理并提取出有效的地址列表
     * @param  object $wallet_list 用户钱包
     * @param  int $size 分片数量
     * @param  string $pluck_field 只获取某个字段
     * @return array scgc地址列表
     */
    public function chunkAddress($wallet_list, $size = 100, $pluck_field = 'address')
    {
        $wallet_list = $wallet_list->reject(function ($v){
           //过滤地址格式不正确的数据
            return empty($v['address']) || !preg_match("/^0x[0-9a-fA-F]{40}$/", $v['address']);
        });
        $pluck_field and $wallet_list = $wallet_list->pluck($pluck_field);
        return $wallet_list->chunk($size)->toArray();
    }

}