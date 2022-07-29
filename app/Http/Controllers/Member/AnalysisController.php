<?php

namespace App\Http\Controllers\Member;

use App\Models\Business\BusinessLogLogin;
use App\Models\Community;
use App\Models\CommunityGroup;
use App\Models\CommunityUser;
use App\Models\Currency;
use App\Models\Friend;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Information\Information;
use App\Models\User;
use App\Models\UsersWallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

if(isset($_SERVER['HTTP_ORIGIN'])){
    header("access-control-allow-origin: ".$_SERVER['HTTP_ORIGIN']);
    header("access-control-allow-headers: Origin, Content-Type, Cookie, X-CSRF-TOKEN, Accept, Authorization, X-XSRF-TOKEN");
    header("access-control-expose-headers: Authorization, authenticated");
    header("access-control-allow-methods: POST, GET, PATCH, PUT, OPTIONS");
    header("access-control-allow-credentials: true");
    header("access-control-max-age: 2592000");
}


/**
 * @group 首页
 * - author whm
 */

class AnalysisController extends Controller
{


    // 获取首页相关数据
    public function getTotalNumber(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        if(empty($user->fc_current_id)){
            $user->fc_current_id = 8005; // 默认人民币
        }
        $fc_currency_rate = Currency::where('current_id', $user->fc_current_id)->where('is_virtual', 0)->value('rate');
        $fc_unit = Currency::getUnit($user->fc_current_id);

        // 好友数量
        $friend_number = (new Friend())->getFriendNumber($uid);
        // 社区总数
        $community_number = Community::getJoinCount($uid) + Community::from('community')->where('creator_id', $uid)->count("id");
        // 群聊总数
        $group_number = GroupUser::from("group_user as gu")
            ->join('group as g', 'gu.group_id', '=', 'g.id')
            ->where('gu.user_id', $uid)
            ->where('gu.status', 1)
            ->where('g.status', 1)
            ->count("g.id");
        // 文章上线数
        $information_number = Information::where('uid', $uid)->where('status', 1)->where('type', 1)->where('is_del', 0)->count('id');
        // 用户钱包
        if($user->language == 'cn'){
            $name_en = "c.name_cn as name_en";
        }else{
            $name_en = "c.name_en";
        }
        ////////////////////////////////// 钱包地址 ////////////////////////////////////////
        (new User())->createUsersWallet($uid);
        ////////////////////////////////// 钱包地址 ////////////////////////////////////////

        $user_wallet = UsersWallet::from('users_wallet as w')
            ->select("w.current_id", "w.usable_balance", $name_en, "c.short_en as unit", "c.circle as icon", "c.rate", "c.color")
            ->join('currency as c', 'w.current_id', '=', 'c.current_id')
            ->where('w.uid', $uid)
            ->where('w.is_del', 0)
            ->where('c.enabled', 1)
            ->orderBy('c.sort', 'desc')
            ->orderBy('c.current_id', 'asc')
            ->get()
            ->toArray();
        if(!empty($user_wallet)){
            $currency_model = new Currency();
            foreach ($user_wallet as &$item){
                $item['currency'] = $item['name_en'].'('.$item['unit'].')';
                $item['icon'] = url($item['icon']);
                $item['fc_usable_balance'] = $currency_model->getMoveMoney($item['current_id'], $item['usable_balance'], $item['rate'], $fc_currency_rate, 2).' '.$fc_unit;
                $item['fc_unit'] = $fc_unit;
                unset($item['rate'], $item['name_en']);
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'friend_number' => $friend_number,
            'community_number' => $community_number,
            'group_number' => $group_number,
            'information_number' => $information_number,
            'user_wallet' => $user_wallet,
        ));

    }


