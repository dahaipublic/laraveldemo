<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRegEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('example@example.com')
            ->view('emails.orders.shipped')// 可使用 from、subject、view 、text 、 markdown和 attach 来配置邮件的内容和发送
            ->with([
                'orderName' => $this->order->name,
                'orderPrice' => $this->order->price,
            ]);//带参数，通过with方法，具体的参数可以通过依赖注入获得
    }
}
