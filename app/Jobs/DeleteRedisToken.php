<?php

namespace App\Jobs;

use App\Models\Api\LoginToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DeleteRedisToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $user_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user_id = 0)
    {
        $this->user_id = $user_id ? : 0;
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        DB::table('task')->insert(['task_message'=>'API_STRING_SINGLETOKEN_'.$this->user_id.'_'.date('Y-m-d H:i:s')]);

        try{

            if(empty($this->user_id)){
                return true;
            }
            $list = (new User())->getRedisToken($this->user_id);
            if(!empty($list)){
                foreach ($list as $item){
                    Redis::del($item['redis_key']);
                }
            }
            // 删除redis中的token
            Redis::del('API_STRING_SINGLETOKEN_'.$this->user_id);
            Redis::del('MEMBER_STRING_SINGLETOKEN_'.$this->user_id);

            // 删除数据库中的token
            // LoginToken::where('uid', $this->user_id)->delete();

        }catch(\Exception $exception) {

            Log::useFiles(storage_path('deleteRedisToken.log'));
            Log::info('deleteRedisToken: message:'.$exception->getMessage().', file:'.$exception->getFile().', line:'.$exception->getLine());

        }

    }
}