    // 获取首页波浪的数据
    public function getWave(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        // 1 好友 2 社区 3 群聊 4 文章
        $wave_type = $request->input('wave_type', 1);
        // 0 为传时间 1 近7天 2 近15天 3 近30天
        $day_type = $request->input('day_type', 1);
        $start_time = strtotime($request->input('start_time'));
        $end_time = strtotime($request->input('end_time'));

        if($day_type == 0){
            if(empty($start_time) || empty($end_time)){
                response_json(403, trans('web.parameterError'));
            }
            if ($start_time < $end_time) {
                response_json(403, trans('web.startTimeMustBeGreaterThanEndTime'));
            }
        }

        $wave_arr = array();
        $day_arr = (new User())->getWaveDay($day_type, $start_time, $end_time);
        if(!empty($day_arr)){
            $start_time = $day_arr[0]['timestamp'];
            $end_time = $day_arr[count($day_arr)-1]['timestamp'] + 3600 * 24 - 1;
        }else{
            return response_json(200, trans('web.getDataSuccess'), array(
                'wave' => $wave_arr,
                'error' => 'empty'
            ));
        }

        $list = array();
        // 数据列表
        // 1 好友 2 社区 3 群聊 4 文章
        if($wave_type == 1){
            $query = Friend::select("friend_id", "created_at")
                ->where('user_id', $uid)
                ->where('status', 2);
        }elseif ($wave_type == 2){
            $query = Community::select("id", "created_at")
                ->where('creator_id', $uid)
                ->where('status', 1);
        }elseif ($wave_type == 3){
            $query = Group::select("id", "created_at")
                ->where('creatorId', $uid)
                ->where('status', 1);
        }else{
            $query = Information::select("id", "created_at")
                ->where('uid', $uid)
                ->where('status', 1);
        }

        $list = $query->where('created_at', '>', date('Y-m-d H:i:s', $start_time))
            ->where('created_at', '<', date('Y-m-d H:i:s', $end_time))
            ->get()
            ->toArray();

        // 这是一个首页波浪数据
        if(!empty($list)){
            foreach ($list as &$_item){
                $_item['created_at'] = date('Y-m-d', strtotime($_item['created_at']));
            }
            foreach ($day_arr as $day_item){
                $temp = array(
                    'ymd' => $day_item['ymd'],
                    'number' => 0
                );
                foreach ($list as $item){
                    if($day_item['ymd'] == $item['created_at']){
                        $temp['number'] += 1;
                    }
                }
                $wave_arr[] = $temp;
            }
        }else{
            foreach ($day_arr as $day_item){
                $temp = array(
                    'ymd' => $day_item['ymd'],
                    'number' => 0
                );
                $wave_arr[] = $temp;
            }
        }

        return response_json(200, trans('web.getDataSuccess'), array(
            'wave' => $wave_arr,
        ));

    }


    // 数据统计
    public function getDataStatistics(Request $request){

        $user = Auth::guard('member')->user();
        $uid = $user->id;

        $create_group = Group::select("id")->where('creatorId', $uid)->where('status', 1)->get()->toArray();
        $create_group_ids = array_column($create_group, 'id');


        /////////////////////////////////////////////// 群组 ///////////////////////////////////////
        // 自建普通群数
        $create_group_number = count($create_group);
        // 已加入群组
        $join_group_number = GroupUser::where('user_id', $uid)->where('status', 1)->count("id");
        // 自建普通群人数
        $group_number = 0;
        if(!empty($create_group_ids)){
            $group_number = GroupUser::where('status', 1)->whereIn('group_id', $create_group_ids)->count("id");
        }
        // 好友数量
        $friend_number = (new Friend())->getFriendNumber($uid);
        $group = array(
            'friend_number' => $friend_number,
            'create_group_number' => $create_group_number,
            'join_group_number' => $join_group_number,
            'group_number' => $group_number,
        );
        /////////////////////////////////////////////// 群组 ///////////////////////////////////////



        /////////////////////////////////////////////// 社区 ///////////////////////////////////////

        $where[] = ['creator_id', $uid];
        $create_count = Community::from('community')->where($where)->count();
        $create_people_count = intval(Community::from('community')->where($where)->sum('member_count'));
        $join_count = Community::getJoinCount($uid);
        $join_people_count = Community::getJoinPeopleCount($uid);
        $community = array(
            'create_count' => $create_count,
            'create_people_count' => $create_people_count,
            'join_count' => $join_count,
            'join_people_count' => $join_people_count,
        );

        /////////////////////////////////////////////// 社区 ///////////////////////////////////////



        /////////////////////////////////////////////// 资讯 ///////////////////////////////////////
        $list = Information::select("id as information_id", "status")
            ->where('uid', $uid)
            ->where('type', 1)
            ->where('is_del', 0)
            ->get()
            ->toArray();

        $status0 = 0;
        $status1 = 0;
        $status2 = 0;
        $status3 = 0;
        if(!empty($list)){
            foreach ($list as $item){
                if($item['status'] == 1){
                    $status1 += 1;
                }elseif ($item['status'] == 2){
                    $status2 += 1;
                }elseif ($item['status'] == 0){
                    $status0 += 1;
                }elseif ($item['status'] == 3){
                    $status3 += 1;
                }
            }
        }
        $information = array(
            'status0' => $status0,
            'status1' => $status1,
            'status2' => $status2,
            'status3' => $status3,
        );
        /////////////////////////////////////////////// 资讯 ///////////////////////////////////////


        return response_json(200, trans('web.getDataSuccess'), array(
            'group' => $group,
            'community' => $community,
            'information' => $information,
        ));

    }



}