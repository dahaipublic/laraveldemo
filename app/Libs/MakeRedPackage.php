<?php

namespace App\Libs;

use App\Libs\RedPackage;
use App\Libs\EqualRedPackage;
use App\Libs\RandomRedPackage;

class MakeRedPackage
{	
	protected static $_instance = null;

	public static function get_instance()
	{
		if(self::$_instance == null){
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function get_red_package_class($type)
	{
		//1普通红包 2，手气红包
		if($type == '1'){
			$class = new EqualRedPackage();
		}else{
			$class = new RandomRedPackage();
		}
		
		return $class;
	}

	public function make_red_package(RedPackage $red_package)
	{
		$red_package_class = $this->get_red_package_class($red_package->type);

		$red_package_class->set_config($red_package);

		return $red_package_class->create();
	}

        /**
         * 红包生成核心算法
         * @param type $total_amount 总金额
         * @param type $num 红包个数
         * @param type $type 红包类型，1固定，2随机
         * @param type $min_amount 红包最小值
         * @param type $decimel 红包有效位数
         * @return type
         */
	public function get_red_package($total_amount, $num, $type = 1, $min_amount = 0.01, $decimel = 2)
	{
            $red_package = RedPackage::create($total_amount, $num, $type, $min_amount, $decimel);
            return self::get_instance()->make_red_package($red_package);
	}
}