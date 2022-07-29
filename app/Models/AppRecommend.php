<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRecommend extends Model
{
    protected $table = 'recommend_code';

    //禁用
    const STATUS_UNUSED = 0;
    //启用
    const STATUS_ISUSED = 1;

    public static $status_en = [
        999 => 'Unselected',
        self::STATUS_UNUSED => 'Inactivated',
        self::STATUS_ISUSED => 'Activated'
    ];

    public static $status_cn = [
        999 => '未选择',
        self::STATUS_UNUSED => '未激活',
        self::STATUS_ISUSED => '已激活'
    ];

    public static $status_hk = [
        999 => '未選擇',
        self::STATUS_UNUSED => '未激活',
        self::STATUS_ISUSED => '已激活'
    ];
}
