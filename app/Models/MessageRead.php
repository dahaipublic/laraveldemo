<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageRead extends Model
{
    //
    protected $table = 'message_read';

    protected $primaryKey = 'id';


    /**
     * @desc 添加用户之前的后台通知， 2019-04-04 经理说如此操作
     * @param $user
     */
    public function addBeforeMessage($user){

        $message_ids = MessageRead::select("message_id")
            ->where('uid', $user->id)
            ->get()
            ->toArray();
        $message_ids = array_column($message_ids, 'message_id');
        // 添加重要消息
        $now_time = date('Y-m-d H:i:s');
        $list = Message::select("id", "created_at")
            ->where('lang', $user->language)
            ->where('created_at', '<=', $user->created_at)
            ->whereNotIn('type', [1, 3])
            ->whereNotIn('id', $message_ids)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
        if(!empty($list)){
            $data = array();
            foreach ($list as $item){
                $data[] = array(
                    'message_id' => $item['id'],
                    'uid' => $user->id,
                    'created_at' => $item['created_at'],
                    'updated_at' => $now_time,
                );
            }
            // 批量插入
            $insert = MessageRead::insert($data);
            if($insert){
                User::where('id', $user->id)->update([
                    'message_read_add' => 1
                ]);
            }
        }

    }

}
