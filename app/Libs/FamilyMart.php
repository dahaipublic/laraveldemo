<?php
namespace App\Libs;

use App\Models\Api\CooperativeMerchant;
use App\Models\Currency;
use App\Models\RedemptionRecord;

class FamilyMart
{
	const MERCHANT_ID = '1';
	const VER_NO = '1.0';
	const PROC_CODE_QUERY = '4Q01';//4Q01查询
	const PROC_CODE_ADD = '4Q02';//累點(4Q02)
	const PROC_CODE_ADD_OUT = '4S02';//累點(4Q02)
	const PROC_CODE_REDEMPTION = '4Q04';//兌點(4Q04)
	const PROC_CODE_REDEMPTION_OUT = '4S04';//兌點(4Q04)
	const PROC_CODE_REFUSE = '4Q05';//兌點取消(4Q05)
	const POS_NO = '00';
	const STORE_NO = '000000';
	const VENDOR_ID = 'A003';
	const MAIN_ACT_ID = 'R81T';
	const src = '577f159d-8c65-44a7-8226-89263278a0ee';
	const TRAN_NO = 'A193L0000663283';
	const SEC_ACT_ID = '';
	const FORMAL_AES_KEY = 'b49184ad47ab42659962a656cefdbe4f';
	const TEST_AES_KEY = '188c7c1b9c224878b680cea33e011d39';
	const USED_CURRENT_ID = '8012';
	const RATE = 317.46031746;//1TWD->?P
	const POINTS_TO_CURRENCY = '0.01155';//91点换1TWD  11.55TWD = 1000P 1P=?T
	const CURRENCY_TO_POINTS = '0.01155';// 1000FM = 11.55RP
	const RP_TWD_TO_FM = '0.01155';// 1000FM = 11.55RP
	const FM_TO_RP_TWD = '0.00315';// 1000FM = 3.15RP

    //FM : 1000全家点数 = 2.95 RP
    //APP: 1000全家点数 = 11.55 RP

    //全家段的每1000点税和手续费是0.2
    //APP端的每1000点税和手续费是0.72
    const TEST_RP_KEY = '188c7c1b-9c22-4878-b680-cea33e011d39';
    const TEST_FM_KEY = '408f5f0b-4603-44da-933e-8f0a7fd7c174';

    const FORMAL_RP_KEY = 'b49184ad-47ab-4265-9962-a656cefdbe4f';
    const FORMAL_FM_KEY = 'b92d928b-4386-4ecf-8b51-15702afdbb37';

    protected static $_instance = null;
    protected static $_key = null;

    public static function getInstance(){
        if (!self::$_instance instanceof FamilyMart)
            self::$_instance = new self();
        return self::$_instance;
    }

    protected static function getKey($type='FM')
    {
        if(getenv('SERVER_ADDR') == '148.66.58.154')
        {
            if($type == 'FM'){
                self::$_key = self::TEST_FM_KEY;
            }else{
                self::$_key = self::TEST_RP_KEY;
            }
        }else{
            if($type == 'FM'){
                self::$_key  = self::FORMAL_FM_KEY;
            }else{
                self::$_key  = self::FORMAL_RP_KEY;
            }
        }
        return self::$_key;
    }

    //加密
    public function encrypt_openssl($str, $type = 'FM')
    {
        return openssl_encrypt($str, 'AES-256-CBC', self::getKey($type), 0, md5(self::getKey($type), 16));
    }

    //解密
    public function decrypt_openssl($encrypt, $type = 'FM')
    {
        return openssl_decrypt(base64_decode($encrypt), 'AES-256-CBC', self::getKey($type), 1, md5(self::getKey($type), 16));
    }

