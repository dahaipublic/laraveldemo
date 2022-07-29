<?php

namespace App\Http\Controllers\Api;

use App\Jobs\UpdateBalance;
use App\Libs\PHPmailer\PHPMailer;
use App\Libs\Security;
use App\Models\Business;
use App\Models\BusinessWallet;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UsersWallet;
use App\Models\BusinessCharge;
use App\Http\Controllers\Controller;
use Illuminate\Mail\Mailer;


class TestController extends Controller
{
    protected $UsersWallet;
    protected $current_id;
    protected $mail;

    private $allow;

    public function __construct(Request $request)
    {
        $this->allow = $request->input('allow', 'false');
        $this->check_allow();

        $this->current_id = $request->input('current_id');
        $seller = $request->input('seller')?:'';
        if($this->current_id){
            $this->UsersWallet = new UsersWallet($this->current_id, $seller);
        }


//        $this->mail = $request->all();
//        $this->mail['code']= mt_rand(1000000, 9999999);
//        $this->mail['content']= '测试';
//        dd($this->mail['email']);
    }

    private function check_allow()
    {
        if($this->allow != '百度一下你就知道了'){
            die('非法操作!');
        }
        return;
    }

    public function test_security()
    {
       $security =  Security::get_instance();
       $data = dump($security->encrypt('我的世界里只有你'));
       dump($security->decrypt($data));
    }

    public function get_new_address(Request $request)
    {
        $res = $this->UsersWallet->create_new_address($request->uid);
        return response_json(200, $res);
    }
    //获取用户余额
    public function test_001(Request $request)
    {
        return response_json(201, 'huoqu budao yanzmsdfa',['result'=>['aaa'=>'bbb']]);
//        $my_class = new BusinessCharge();
//        $my_func = $my_class->getBusinessCharge($request->seller_id, $request->current_id);
//        dump($my_func);
//        $res = $my_class->$my_func();
//        return response_json(200, ['result'=>$res]);
    }

    public function move_to(Request $request)
    {
        if($request->amount >= 10){
            dd('金额错误！');
        }
        $res = $this->UsersWallet->move($request->uid, $request->to_uid, $request->amount);
        return response_json(200, $res);
    }

    public function update_business_balance(Request $request)
    {
        $BusinessWallet = new BusinessWallet();
        $res = $BusinessWallet->update_business_balance($request->sellerId, $request->current_id);
        return response_json(200, $res);
    }

    public function test_move(Request $request)
    {
        if($request->amount >= 10){
            dd('金额错误！');
        }
        $res = $this->UsersWallet->testMove($request->uid, $request->to_uid, $request->amount);
        return response_json(200, $res);
    }

    public function move_to_company(Request $request)
    {
        $amount = (double)$request->post('amount');
        if($amount >= 100){
            dd('金额错误！');
        }
        $res = $this->UsersWallet->moveToCompany($request->post('id', ''), $amount);
        return response_json(200, $res);
    }

