<?php
if (!function_exists('admin_asset')) {
    /**
     * @param $path
     *
     * @return string
     */
    function admin_asset($path)
    {
        return asset($path, '/resources');
    }
}

if (!function_exists('storageurl')) {
    /**
     * @param $path
     *
     * @return string
     */
    function storageurl($path)
    {
        return "https://store.rapidz.io/" . $path;
    }
}
if (!function_exists('formate_echo')) {
    /**
     * @param $path
     *
     * @return string
     */
    function formate_echo($code, $data)
    {
        $path ='';
        return asset($path, '/resources');
    }
}
if (!function_exists('response_json')) {
    /**
     * json数据格式
     * @param type $code
     * @param type $msg
     * @param type $data
     * @return 数组，laravel会自动转化为json
     */
    function response_json($code = 0, $msg = '', $data = [], $is_data = 0)
    {
        $vo = array(
            'code' => (int)$code,
            'msg' => (string)$msg
        );
        if (!empty($data) || $is_data) {
            $vo['data'] = $data;
        }

////        if($code != 200){
//        if(!empty($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['HTTP_ACCEPT'])){
//
//            $url = $_SERVER['REQUEST_URI'];
//            $method = $_SERVER['REQUEST_METHOD'];
//            $HTTP_ACCEPT = $_SERVER['HTTP_ACCEPT'] ?? '';
//            $param = request()->all();
//
//            $sensitive_params = ['allow', 'password', 'repassword', 'fpassword', 'facepassword', 'phoneid', 'pin', 'old_pin', 'fingerprintpay', 'facepay', 'fingerprint'];
//            foreach ($sensitive_params as $v) {
//                if (isset($param[$v])) {
//                    unset($param[$v]);
//                }
//            }
//
//            $param = json_encode($param, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
//            if (!is_array($data)) {
//                $data = (array)$data;
//            }
//            \Log::useDailyFiles(storage_path('logs/allRequestErrorInfo/allRequestErrorInfo.log'));
//            \Log::info('allRequestErrorInfo', ['code'=>$code, 'msg'=>$msg, 'data'=>$data, 'url'=>$url, 'param'=>$param, 'method'=>$method, 'HTTP_ACCEPT'=>$HTTP_ACCEPT]);
//
//        }

//        }
        return $vo;
    }
}
if (!function_exists('create_order_sn')) {
    /**
     * @desc 生成随机订单号
     * @param string $prefix 前缀
     * @return string 32位随机订单号
     */
    function create_order_sn($prefix = 'SH')
    {

        $order_sn = $prefix . date('YmdHis', time()) . mt_rand(10000000, 99999999);
        while (1) {
            $count = \App\Models\Order::select("order_sn")->where('order_sn', $order_sn)->count();
            if ($count == 0) {
                break;
            }
            $order_sn = $prefix . date('YmdHis', time()) . mt_rand(10000000, 99999999);
        }
        return $order_sn;
    }
}

if (!function_exists('create_pos_orderId')) {
    /**
     * @desc 创建pos订单号
     * @return string
     */
    function create_pos_orderId()
    {
        $pos_orderId = 'HT'.date('YmdHis') . mt_rand(1000, 9999) . mt_rand(10, 99);
        while (1) {
            $count = \App\Models\Business\PosOrder::select("pos_orderId")->where('pos_orderId', $pos_orderId)->count();
            if ($count == 0) {
                break;
            }
            $pos_orderId = 'HT'.date('YmdHis') . mt_rand(100000, 999999);
        }
        return $pos_orderId;
    }
}

if (!function_exists('v')) {
    /**
     * @param $msg
     *
     * @return string
     */
    function v($msg)
    {
        //var_dump($msg);;
    }
}

if (!function_exists('curl_get_https')) {
    function curl_get_https($url, $headers = [])
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        if ($headers){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $tmpInfo = curl_exec($curl);     //返回api的json对象
        curl_close($curl);//关闭URL请求
        return $tmpInfo;    //返回json对象
    }
}

if (!function_exists('randomkeys')) {
    /**
     * @param $msg
     *
     * @return string
     */
    function randomkeys($lenth)
    {
        $key = '';
        $patern = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for ($i = 0; $i < $lenth; $i++) {
            $key .= $patern{mt_rand(0, 35)};
        }
        return $key;
    }
}
if (!function_exists('get_distance')) {
    /**
     * @desc 根据两个经纬度计算两地距离
     * @param $lat1 纬度1
     * @param $lng1 经度1
     * @param $lat2 纬度2
     * @param $lng2 经度2
     * @param int $len_type
     * @param int $decimal 1:m（米） or 2:km（千米）
     * @return float 保留几位小数
     */
    function get_distance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2)
    {

        if ($lat1 > 0 && $lng1 > 0 && $lat2 > 0 && $lng2 > 0) {
            $pi = pi(); // 圆周率
            $er = 6378.1369999999997;
            $radLat1 = ($lat1 * $pi) / 180;
            $radLat2 = ($lat2 * $pi) / 180;
            $a = $radLat1 - $radLat2;
            $b = (($lng1 * $pi) / 180) - (($lng2 * $pi) / 180);
            $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + (cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))));
            $s = $s * $er;
            $s = round($s * 1000);
            if (1 < $len_type) {
                $s /= 1000;
            }
        } else {
            $s = 10000;
        }

        return round($s, $decimal);

    }
}


