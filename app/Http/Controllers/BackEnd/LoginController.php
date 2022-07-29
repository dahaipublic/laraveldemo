<?php

namespace App\Http\Controllers\BackEnd;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
//    protected $redirectTo = '/backend/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * 显示后台登录模板
     */
    public function showLogin()
    {
        \Auth::guard('backend')->logout();
        return view('backend.login');
    }

    public function login(Request $request)
    {
        if (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } else {
            $ip = $request->getClientIp();
        }
        $allow_ip = ['183.13.201.3', '183.13.201.126', '112.73.1.92', '127.0.0.1', '192.168.0.201'];
        if (!in_array($ip, $allow_ip)) {
            return back()->withInput()->withErrors(['captcha' => 'IP被禁止访问']);
        }

        $validator = validator()->make(request()->all(), [
            'username'=>'required',
            'password'=>'required|min:8',
            'captcha' => 'required|captcha',
        ], ['captcha.captcha' => '验证码错误']);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }
        $username = $request->input('username');
        $password = $request->input('password');
        if(\Auth::guard('backend')->attempt(['username' => $username, 'password' => $password])){
            return redirect('backend/error_list');
        }else{
            return back()->withErrors(['password' => '帐号不存在或密码错误']);
        }
    }

    public function logout()
    {
        \Auth::guard('backend')->logout();
        return redirect('backend/login');
    }
}