    public function send_email_ems()
    {
//        dd(1);
        //引入PHPMailer的核心文件 使用require_once包含避免出现PHPMailer类重复定义的警告
//        require_once "class.phpmailer.php";
        //示例化PHPMailer核心类
                $mail = new PHPMailer();

        //是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
                $mail->SMTPDebug = 1;
        // 'RAPIDZPAY'=>[
        //        "SEND_FROM" => 'Rapidzpay',
        //        "SMTP_DEBUG" => True,
        //        "USER_NAME" => 'no-reply@rapidzpay.io',
        //        "PASS_WORD" => 'Rapidz000',
        //        "SMTP_HOST" => 'mail.rapidzpay.io',
        //        "SMTP_PORT" => '587',
        //    ],
        //使用smtp鉴权方式发送邮件，当然你可以选择pop方式 sendmail方式等 本文不做详解
        //可以参考http://phpmailer.github.io/PHPMailer/当中的详细介绍
                $mail->isSMTP();
        //smtp需要鉴权 这个必须是true
                $mail->SMTPAuth=true;
        //链接qq域名邮箱的服务器地址
                $mail->Host = 'mail.rapidzpay.io';
        //设置使用ssl加密方式登录鉴权
                $mail->SMTPSecure = 'tls';
        //设置ssl连接smtp服务器的远程服务器端口号 可选465或587
                $mail->Port = '587';
        //设置smtp的helo消息头 这个可有可无 内容任意
                $mail->Helo = 'Hello smtp.sendgrid.net Server';
        //设置发件人的主机域 可有可无 默认为localhost 内容任意，建议使用你的域名
                $mail->Hostname = 'localhost';
        //设置发送的邮件的编码 可选GB2312 我喜欢utf-8 据说utf8在某些客户端收信下会乱码
                $mail->CharSet = 'UTF-8';
        //设置发件人姓名（昵称） 任意内容，显示在收件人邮件的发件人邮箱地址前的发件人姓名
                $mail->FromName = 'no-reply@rapidzpay.io';
        //smtp登录的账号 这里填入字符串格式的qq号即可
                $mail->Username ='no-reply@rapidzpay.io';
        //smtp登录的密码 这里填入“独立密码” 若为设置“独立密码”则填入登录qq的密码 建议设置“独立密码”
                $mail->Password = 'Rapidz000';
        //设置发件人邮箱地址 这里填入上述提到的“发件人邮箱”
                $mail->From = 'no-reply@rapidzpay.io';
        //邮件正文是否为html编码 注意此处是一个方法 不再是属性 true或false
                $mail->isHTML(true);
        //设置收件人邮箱地址 该方法有两个参数 第一个参数为收件人邮箱地址 第二参数为给该地址设置的昵称 不同的邮箱系统会自动进行处理变动 这里第二个参数的意义不大
                $mail->addAddress('sassywen@yahoo.com.tw');
        //添加多个收件人 则多次调用方法即可
        // $mail->addAddress('xxx@163.com','晶晶在线用户');
        //添加该邮件的主题
                $mail->Subject = 'sendgrid发送邮件的示例';
        //添加邮件正文 上方将isHTML设置成了true，则可以是完整的html字符串 如：使用file_get_contents函数读取本地的html文件
                $mail->Body = "这是一个发送邮件的一个测试用例";
        //为该邮件添加附件 该方法也有两个参数 第一个参数为附件存放的目录（相对目录、或绝对目录均可） 第二参数为在邮件附件中该附件的名称
        // $mail->addAttachment('./d.jpg','mm.jpg');
        //同样该方法可以多次调用 上传多个附件
        // $mail->addAttachment('./Jlib-1.1.0.js','Jlib.js');
        //解决could not Authenticate
                $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));

        //发送命令 返回布尔值
        //PS：经过测试，要是收件人不存在，若不出现错误依然返回true 也就是说在发送之前 自己需要些方法实现检测该邮箱是否真实有效
                $status = $mail->send();

        //简单的判断与提示信息
        if($status) {
            echo '发送邮件成功';
        }else{
            echo '发送邮件失败，错误信息未：'.$mail->ErrorInfo;
        }
    }
    //测试是否连接上节点
    public function get_info(Request $request)
    {
        $short_en = $request->input('current_id');
        if(in_array($short_en, ['1001', '1005']))
            $res = $this->UsersWallet->get_wallet_info();
        else
            $res = $this->UsersWallet->get_info();
        return response_json(200, ['result'=>$res]);
    }

    //获取用户余额
    public function get_balance(Request $request)
    {
        $res = $this->UsersWallet->get_balance($request->uid);
        return response_json(200, ['result'=>$res]);
    }

    //获取用户余额
    public function get_sql_balance(Request $request)
    {
//        \Log::useDailyFiles(storage_path('logs/update_balance.log'));
//        \Log::info('200', $request->all());
//        UpdateBalance::dispatch(['id'=>$request->id, 'current_id'=>$request->current_id, 'seller'=>$request->seller, 'pos_id'=>$request->pos_id])->onQueue('update_balance');
        $res = $this->UsersWallet->update_calculate_balance($request->id, $request->current_id, $request->seller, $request->pos_id);
        return response_json(200, ['result'=>$res]);
    }

    //用户订单列表
    public function get_transactions_list(Request $request)
    {
        $res = $this->UsersWallet->_get_list_transactions($request->uid);
        if($res){
            foreach ($res as $key => &$item){
                $res[$key]['time'] = date('Y-m-d H:i:s', $item['time']);
                if(isset($item['timereceived'])){
                    $res[$key]['timereceived'] = date('Y-m-d H:i:s', $item['timereceived']);
                }
            }
        }
        return response_json(200, ['result'=>$res]);
    }

    //最新500条订单列表
    public function get_order_list()
    {
        if($this->current_id == '1011'){
            $res = $this->UsersWallet->omni_listtransactions();
        }else{
            $res = $this->UsersWallet->get_rpz_transaction_list_all();

        }
//        if($res){
//            foreach ($res as $key => &$item){
//                $res[$key]['time'] = date('Y-m-d H:i:s', $item['time']);
//                if(isset($item['timereceived'])){
//                    $res[$key]['timereceived'] = date('Y-m-d H:i:s', $item['timereceived']);
//                }
//            }
//        }
        return response_json(200, ['result'=>$res]);
    }

    //用户列表
    public function get_account_list()
    {
        $res = $this->UsersWallet->get_account_list();
        return response_json(200, ['result'=>$res]);
    }

    //验证地址
    public function validate_address(Request $request)
    {
        $res = $this->UsersWallet->validate_address($request->address);
        return response_json(200, ['result'=>$res]);
    }

    //获取账号地址
    public function get_addresses_by_account(Request $request)
    {
        $res = $this->UsersWallet->get_addresses_by_account($request->uid);
        return response_json(200, ['result'=>$res]);
    }

    //验证地址
    public function get_transaction(Request $request)
    {
        $res = $this->UsersWallet->get_transaction($request->jyXID);
        return response_json(200, ['result'=>$res]);
    }

    public function test_sent_email(Mailer $mailer)
    {
        try {
            $user = User::where('id', '125')->first();
            // var_dump($this->mail->user_id);
            // dd($user);
            $mailer->send('email.code', ['data'=>$this->mail, 'user'=>$user], function ($message) {
                $message->subject('E-mail verification');
                $message->to($this->mail['email']);
                $message->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'));
            });
            throw new \Exception('err');
            // $message = (new SendRegEmail($this->mail))->onQueue('emails');
            // Mail::to($request->user())->queue($message);
        } catch (\Exception $e) {
            \Log::info('400', ['msg'=>time(), 'msg'=>$e->getMessage()]);
//            file_put_contents(storage_path('emailbug.log'), '['.date('Y-m-d H:i:s', time()).']'.'email:'.$this->mail['email'],message:'.$exception->getMessage().PHP_EOL, FILE_APPEND);

        }
    }

    //验证地址
    public function usdtlisttransaction(Request $request)
    {
        $res = $this->UsersWallet->validate_address($request->address);
        return response_json(200, ['result'=>$res]);
    }

    //用户列表
    public function omni_listtransactions()
    {
        $res = $this->UsersWallet->omni_listtransactions();
        return response_json(200, ['result'=>$res]);
    }

    //获取id
    public function get_property_id(Request $request)
    {
        $res = $this->UsersWallet->getPropertyId($request->address);
        return response_json(200, ['result'=>$res]);
    }

    public function get_wallet_balance()
    {
        $res = $this->UsersWallet->getWalletBalance();
        return response_json(200, ['result'=>$res]);
    }

    public function send_to_address(Request $request)
    {
        $res = $this->UsersWallet->sendToAddress($request->address, $request->amount);
        return response_json(200, ['result'=>$res]);
    }

    public function send_many(Request $request)
    {
        $res = $this->UsersWallet->sendMany($request->uid, $request->address, $request->amount);
        return response_json(200, ['result'=>$res]);
    }


}