if (!function_exists('excel_export')) {
    /**
     * @desc excel 数据导出
     * @param string $file_name 文件名称
     * @param array $list 要导出的数据，从数据库中查出的二维数据
     * @param array $columns_arr 导出的Excel键值对
     * @param string $type export 导出下载, store 保存到服务器
     * @return mixed
     */
    function excel_export($file_name = '', $list = [], $columns_arr = [], $type = 'export')
    {

        $excel_file_name = $file_name . date('Y-m-d', time());

        // 解决文件名称乱码 https://www.cnblogs.com/angellating/p/6979066.html
        header('Content-Type: application/vnd.ms-excel');
        header('Cache-Control: max-age=0');
        header("Content-Disposition: attachment;filename=$excel_file_name");

        $info = array();
        $row_name_arr = array_column($columns_arr, 'title');
        $export_list = array();
        $row_width = array();
        $columns = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 'BS', 'BT', 'BU', 'BV', 'BW', 'BX', 'BY', 'BZ', 'CA', 'CB', 'CC', 'CD', 'CE', 'CF', 'CG', 'CH', 'CI', 'CJ', 'CK', 'CL', 'CM', 'CN', 'CO', 'CP', 'CQ', 'CR', 'CS', 'CT', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DA', 'DB', 'DC', 'DD', 'DE', 'DF', 'DG', 'DH', 'DI', 'DJ', 'DK', 'DL', 'DM', 'DN', 'DO', 'DP', 'DQ', 'DR', 'DS', 'DT', 'DU', 'DV', 'DW', 'DX', 'DY', 'DZ', 'EA', 'EB', 'EC', 'ED', 'EE', 'EF', 'EG', 'EH', 'EI', 'EJ', 'EK', 'EL', 'EM', 'EN', 'EO', 'EP', 'EQ', 'ER', 'ES', 'ET', 'EU', 'EV', 'EW', 'EX', 'EY', 'EZ');
        if (!empty($list)) {
            foreach ($list as $item) {
                $temp_arr = array();
                foreach ($columns_arr as $col) {
                    $temp_arr[$col['field']] = $item[$col['field']]; // 这是什么鬼
                }
                array_push($export_list, $temp_arr);
            }
        }
        if (!empty($columns_arr)) {
            foreach ($columns_arr as $_key => $_col) {
                $row_width[$columns[$_key]] = $_col['width'];
            }
        }
        if (!empty($export_list)) {
            foreach ($export_list as $v) {
                $info[] = array_values($v); // 去数据库字段键值
            }
        }
        array_unshift($info, $row_name_arr); // 置顶标题

        $len = count($info) + 1; // + 1
        $len_1 = count($row_width); // + 1
        $fw = "A1:$columns[$len_1]$len";
        ob_end_clean();
        ob_start();
        if ($type == 'export') { // 导出
            // iconv('UTF-8', 'gb2312', $excel_file_name)
            Excel::create($excel_file_name, function ($excel) use ($info, $file_name, $row_width, $fw) {
                $excel->sheet('Sheet1', function ($sheet) use ($info, $row_width, $fw) {
                    $sheet->rows($info);
                    $sheet->setWidth($row_width);
                    $sheet->cells($fw, function ($cells) {
                        $cells->setAlignment('center'); // 设置居中 https://blog.csdn.net/qq_35984195/article/details/79243404
                    });
                });
            })->export('xls');
            unset($list);//释放变量的内存
            exit;
        } else {
            // 保存到服务器
            $store = Excel::create($excel_file_name, function ($excel) use ($info, $file_name, $row_width, $fw) {
                $excel->sheet('Sheet1', function ($sheet) use ($info, $row_width, $fw) {
                    $sheet->rows($info);
                    $sheet->setWidth($row_width);
                    $sheet->cells($fw, function ($cells) {
                        $cells->setAlignment('center'); // 设置居中 https://blog.csdn.net/qq_35984195/article/details/79243404
                    });
                });
            })->store('xls', storage_path('app/public/excel/exports'));
            $store = get_object_vars($store);
            $file_name = $store['filename'] . '.' . $store['ext'];
            $store['download_url'] = url('storage/excel/exports/' . $file_name);
            $file = file_get_contents(storage_path('app/public/excel/exports/') . $file_name);
            $store['ftp_upload'] = Storage::disk('ftp')->put('storage/excel/exports/' . $file_name, $file);
            unset($list);//释放变量的内存
            return $store;
        }

    }
}

if (!function_exists('get_commission_charge')) {
    /**
     * @desc 获取商家的手续费率
     * @param int $business_id
     * @param int $current_id
     * @param int $amount 当前订单的金额
     * @return int
     */
    function get_commission_charge($sellerId = 0, $current_id = 0)
    {

        $commission_charge = 0;
        if (empty($sellerId) || empty($current_id)) {
            return $commission_charge;
        }
        // 获取商家的营业额（美金）
        $sales_money = get_usd_sales_money($sellerId);
        $business_vip_charge = \App\Models\BusinessVipCharge::select("min_revenue", "max_revenue", "charge")
            ->where('sellerId', $sellerId)
            ->where('current_id', $current_id)
            ->orderBy('min_revenue', 'asc')
            ->get()
            ->toArray();

        if (!empty($business_vip_charge)) {
            $commission_charge = $business_vip_charge[0]['charge'];
            foreach ($business_vip_charge as $item) {
                if ($sales_money > $item['min_revenue'] && $sales_money <= $item['max_revenue']) {
                    $commission_charge = $item['charge'];
                    break;
                }
            }
        } else {
            $business_charge = \App\Models\BusinessCharge::select("charge")
                ->where('current_id', $current_id)
                ->value('charge');
            if (!empty($business_charge)) {
                $commission_charge = $business_charge;
            }
        }
        return $commission_charge;

    }
}

if (!function_exists('get_usd_sales_money')) {
    /**
     * @desc 获取商家营业额
     * @param int $seller_id 商家id
     * @return string 返回商家营业额，单位：美元
     */
    function get_usd_sales_money($seller_id = 0)
    {

        $usd_sales_money = '0.00';
        $list = \App\Models\BusinessOrder::from("business_order as bo")
            ->select("bo.total as amount", "bo.current_id", "c.rate")
            ->join('currency as c', 'bo.current_id', '=', 'c.current_id')
            ->where('bo.seller_id', $seller_id)
            ->where('bo.is_send', '2')
            ->where('bo.status', 803)
            ->where('bo.is_done', 1)
            ->get()
            ->toArray();
        if ($list) {
            foreach ($list as $item) {
                $usd_sales_money = bcadd($usd_sales_money, bcmul($item['amount'], $item['rate'], 8), 2);
            }
        }
        return $usd_sales_money;

    }
}

if (!function_exists('get_max_withdraw_cash')) {
    /**
     * @desc 得到商家最大可提现金额
     * @param int $balance
     * @param int $current_id
     * @return string
     */
    function get_max_withdraw_cash($sellerId = 0, $current_id = 1001, $amount = 0)
    {

        // (1 + 手续费率)*可提现 == 总
        // https://www.cnblogs.com/phpper/p/7664069.html
        $withdraw_rate = get_commission_charge($sellerId, $current_id);
        // 高精度
        $decimals = \App\Models\UsersWallet::get_decimals($current_id);
        $withdraw_rate = bcadd(1, $withdraw_rate, $decimals);
        // dd($amount, $withdraw_rate, bcdiv($amount, $withdraw_rate, $decimals));
//        dd($amount,$withdraw_rate);
        return bcdiv($amount, $withdraw_rate, $decimals);

    }
}


if (!function_exists('upload_base64_img')) {
    /**
     * @desc 上传base64格式图片
     * @param string $img_data base64格式图片数据    eq: data:image/png;base64,iVBORw0KGgoAA****
     * @param string $path 上传路径    eq: /public/news/2018-11-08
     * @return 上传后地址
     */
    function upload_base64_img($img_data, $path)
    {

        preg_match('/^(data:\s*image\/(\w+);base64,)/', $img_data, $result);
        //图片类型
        $filetype = $result[2];
        $filename = str_random(random_int(20, 20));
        //图片路径
        $filepath = base_path() . '/storage/app' . $path . '/';
        if (!is_dir($filepath)) {
            mkdir(iconv("UTF-8", "GBK", $filepath), 0777, true);
        }
        $filepath = $filepath . $filename . '.' . $filetype;
        //提取base64字符
        $imgdata = substr($img_data, strpos($img_data, ",") + 1);
        $decodedData = base64_decode($imgdata);
        //保存
        file_put_contents($filepath, $decodedData);
        $imgUrl = strchr($filepath, '/');
        $imgUrl = str_replace('/app/public', '', $imgUrl);
        return $imgUrl;
    }
}