    //流的方式发送请求
    public function streamRequest($data, $type = 'FM')
    {
        $data = utf8_encode('Data4='.$this->encrypt_openssl($data, $type));
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/xml;\r\nContent-Length: ".strlen($data),
                'content' => $data
            ]
        ]);

        if(getenv('SERVER_ADDR') == '148.66.58.154'){
            return file_get_contents('https://vs2.family.com.tw/WebServiceFMPVendorTest/FMP_Vendor_QryPoint.ashx', false, $context);
        }else{
            return file_get_contents('https://vs2.family.com.tw/WebServiceFMPVendor/FMP_Vendor_QryPoint.ashx', false, $context);
        }


    }

    public function encrypt_openssl_rp($str)
    {
        if(getenv('SERVER_ADDR') != '148.66.58.154') {
            $key = self::FORMAL_AES_KEY;
            return openssl_encrypt($str, 'AES-256-CBC', $key, 0, md5($key, 16));
        }
        return openssl_encrypt($str, 'AES-256-CBC', env('AES_KEY'), 0, md5(env('AES_KEY'), 16));
    }

    public function decrypt_openssl_rp($encrypt)
    {
        if(getenv('SERVER_ADDR') != '148.66.58.154') {
            $key = self::FORMAL_AES_KEY;
            return openssl_decrypt(base64_decode($encrypt), 'AES-256-CBC', $key , 1, md5($key, 16));
        }
        return openssl_decrypt(base64_decode($encrypt), 'AES-256-CBC', env('AES_KEY') , 1, md5(env('AES_KEY'), 16));
    }

    public function streamRequest_rp($data)
    {
        $data = utf8_encode('Data4='.$this->encrypt_openssl_rp($data));
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/xml;\r\nContent-Length: ".strlen($data),
                'content' => $data
            ]
        ]);
        if(getenv('SERVER_ADDR') != '148.66.58.154') {
            return file_get_contents('https://vs2.family.com.tw/WebServiceFMPVendor/FMP_Vendor_QryPoint.ashx', false, $context);
        }
        return file_get_contents('https://vs2.family.com.tw/WebServiceFMPVendorTest/FMP_Vendor_QryPoint.ashx', false, $context);
    }

    //将XML转为array
    public function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    /**
     * @param $param
     * @param int $type
     * @return string
     */
    public function createXmlData($param, $type = 0)
    {
        switch ($type){
            case '1':
                $proc_code = self::PROC_CODE_ADD;
                break;
            case '2':
                $proc_code = self::PROC_CODE_REDEMPTION;
                break;
            case '3':
                $proc_code = self::PROC_CODE_REFUSE;
                break;
            default :
                $proc_code = self::PROC_CODE_QUERY;
                break;
        }
        $doc = new \DOMDocument('1.0', 'utf-8');
        $date = date('YmdHis');
        $xml = $doc->createElement('XML');
        $xml->appendChild($doc->createElement('VER_NO', self::VER_NO));//电文版本
        $xml->appendChild($doc->createElement('PROC_CODE', $proc_code));//交易代号
        $xml->appendChild($doc->createElement('POS_NO', self::POS_NO));//pos机号
        $xml->appendChild($doc->createElement('STORE_NO', self::STORE_NO));//店铺代号
        $xml->appendChild($doc->createElement('VENDOR_ID', self::VENDOR_ID));//厂商代号
        $xml->appendChild($doc->createElement('MAIN_ACT_ID', self::MAIN_ACT_ID));//主要活动代号
        $xml->appendChild($doc->createElement('SEC_ACT_ID', ''));//次要活动代号
        $xml->appendChild($doc->createElement('TRAN_NO', self::TRAN_NO));//厂商交易序号
        $xml->appendChild($doc->createElement('TXN_DT', $date));//交易日期
        $xml->appendChild($doc->createElement('TRAN_DT', $date));//通讯交易日期
        $xml->appendChild($doc->createElement('MEMBER_ID', $param['member_code']));//会员条码
        $xml->appendChild($doc->createElement('TXN_POINT', $param['point']));//交易点数
        $xml->appendChild($doc->createElement('TXN_AMOUNT', $param['amount']));//交易金额
        $doc->appendChild($xml);
        $data = $doc->saveXML($doc->documentElement, LIBXML_NOEMPTYTAG);
        return $data;
    }

    //检查是否是1000的倍数
    public function check_points($points)
    {
        return (($points % 1000 != 0) ||($points > 100000)) ? false : true ;
    }
    //
    public function exchangePoint($current_id, $amount)
    {
        if($amount <= 0){
            return 0;
        }
        $money = (new Currency())->exchangeCurrency($current_id, self::USED_CURRENT_ID, $amount);
        $point = intval(bcmul($money, self::RATE, 2));
        return $point;
    }

    public function getNeedRpPoints($point)
    {
//        if($this->check_points($point)){
            return bcmul($point, self::RP_TWD_TO_FM, 2);
//        }
//        return 0;
    }

    public function getNeedFmPoints($points)
    {
//        if($this->check_points($points)){
            return bcmul($points, self::FM_TO_RP_TWD, 3);
//        }
//        return 0;
    }

    public function getRpPointFee($rp_point)
    {
       return bcmul($rp_point , '0.02', 3);
    }

    public function getFmPointFee($fm_point)
    {
        return bcmul(bcdiv($fm_point,'1000', 8), '0.2', 8);
    }

    public function getPointFee($merchant_id, $point, $current_id, $type='FM')
    {
        //1计算数字货币兑换点数的手续费， 2计算点数兑换数字货币的手续费
        \Log::useDailyFiles(storage_path('logs/pointQuery.log'));
        $Currency = new Currency();
        $need_amount = '0.000001';

        //计算需要多少数字货币
        if($type == 'FM'){
            $rate = FamilyMart::POINTS_TO_CURRENCY;
        }else{
            $rate = FamilyMart::CURRENCY_TO_POINTS;
        }
        $need_twd = bcmul($point, $rate, 8);
        if($need_twd < '0.01') return $need_amount;
        $merchant_fee = CooperativeMerchant::where('merchant_id', $merchant_id)->value('fee');
        $need_fee = bcmul($need_twd, $merchant_fee, 8);
        $fee_amount = $Currency->exchangeCurrency(FamilyMart::USED_CURRENT_ID, $current_id,  $need_fee, true);

        \Log::info('getPointFee', ['fee_amount'=>$fee_amount]);
        if(bccomp($fee_amount, '0.000001', 8) <= 0) return $need_amount;
        $need_amount = $fee_amount;

        return $need_amount;
    }

    public function round_up($amount, $demical)
    {
        return $amount;
    }

    public function round_down($amount, $demical)
    {
        return $amount;
    }

    //获取廠商交易序號
    function getTranNo()
    {
        //redemption_record
        $code = $this->randString(4,2);
        $tran_no = $code.date('md').substr(time(), -5).substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 4);
         if(RedemptionRecord::where('tran_no', $tran_no)->value('id')){
             $tran_no = $this->getTranNo();
         }
        return $tran_no;
    }

    public function randString($len=6, $type=1){
        switch ($type) {
            case 0 :
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                break;
            case 1 :
                $chars = str_repeat('0123456789', 5);
                break;
            case 2 :
                $chars = str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZ', 2);
                break;
            case 3 :
                $chars = str_repeat('abcdefghijklmnopqrstuvwxyz', 2);
                break;
            default :
                // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
                $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
                break;
        }
        if ($len > 20) { //位数过长重复字符串一定次数
            $chars = str_repeat($chars, 5);
        }
        $chars = str_shuffle($chars);
        $str = substr($chars, 0, $len);
        return $str;
    }
}