if (!function_exists('last_12_months')) {
    /**
     * @desc 获取当前时间的前12个月份
     * @return array
     */
    function last_12_months()
    {

        $z = date('Y-m');
        $a = date('Y-m', strtotime('-11 months'));  // ? 11个月前
        $begin = new DateTime($a);
        $end = new DateTime($z);
        $end = $end->modify('+1 month');
        $interval = new DateInterval('P1M');
        $year_month = new DatePeriod($begin, $interval, $end);
        $month_arr = array();
        if (!empty($year_month)) {
            foreach ($year_month as $key => $date) {
                $month_arr[$key] = $date->format("Y-m");
            }
        }
        return $month_arr;

    }
}

if (!function_exists('get_months')) {
    /**
     * @desc 获取开始时间到结束时间的某几个月份
     * @return array
     */
    function get_months($start_time, $end_time)
    {

        $month_arr = array();
        if (!empty($start_time) && !empty($end_time)) {
            $z = date('Y-m', $end_time);
            $a = date('Y-m', $start_time);
            $begin = new DateTime($a);
            $end = new DateTime($z);
            $end = $end->modify('+1 month');
            $interval = new DateInterval('P1M');
            $year_month = new DatePeriod($begin, $interval, $end);
            $month_arr = array();
            if (!empty($year_month)) {
                foreach ($year_month as $key => $date) {
                    $month_arr[$key] = $date->format("Y-m");
                }
            }
        }
        return $month_arr;

    }
}


if (!function_exists('is_mobile')) {
    /**
     * @desc 验证手机格式是否正确
     * @param string $mobile
     * @param string $area_code
     * @return bool
     */
    function is_mobile($mobile = '', $area_code = '86')
    {
        $len = strlen($mobile);
        if (is_numeric($mobile)) {
            if ($area_code != '86') {
                if ($len >= 4 && $len <= 11) {
                    return true;
                } else {
                    return false;
                }
            } else if ($area_code == '65') {
                if ($len == 8) {
                    return true;
                } else {
                    return false;
                }
            } else {
                if (preg_match("/^1[0123456789]{1}\d{9}$/", $mobile)) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    }

}

//判断是否是商家子用户登录
if (!function_exists('get_business_guard')) {
    /**
     * @return string 返回文件完整名称
     */
    function get_business_guard()
    {
        if (auth('business')->check()) {
            return 'business';
        }
        if (auth('subbusiness')->check()) {
            return 'subbusiness';
        }
        return false;
    }
}

//根据当前日期获取下一周日期数组
if (!function_exists('get_next_week')) {
    /**
     * @return string 返回下一周日期数组
     */
    function get_next_week($date)
    {
        $dates = array();
        $time = strtotime($date . ' 12:00:00');
        $w = date('w', $time);
        if ($w == 0) {
            $nextMonday = 1;
        } else {
            $nextMonday = 7 - $w + 1;
        }
        for ($i = $nextMonday; $i < $nextMonday + 7; $i++) {
            $dates[] = date('Y-m-d', $time + 3600 * 24 * $i);
        }
        return $dates;
    }
}


if (!function_exists('file_get_name')) {
    /**
     * @param $file 要上传的文件
     * @return string 返回文件完整名称
     */
    function file_get_name($file)
    {
        $filename = $file->getClientOriginalName();
        $ext = explode('.', $filename);
        $filename = \Illuminate\Support\Str::random(40) . '.' . $ext[1];
        return $filename;
    }
}

if (!function_exists('get_business_guard')) {
    /**
     * @return string 返回文件完整名称
     */
    function get_business_guard()
    {
        if (auth('business')->check()) {
            return 'business';
        }
        if (auth('subbusiness')->check()) {
            return 'subbusiness';
        }
        return false;
    }
}

if (!function_exists('get_member_guard')) {
    /**
     * @return string 返回文件完整名称
     */
    function get_member_guard()
    {
        if (auth('member')->check()) {
            return 'member';
        }
        return false;
    }
}


if (!function_exists('curl_post')) {
    /**
     * @param $url
     * @param $data
     * @param $headers
     * @return mixed
     */
    function curl_post($url, $data, $headers = array())
    {
        $curl = curl_init();
        //curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:'));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_ENCODING, 0);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, '0');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, '0');
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}

if (!function_exists('check_pay_password')) {
    /**
     * @desc 判断用户支付密码
     * @param null $user 用户信息
     * @param int $pwd_type 支付密码类型 当pwd_type==1时，传支付密码，传2时传指纹密码，3时传人脸
     * @param array $pay_pwd 支付密码
     * @return array
     */
    function check_pay_password($user, $pwd_type = 1, $pay_pwd = [])
    {
//$arrinfo = ['user'=>$user,'pwd_type'=>$pwd_type,'pay_pwd'=>$pay_pwd];
//        Log::useFiles(storage_path('check_pay_password.log'));
//        Log::info('check_pay_password', $arrinfo);
        $data = array(
            'code' => 200,
            'msg' => ''
        );
        $lock_num = 5;

        if (empty($user)) {
            $data = array(
                'code' => 403,
                'msg' => trans('app.paymentPasswordNotSet')
            );
        } else {
            // 支付密码
            if ($pwd_type == 1) {
                // 判断用户是否设置了支付密码
                if (empty($user->pin)) {
                    $data = array(
                        'code' => 411,
                        'msg' => trans('app.paymentPasswordNotSet')
                    );
                } else if (strlen($pay_pwd['pin']) < 30) {
                    $data = array(
                        'code' => 201,
                        'msg' => trans('app.paymentPasswordIsError')
                    );
                } else if (empty($pay_pwd['pin'])) {
                    $data = array(
                        'code' => 402,
                        'msg' => trans('app.payPasswordMustBeOne')
                    );
                } else if (!Hash::check(think_md5($pay_pwd['pin']), $user->pin)) {
                    if ($user->pin_error < $lock_num) {
                        if ($user->pin_error == $lock_num - 1) {
                            //支付密码连续输错n次，需要关闭指纹支付和人脸支付
                            \App\Models\User::where('id', $user->id)->update([
                                'pin_error' => $lock_num,
                                'fingerprint_pay_status' => 1,
                                'face_pay_status' => 1,
                                'finger_pay_error' => 0,
                                'face_pay_error' => 0,
                            ]);
                            $data = array(
                                'code' => 9990,
                                'msg' => trans('app.mostPinError')
                            );
                        } else {
                            \App\Models\User::where('id', $user->id)->increment('pin_error', 1);
                            $data = array(
                                'code' => 9900,
                                'msg' => trans('app.ifMostPinError')
                            );
                        }
                    } else {
                        $data = array(
                            'code' => 403,
                            'msg' => trans('app.mostPinError')
                        );
                    }
                }else if (Hash::check(think_md5($pay_pwd['pin']), $user->pin) && $user->pin_error>=$lock_num) {
                    $data = array(
                        'code' => 201,
                        'msg' => trans('app.mostPinError')
                    );
                }
            } elseif ($pwd_type == 2) {
                // 判断用户是否设置了指纹密码
                if (empty($user->fingerprintpay)) {
                    $data = array(
                        'code' => 412,
                        'msg' => trans('app.fingerprintPayPasswordNotSet')
                    );
                } elseif ($user->fingerprint_pay_status != 2) {
                    $data = array(
                        'code' => 416,
                        'msg' => trans('app.fingerprintPayNotEnable')
                    );
                } elseif (empty($pay_pwd['fingerprint'])) {
                    $data = array(
                        'code' => 402,
                        'msg' => trans('app.payPasswordMustBeOne')
                    );
                } else if (!Hash::check(think_md5($pay_pwd['fingerprint']), $user->fingerprintpay) || $user->fingerprint_id !== $pay_pwd['identify']) {
                    $update['finger_pay_error'] = ++$user->finger_pay_error;
                    if ($update['finger_pay_error'] >= $lock_num) {//指纹验证失败次数达到$lock_num次，关闭指纹支付，指纹支付错误次数清零
                        $update['fingerprint_pay_status'] = 1;
                        $update['finger_pay_error'] = 0;
                        $data = array(
                            'code' => 9990,
                            'msg' => trans('app.mostFingerPayError')
                        );
                    }else {
                        $data = array(
                            'code' => 201,
                            'msg' => trans('app.fingerprintPasswordIsError')
                        );
                    }
                    \App\Models\User::where('id', $user->id)->update($update);
                }
            } else {
                // 判断用户是否设置了人脸识别
                if (empty($user->facepay)) {
                    $data = array(
                        'code' => 413,
                        'msg' => trans('app.faceLoginNotSet')
                    );
                } elseif ($user->face_pay_status != 2) {
                    $data = array(
                        'code' => 417,
                        'msg' => trans('app.facePayNotEnable')
                    );
                }elseif (empty($pay_pwd['facepay'])) {
                    $data = array(
                        'code' => 402,
                        'msg' => trans('app.payPasswordMustBeOne')
                    );
                } else if (!Hash::check(think_md5($pay_pwd['facepay']), $user->facepay) || $user->fingerprint_id !== $pay_pwd['identify']) {
                    $update['face_pay_error'] = ++$user->face_pay_error;
                    if ($update['face_pay_error'] >= $lock_num) {//人脸验证失败次数达到$lock_num次，关闭人脸支付，人脸支付错误次数清零
                        $update['face_pay_status'] = 1;
                        $update['face_pay_error'] = 0;
                        $data = array(
                            'code' => 9990,
                            'msg' => trans('app.mostFacePayError')
                        );
                    }else {
                        $data = array(
                            'code' => 201,
                            'msg' => trans('app.faceLoginIsError')
                        );
                    }
                    \App\Models\User::where('id', $user->id)->update($update);
                }
            }
        }

        if ($data['code'] == 200) {
            switch ($pwd_type) {
                //支付密码输入正确后，指纹/人脸错误次数需要清零
                case 1:
                    if (($user->pin_error > 0 || $user->finger_pay_error > 0 || $user->face_pay_error > 0) && $user->pin_error < $lock_num) {
                        \App\Models\User::where('id', $user->id)->update(['pin_error' => 0, 'finger_pay_error' => 0, 'face_pay_error' => 0]);
                    }
                    break;
                //其他的支付验证方式验证通过后只会清掉当前支付验证方式的错误次数，不会改变其它的。
                case 2:
                    if ($user->finger_pay_error > 0 && $user->finger_pay_error < $lock_num) {
                        \App\Models\User::where('id', $user->id)->update(['finger_pay_error' => 0]);
                    }
                    break;
                case 3:
                    if ($user->face_pay_error > 0 && $user->face_pay_error < $lock_num) {
                        \App\Models\User::where('id', $user->id)->update(['face_pay_error' => 0]);
                    }
                    break;
            }
        }
        return $data;
    }
}

if (!function_exists('debug_log')) {
    function debug_log($path, $content)
    {
        file_put_contents(storage_path($path), '[' . date('Y-m-d H:i:s') . '] ' . $content . PHP_EOL, FILE_APPEND);
    }
}


if (!function_exists('check_amount')) {
    /**
     * @desc 判断数字金额是否满足条件
     * @param int $amount
     * @param int $current_id
     * @return 数组
     */
    function check_amount($amount = 0, $current_id = 0, $category = 'send')
    {
        if (!is_numeric($amount) || $amount <= 0) {
            return response_json(402, trans('app.mustEnterPositiveNumbers'));
        }
//        elseif ($amount > 1000){
//            return response_json(402, trans('app.amountIsTooLarge'));
//        }

        $decimals = \App\Models\UsersWallet::get_decimals($current_id);
        if ($category == 'send') {
            $min_amount = \App\Models\UsersWallet::get_min_amount($current_id);
            if (bccomp($amount, $min_amount, $decimals) < 0) {
                return response_json(402, trans('app.amountMustBeGreaterThanOrEqual') .' '. $min_amount);
            }
        }

        if (strpos($amount, '.') !== false) {
            $number_arr = explode('.', $amount);
            if (isset($number_arr[1]) && !empty($number_arr[1])) {
                if (strlen($number_arr[1]) > 8) {
                    return response_json(402, trans('app.doNotExceedEightDecimal'));
                }
            }
        }
        return response_json(200, 'success');
    }
}

if (!function_exists('check_user_mobile_email')) {
    /**
     * @desc 在 转账、购买这两个 接口之前，要限制用户设置支付密码和邮箱认证、手机号码认证，才可以转账、购买
     * @param $user 用户信息
     * @return 数组
     */
    function check_user_mobile_email($user)
    {
        if (empty($user)) {
            return response_json(403, trans('app.userNotFound'));
        } elseif ($user->phone_status != 2) {
            return response_json(409, trans('app.phoneNotVerify'));
        } elseif ($user->email_status != 2) {
            return response_json(410, trans('app.emailNotVerify'));
        } else {
            return response_json(200, 'success');
        }
    }
}

if (!function_exists('replace_img_path')) {
    function replace_img_path($str)
    {
        if ($str) {
            return strpos($str, 'http://') === 0 ? url($str) : $str;
        }
        return '';
    }
}
if (!function_exists('think_md5')) {
    function think_md5($str, $key = 'Think')
    {
        return '' === $str ? '' : md5(sha1($str) . $key);
    }
}

if (!function_exists('convert_url_query')) {
    /**
     * @desc 解析url并得到url中的参数
     * @param string $url
     * @return array
     */
    function convert_url_query($url = '')
    {
        $arr = parse_url($url);
        $params = array();
        if (!empty($arr['query'])) {
            $queryParts = explode('&', $arr['query']);
            foreach ($queryParts as $param) {
                $item = explode('=', $param);
                $params[$item[0]] = $item[1];
            }
        }
        return $params;
    }
}

if (!function_exists('get_longitude_and_latitude')) {
    /**
     * @desc 通过ip获取经纬度
     * @param string $ip
     * @return array
     * @throws Exception
     */
    function get_longitude_and_latitude($ip = '', $user)
    {

        $result = array(
            'longitude' => $user->last_lng ?: '114.116215',
            'latitude' => $user->last_lat ?: '22.543419'
        );
        if (empty($user->last_lng) && empty($user->last_lat)) {
            try {
                $url = "http://ip-api.com/json/$ip?fields=status,lat,lon";
                $str = file_get_contents($url);
                $data = json_decode($str, true);
                if ($data['status'] == 'success') {
                    $result = array(
                        'longitude' => $data['lon'],
                        'latitude' => $data['lat']
                    );
                }
            } catch (\Exception $exception) {
                Log::useFiles(storage_path('getLongitudeAndLatitude.log'));
                Log::info('ip:' . $ip . ',input:' . json_encode($user, JSON_UNESCAPED_UNICODE) . ',message:' . $exception->getMessage());
            }
        }
        return $result;

    }
}

if (!function_exists('convert_arr_key')) {
    /**
     * @param $arr
     * @param $key_name
     * @return array
     * 将数据库中查出的列表以指定的 id 作为数组的键名
     */
    function convert_arr_key($array, $key_name, $key_name_two)
    {

        $re = array();
        $arr = array();
        if ($array) {
            foreach ($array as $a) {
                $tmp_v = $a;
                unset($tmp_v[$key_name]);
                if (isset($re[$a[$key_name]])) {
                    $re[$a[$key_name]][] = $tmp_v;
                } else {
                    $re[$a[$key_name]] = array($tmp_v);
                }
            }
            foreach ($re as $key => $val) {
                $arr[] = array(
                    $key_name => $key,
                    $key_name_two => $val
                );
            }
        }

        return $arr;

    }
}

if (!function_exists('geo_hash_encode')) {
    /**
     * @desc 将一个经纬度信息，转换成一个可以排序，可以比较的字符串编码，用于高效搜索
     * @param string $latitude
     * @param string $longitude
     * @param int $len
     * @return string
     */
    function geo_hash_encode($latitude = '', $longitude = '', $len = 12)
    {

        $geo_hash = '';
        if ($latitude > 0 && $longitude > 0) {
            $latitude = bcadd($latitude, 0, 8);
            $longitude = bcadd($longitude, 0, 8);
            $GeoHash = new \Geohash\GeoHash();
            // https://blog.csdn.net/qq_36373262/article/details/62419390
            // 参数：纬度，经度
            // 决定查询范围，值越大，获取的范围越小
            // 当geohash base32编码长度为8时，精度在19米左右，而当编码长度为9时，精度在2米左右，编码长度需要根据数据情况进行选择。
            $geo_hash = $GeoHash->encode($latitude, $longitude, $len);
        }
        return $geo_hash;
    }
}


if (!function_exists('geo_hash_decode')) {
    /**
     * @desc 将geo_hash转成可用的经纬度信息
     * @param $geo_hash
     * @return array
     */
    function geo_hash_decode($geo_hash)
    {
        $GeoHash = new \Geohash\GeoHash();
        $geo_hash_arr = $GeoHash->decode($geo_hash);
        return $geo_hash_arr;
    }
}

if (!function_exists('getLanLat')) {
    /**
     * @desc将中文地址转换为经纬度地址
     * @key_words地址名称
     * @return array
     * */
    function getLanLat($key_words)
    {
        $header[] = 'Referer: http://lbs.qq.com/webservice_v1/guide-suggestion.html';
        $header[] = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36';
        $url = "http://apis.map.qq.com/ws/place/v1/suggestion/?&region=&key=OB4BZ-D4W3U-B7VVO-4PJWW-6TKDJ-WPB77&keyword=" . $key_words;

        $ch = curl_init();
        //设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        //执行并获取HTML文档内容
        $output = curl_exec($ch);
        // print_r($output);die;
        //释放curl句柄
        curl_close($ch);
        // return $output;
        $result = json_decode($output, true);
        return $result;
        //echo json_encode(['error_code'=>'SUCCESS','reason'=>'查询成功','result'=>$result);
    }

}

if (!function_exists('getLanLatByBD')) {
    /**
     * @desc百度地图api将中文地址转换为经纬度地址
     * @key_words地址名称
     * @return array
     * */
    function getLanLatByBD($address)
    {
        //API控制台申请得到的ak（此处ak值仅供验证参考使用）
        $ak = 'hhKlwm3Q80647hUTuHzokF8r7FzOgXIT';
        //应用类型为for server, 请求校验方式为sn校验方式时，系统会自动生成sk，可以在应用配置-设置中选择Security Key显示进行查看（此处sk值仅供验证参考使用）
        $sk = 'V1w0RSkPf3YnRM3KjD5opLV7NQhnGDnB';
        //以Geocoding服务为例，地理编码的请求url，参数待填
        $url = "http://api.map.baidu.com/geocoder/v2/?address=%s&output=%s&ak=%s&sn=%s";
        //get请求uri前缀
        $uri = '/geocoder/v2/';
        //地理编码的请求中address参数
        //$address = '深圳罗湖';
        //地理编码的请求output参数
        $output = 'json';
        //构造请求串数组
        $querystring_arrays = array(
            'address' => $address,
            'output' => $output,
            'ak' => $ak
        );
        $sn = caculateAKSN($ak, $sk, $uri, $querystring_arrays);
        $target = sprintf($url, urlencode($address), $output, $ak, $sn);
        $ch = curl_init();
        //设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        //执行并获取HTML文档内容
        $output = curl_exec($ch);
        // print_r($output);die;
        //释放curl句柄
        curl_close($ch);
        // return $output;
        $result = json_decode($output, true);
        return $result;
    }

}

if (!function_exists('caculateAKSN')) {
    /**
     * @desc获取百度地图api所需sn
     * @return array
     * */
    function caculateAKSN($ak, $sk, $url, $querystring_arrays, $method = 'GET')
    {
        if ($method === 'POST') {
            ksort($querystring_arrays);
        }
        $querystring = http_build_query($querystring_arrays);
        return md5(urlencode($url . '?' . $querystring . $sk));
    }
}

if (!function_exists('last_7_days')) {
    /**
     * @desc 获取当前时间的前7天
     * @return array
     */
    function last_7_days()
    {

        $begin = strtotime(date('Y-m-d', strtotime('-6 days')));  // ? 7天前
        $today_time = strtotime(date('Y-m-d'));  // ? 7天前
        $now_time = time();
        $weeks_arr = array();
        $weeks_arr['date'] = array();
        $weeks_arr['weeks'] = array();
        $weeks_arr['day'] = array();
        $weeks_array = array("日", "一", "二", "三", "四", "五", "六"); // 先定义一个数组
        $day_second = 3600 * 24;
        for ($i = $begin; $i < $now_time; $i = $i + $day_second) {
            if ($i != $today_time) {
                array_push($weeks_arr['date'], $i);
            } else {
                array_push($weeks_arr['date'], $now_time);
            }
            array_push($weeks_arr['weeks'], '星期' . $weeks_array[date('w', $i)]);
            array_push($weeks_arr['day'], date('Y-m-d', $i));
        }
        return $weeks_arr;

    }
}

if (!function_exists('convert_arr')) {
    /**
     * @desc 将特定键值作为数组的键名
     * @param $arr
     * @param $key_name
     * @return array
     */
    function convert_arr($arr, $key_name)
    {
        $arr2 = array();
        if (!empty($arr)) {
            foreach ($arr as $key => $val) {
                $arr2[$val[$key_name]] = $val;
            }
        }
        return $arr2;
    }
}

if (!function_exists('storage_path_restore')) {
    /**
     * 根据storage_path方法后的值还原文件地址
     * @param type $path storage_path方法后的值
     * @param type $isRelative 返回值是否是相对根目录
     * @return type
     */
    function storage_path_restore($path, $isRelative = 0)
    {
        $first = substr($path, 0, 1);
        switch ($first) {
            case '/':
                $tem = str_replace('/storage/', '', $path);

                break;

            case 's':
                $tem = str_replace('storage/', '', $path);

                break;

            default:
                if ($isRelative) {
                    return public_path($path);
                } else {
                    return $path;
                }

                break;
        }
        if ($isRelative) {
            $path_restore = storage_path('app/public/' . $tem);
        } else {
            $path_restore = '/storage/app/public/' . $tem;
        }

        return $path_restore;
    }
}

if (!function_exists('push_notice')) {
    /**
     * @desc 推送长连接
     * @param int $uid
     * @param array $data
     * @param string $app
     * @return mixed
     */
    function push_notice(int $uid, array $data, string $app = \App\Models\Order::HT_APP)
    {
        $push = [
            'method' => 'publish',
            'params' => [
                'channel' => 'app.#' . $uid,
                'data' => $data
            ]
        ];
        // 保存websocket推送数据
        (new \App\Models\Rp\PushNoticeRecord())->savePushNoticeRecord($uid, $data, $app);
        if(IS_FORMAL_HOST){
            if($app == \App\Models\Order::HT_APP){
                return curl_post('http://' . env('WEBSOCKET_IP'), json_encode($push), ['Authorization: apikey ' . env('CWEB'), 'Content-Type: application/json']);
            }else{
                // rapidz的推送
                $api_url = config('rp.RP_REQUEST_HOST').config('rp.PUSH_NOTICE');
                $api_data = array(
                    'uid' => $uid,
                    'data' => $data
                );
                $api_param = array(
                    'encryptString' => encrypt_openssl(json_encode($api_data)),
                    'form_token' => str_random(64)
                );
                $api_res = curl_post($api_url, $api_param);
                DB::table('task')->insert(['task_message'=>'push_notice'.date('Y-m-d H:i:s').json_encode($api_res)]);
                return $api_res;
            }
        }
    }
}

if (!function_exists('eth_hexdec')) {
    /**
     * @desc 把从ETH获取过来的金额转成十进制小数
     * @param $amount
     * @return mixed|string
     */
    function eth_hexdec($amount)
    {
        $amount = number_format(hexdec($amount), 8, '.', '');
        $amount = bcdiv($amount, "1000000000000000000", 8);
        return $amount;
    }
}


if (!function_exists('eth_fee')) {
    /**
     * @desc 计算ETH的fee手续费
     * @param $amount
     * @return mixed|string
     */
    function eth_fee($gas, $gasPrice)
    {
        $gas = number_format(hexdec($gas), 8, '.', '');
        $gasPrice = number_format(hexdec($gasPrice), 8, '.', '');
        $amount = bcmul($gas, $gasPrice);
        $amount = bcdiv($amount, "1000000000000000000", 8);
        return $amount;
    }
}

if (!function_exists('getNextMonthDays')) {
    /**
     * @desc 获取下个月的今天
     * @param $date
     * */
    function getNextMonthDays($date)
    {
        $firstday = date('Y-m-01', strtotime($date));
        $lastday = strtotime("$firstday +2 month -1 day");
        $day_lastday = date('d', $lastday); //获取下个月份的最后一天
        $day_benlastday = date('d', strtotime("$firstday +1 month -1 day")); //获取本月份的最后一天

        //获取当天日期
        $Same_day = date('d', strtotime($date));
        //判断当天是否是最后一天   或 下月最后一天 等于 本月的最后一天
        if ($Same_day == $day_benlastday || $day_lastday == $Same_day) {
            $day = $day_lastday;
        } else {
            $day = $Same_day;
        }
        $day = date('Y', $lastday) . '-' . date('m', $lastday) . '-' . $day;

        return $day;
    }
}

//向上取
if (!function_exists('round_up')) {
    /**
     * @param $amount
     * @param int $decimal
     * @return float|int
     */
    function round_up($amount, $decimal = 0)
    {
        if ($decimal < 0) {
            $decimal = 0;
        }
        $num = pow(10, $decimal);
        return ceil($amount * $num) / $num;
    }
}

//向下取
if (!function_exists('round_down')) {
    /**
     * @param $amount
     * @param int $decimal
     * @return float|int
     */
    function round_down($amount, $decimal = 0)
    {
        if ($decimal < 0) {
            $decimal = 0;
        }
        $num = pow(10, $decimal);
        return floor($amount * $num) / $num;
    }
}

/**
 * @desc 二维数组根据字段进行排序
 * @params array $array 需要排序的二维数组
 * @params string $field 排序的字段
 * @params string $sort 排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
 */
function arraySequence($array, $field, $sort = 'SORT_DESC')
{
    $arrSort = array();
    if (!empty($array)) {
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
    }
    return $array;
}

if (!function_exists('is_url')) {
    /**
     * @desc 正则判断一个url是否为url
     * @param $url 网站地址
     * @return bool
     */
    function is_url($url)
    {
        $pattern = "#(http|https)://(.*\.)?.*\..*#i";
        if (preg_match($pattern, $url) && @file_get_contents($url)) {
            return true;
        } else {
            return false;
        }
    }
}


if (!function_exists('get_friend_date')) {
    /**
     * @desc 友好时间显示
     * @param $time 时间戳
     * @param string $lang 语言包
     * @return bool|string
     */
    function get_friend_date($time, $lang = 'cn')
    {
        if (!$time) {
            return '';
        }
        if ($lang == 'cn') {
            $f_date = date('Y年m月d日 H:i:s', $time);
        } else {
            $f_date = date('Y-m-d H:i:s', $time);
        }
        return $f_date;
    }
}


if (!function_exists('upload_img_file')) {
    /**
     * @desc 上传文件
     * @param $file
     * @param 是否压缩, 0 不压缩, 1 压缩
     * @return bool|string
     */
    function upload_img_file($file, $is_thumb = 0, $ext = 'jpg', $type = 0)
    {
        $filename = '';
        if (empty($type)) {
            if ($file->isValid()) {
                //原文件名
                $originalName = $file->getClientOriginalName();
                //扩展名
                $ext = $file->getClientOriginalExtension() ?: $ext;
                //MimeType
                $type = $file->getClientMimeType();
                //临时绝对路径
                $realPath = $file->getRealPath();
                if (empty($is_thumb)) {
                    $filename = date('Ymd') . '/' . uniqid() . '.' . $ext;
                    $jpg = file_get_contents($realPath);
                } else {
                    $filename = date('Ymd') . '/' . uniqid() . '_thumb.' . $ext;
                    $jpg = \Intervention\Image\Facades\Image::make($file)->encode('jpg', 50);    // 后面的参数1 - 100是图片质量
                }
                $bool = Storage::disk('public')->put($filename, $jpg);    //保存图片
            } else {
                $bool = false;
            }
        }
        else {
            $filename = date('Ymd') . '/' . uniqid() . '.' . $ext;
            $bool = Storage::disk('public')->put($filename, $file);    //保存图片
        }
        //判断是否上传成功
        if ($bool) {
            return 'storage/' . $filename;
        } else {
            return '';
        }
    }
}

if (!function_exists('get_number')) {
    /**
     * @param $number
     * @param $lang
     * @return string
     */
    function get_number($number, $lang)
    {

        if ($lang == 'cn') {
            $unit = '万+';
        } else {
            $unit = 'W+';
        }
        return $number >= 10000 ? bcdiv($number, 10000, 2) . $unit : $number;

    }
}

if (!function_exists('get_week_day')) {
    /**
     * @desc 获取星期几的信息
     * @param $timestamp 时间戳
     * @param string $lang 语言
     * @return mixed
     */
    function get_week_day($timestamp, $lang = 'cn')
    {

        if ($lang == 'cn') {
            $week_array = array("星期日", "星期一", "星期二", "星期三", "星期四", "星期五", "星期六");
            return $week_array[date("w", $timestamp)];
        } else {
            return date("l"); // date("l") 可以获取英文的星期比如Sunday
        }

    }
}


if (!function_exists('create_dir')) {
    /**
     * @desc 创建多级目录
     * @param string $path 路径
     * @param string $mode 属性
     * @return    string    如果已经存在则返回true，否则为 false
     */
    function create_dir($path, $mode = 0777)
    {

        if (is_dir($path)) {
            return true;
        }
        $temp = explode('/', $path);
        $cur_dir = '';
        $max = count($temp) - 1;
        if($max > 0){
            for ($i = 0; $i < $max; $i++) {
                $cur_dir .= $temp[$i] . '/';
                if (@is_dir($cur_dir)) {
                    continue;
                }
                @mkdir($cur_dir, $mode, true);
                @chmod($cur_dir, $mode);
            }
        }
        return is_dir($path);

    }
}

if (!function_exists('create_html')) {
    /**
     * @desc 生成html文件
     * @param string $html_string
     * @param string $filename
     * @return array
     */
    function create_html($html_string = '', $filename = '')
    {

        $ymd = date('Ymd');
        $path = storage_path('app/public/html') . '/' . $ymd . '/';
        if (!is_dir($path)) {
            create_dir($path);
        }
        if(empty($filename)){
            $filename = uniqid().mt_rand(1000, 9999);
        }

        $put = file_put_contents($path . "$filename.html", $html_string);
        if(!empty($put)){
            $html_url =  "storage/html/" . $ymd . "/$filename.html";
            return array(
                'path_url' => $path. "$filename.html",
                'html_url' => $html_url
            );
        }else{
            return array();
        }

    }
}

if (!function_exists('images_path_to_array')) {
    /**
     * @desc 将图片路径信息字符串转换成数组，并为数组的每个元素加上域名
     * @param string $imgs_path 图片路径信息字符串
     * @return array
     */
    function images_path_to_array($imgs_path, $split_str = ',')
    {
        if (!is_string($imgs_path) || empty($imgs_path)) return [];
        $imgs = [];
        foreach (explode($split_str, $imgs_path) as $v) {
            $imgs[] = url($v);
        }
        return $imgs;
    }
}


if (!function_exists('get_keyword_mean')) {
    /**
     * @desc 提取关键词的含义：链系号/邮箱号/群号/社区号
     * @param string $keyword 关键字
     * @return bool|string
     */
    function get_keyword_mean($keyword)
    {
        if (preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/', $keyword)) {
            return 'email';
        } elseif (preg_match('/^[\d]{8}$/', $keyword)) {
            return 'userIdOrGroupNo';
        } elseif (preg_match('/^[\d]{6}$/', $keyword)) {
            return 'userIdOrCommunityNo';
        } elseif (preg_match('/^[\d]+$/', $keyword)) {
            return 'userId';
        } else {
            return false;
        }
    }
}



if (!function_exists('get_lang_month')) {
    /**
     * @desc 获取月份
     * @param $timestamp 时间戳
     * @param string $lang cn 中文, en 英语
     * @return string
     */
    function get_lang_month($timestamp, $lang = 'cn'){

        if($lang == 'cn'){
            $month_arr = array(
                '1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'
            );
        }else{
            $month_arr = array(
                'Jan.','Feb.','Mar.','Apr.','May.','Jun.','Jul.','Aug.','Sept.','Oct.','Nov.','Dec.'
            );
        }
        $month = date('n', $timestamp);
        return $month_arr[$month-1];

    }

}

if (!function_exists('set_time_con')) {
    /**
     * @desc 设置日期查询条件
     * @param object $query 查询模型
     * @param string $start_date 开始日期
     * @param string $end_date 截止日期
     * @param string $con_field 条件字段
     * @return void
     */
    function set_time_con(&$query, $start_date, $end_date, $con_field = 'created_at'){
        $end_date and $end_date = substr($end_date,0, 10) . ' 23:59:59';
        if ($start_date && $end_date){
            $query->whereBetween($con_field, [$start_date, $end_date]);
        }elseif ($start_date){
            $query->where($con_field, '>=', $start_date);
        }elseif ($end_date){
            $query->where($con_field, '<=', $end_date);
        }
    }
}

if (!function_exists('filter_url_host')) {
    /**
     * @desc 过滤url地址中的host域名
     * @param string $url url地址
     * @return string
     */
    function filter_url_host($url){
        return ltrim(parse_url((string)$url)['path'], '/');
    }
}

if (!function_exists('get_display_name')) {
    /**
     * @desc 获取用户显示昵称（优先级：好友备注昵称 > 用户在群或社区的昵称 > 用户昵称）
     * @param string $friend_remark 好友备注昵称
     * @param string $nickname 用户在群或社区的昵称
     * @param string $user_name 用户昵称
     * @param string $default_name 默认姓名
     * @return string
     */
    function get_display_name($friend_remark = '', $nickname = '', $user_name = '', $default_name = ''){
        return empty($friend_remark) ? (empty($nickname) ? (empty($user_name) ? $default_name : $user_name) : $nickname) : $friend_remark;
    }
}

if (!function_exists('trim_script')) {
    /**
     * @desc 过滤字符串中的js标签
     * @param string $str
     * @return string
     */
    function trim_script($str){
        return preg_replace('/<[\\\\\/]?script[\\\\\/]?>/i', '', (string)$str);
    }
}

//    无限级分类
if (!function_exists('classify')) {
    function classify($arr, $id, $level)
    {
        $list = [];
        foreach ($arr as $k => $v) {
            if ($v['pid'] == $id) {
                $v['level'] = $level;
                $v['children'] = classify($arr, $v['id'], $level + 1);
                $list[] = $v;
            }
        }
        return $list;
    }
}


/**
 * @desc 科学计数格式化数字
 * @param int $number 要转换的数字
 * @param int $decimals 保留小数点位数
 * @param int $comma 是否保存逗号, 0 否， 1 是
 * @return int|string
 */
if (!function_exists('bc_number_format')) {
    function bc_number_format($number = 0, $decimals = 8, $comma = 0){
        if(empty($comma)){
            $number = number_format($number, $decimals, '.', '');
        }else{
            $number = number_format($number, $decimals);
        }
        return $number;
    }
}

if (!function_exists('encrypt_openssl')) {
    /**
     * @param $str 要加密的字符串
     * @return string
     */
    function encrypt_openssl($str = '', $aes_key = '')
    {
        if(empty($aes_key)){
            $aes_key = config('rp.RP_KEY');
        }
        return openssl_encrypt($str, 'AES-256-CBC', $aes_key, 0, md5($aes_key, 16));
    }
}

if (!function_exists('decrypt_openssl')) {
    /**
     * @param $encrypt 要解密的字符串
     * @return string
     */
    function decrypt_openssl($encrypt = '', $aes_key = '')
    {
        if(empty($aes_key)){
            $aes_key = config('rp.RP_KEY');
        }
        return openssl_decrypt(base64_decode($encrypt), 'AES-256-CBC', $aes_key , 1, md5($aes_key, 16));
    }
}

if (!function_exists('formate_date')) {
    /**
     * @desc 获取自定义的日期格式
     * @return string
     */
    function formate_date($time, $limit_str = ' ', $set_strtotime = true){
        $set_strtotime and $time = strtotime($time);
        return date("m/d/Y{$limit_str}h:iA", $time);

    }
}

if (!function_exists('check_decipher')) {
    /**
     * @desc 加密指纹人脸密钥
     * @return string
     */
    function check_decipher($fpassword){
        return bcrypt(think_md5($fpassword));
    }
}


if (!function_exists('get_other_app_time')) {
    /**
     * @desc 获取其他app服务器的时间，如rapidz, 解决服务器时间不一致问题
     * @param $timestamp 时间戳
     * @param string $app 哪个app, 如 RapidzApp
     * @return string
     */
    function get_other_app_time($timestamp, $app = \App\Models\Order::RP_APP){

        $time_string = '';
        // 示例： return Carbon::createFromTimestamp(strtotime($value))->timezone(Config::get('app.timezone'))->toDateTimeString(); //remove this one if u want to return Carbon object
        if($app == \App\Models\Order::RP_APP){
            $time_string = \Carbon\Carbon::createFromTimestamp($timestamp)
                ->timezone(config('rp.RP_TIMEZONE'))
                ->toDateTimeString(); //remove this one if u want to return Carbon object
        }else{
            $time_string = date('Y-m-d H:i:s');
        }

        return $time_string;

    }
}

if (!function_exists('push_notifications')) {
    /**
     * @desc 谷歌push推送 https://pusher.com/docs/beams/getting-started/android/publish-notifications
     * @param $interests 推送给哪个用户id
     * @param string $interests 推送类型
     * @param array $notification 推送的内容
     * @return mixed
     */
    function push_notifications($interests = '', $notification = array(), $system = 'IOS'){

        $beamsClient = new \Pusher\PushNotifications\PushNotifications(array(
            "instanceId" => config('api.PUSH_NOTIFICATIONS_INSTANCE_ID'),
            "secretKey" => config('api.PUSH_NOTIFICATIONS_SECRET_KEY'),
        ));
        $interests = (string)$interests;
        if($system != 'IOS'){
            $publishResponse = $beamsClient->publishToInterests(
                array($interests),
                array(
                    "fcm" => array(
                        "notification" => $notification,
                        "data" => array(
                            "inAppNotificationMessage" => $notification['body'] ?? trans('app.defaultPushMessage')       // This data can be used in your activity when a notification arrives, with your app in the foreground.
                        )
                    ),
                )
            );
        }else{
            $publishResponse = $beamsClient->publishToInterests(
                array($interests),
                array(
                    "apns" => array(
                        "aps" => array(
                            "alert" => $notification,
                            "data" => array(
                                "inAppNotificationMessage" => $notification['body'] ?? trans('app.defaultPushMessage')       // This data can be used in your activity when a notification arrives, with your app in the foreground.
                            )
                        )
                    ),
                )
            );
        }

        return $publishResponse;

    }

    /**
     * 登录token 加密算法
     */
    if (!function_exists('encryptionToken')) {

    }
        function encryptionToken($uid,$mirotime){

           $info = md5("_(8)./a6pi4354" . $mirotime . "53454#$&G43514" . $uid . "989883467a5@F0lH");

            return $info;

        }

    if (!function_exists('captcha_src')) {
        /**
         * @param string $config
         * @return string
         */
        function captcha_src(string $config = 'default'): string
        {
            return app('captcha')->src($config);
        }
    }

    if (!function_exists('captcha_img')) {

        /**
         * @param string $config
         * @return string
         */
        function captcha_img(string $config = 'default'): string
        {
            return app('captcha')->img($config);
        }
    }
}
