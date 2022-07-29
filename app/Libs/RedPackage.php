<?php

namespace App\Libs;

class RedPackage
{
	public $decimel;

	public $total_amount;

	public $num;

	public $type;//固定，随机

	public $min_amount;

	public $max_amount;

	public static function create($total_amount, $num, $type, $min_amount, $decimel)
	{
		$self = new self();
		$self->total_amount = $total_amount;
		$self->num = $num;
		$self->type = $type;
		$self->min_amount = $min_amount;
		$self->decimel = $decimel;
		$self->max_amount = bcdiv($self->total_amount , $self->num , $self->decimel)*2;
		return $self;
	}
}

interface CreateRedPackage
{
	//创建红包
	public function create();

	//设置参数
	public function set_config(RedPackage $config);

	//检验参数
	public function check_param();

	//生成红包金额
	public function create_red_package($x);
}


class EqualRedPackage implements CreateRedPackage
{
	public $total_amount;

	public $amount;

	public $num;

	public $min_amount;

	public $decimel;

	public function __construct($config = null)
	{
		if($config instanceof Redpackage){
			$this->set_config($config);
		}
	}

	public function set_config(Redpackage $config)
	{
		// dump($config);exit;
		$this->num = $config->num;
		$this->total_amount = $config->total_amount;
		$this->min_amount = $config->min_amount;
		$this->decimel = $config->decimel;
		// dump($this->decimel);exit;
		$this->amount = bcadd((bcdiv($this->total_amount, $this->num, $this->decimel)), 0, $this->decimel);
		// dump($this->amount);exit;
	}

	public function check_param()
	{
		// if(($this->min_amount * $this->num) != 0 ){
		// 	return false;
		// }

		if(is_numeric($this->num) == false || $this->num <= 0){
			return false;
		}

		if(is_numeric($this->amount) == false || $this->amount <= 0){
			return false;
		}

		if(($this->total_amount / $this->num) < $this->min_amount){
			return false;
		}

		return true;
	}


	public function create_red_package($x)
	{
		return $this->amount;
	}

	public function create()
	{
		$data = [];

		if($this->check_param() == false){
			return $data;
		}

		for ($i=0; $i < $this->num; $i++) { 
			$data[$i] = $this->create_red_package($i);
		}
		$data = $this->check_equivalence($data);
		// foreach ($data as $key => $value) {
		// 	$a = bcadd($a, $value, $this->decimel);
		// }
		// dump($a);exit;
		// shuffle($data);
		return $data;
	}

	public function check_equivalence($data)
	{
		$sum = 0;
        foreach ($data as $value){
            $sum = bcadd($sum, $value, $this->decimel);
        }

        //修正数据
     	if(bccomp($sum, $this->min_amount, $this->decimel) != 0) {
     		$balance = bcsub($this->total_amount, $sum, $this->decimel);
     		$data[0] = bcadd($data[0], $balance, $this->decimel);
            //0.00999996   <0.01
        }
        
        
        return $data;
	}
}

class RandomRedPackage implements CreateRedPackage
{
	public $total_amount;

	public $num;

	public $min_amount;

	public $decimel;//保留小数

	public $min_proability;//最小概率

	public $max_proability;//最大概率

	public $sum_probability;//概率之和

	public $averange_probability;//平均概率值

	public function __construct($config = null)
	{
		if($config instanceof Redpackage){
			$this->set_config($config);
		}
	}

	public function set_config(Redpackage $config)
	{
		$this->total_amount = $config->total_amount;
		$this->num = $config->num;
		$this->min_amount = $config->min_amount;
		$this->decimel = $config->decimel;

		//化小数为整数
		$this->min_probability = 1;
		$this->sum_probability = 0;
		$this->max_probability = $this->total_amount / $this->min_amount;
		$this->averange_probability = $this->total_amount / $this->min_amount / $this->num;
	}

	public function check_param()
	{
		if(is_numeric($this->num) == false || $this->num <= 0){
			return false;
		}

		return true;
	}


	public function create_red_package($i)
	{
        //最大概率为剩余概率的一半
        $allow_max_probability = ($this->max_probability - $this->sum_probability - ($this->num - $i) * $this->averange_probability) ;

        // file_put_contents($i.'_.txt', $allow_max_probability);
         // dump($allow_max_probability);exit;
        if($allow_max_probability < $this->min_probability){
            $allow_max_probability = $this->min_probability;
        }

        if($i == $this->num){
        	$sub_data = $this->max_probability - $this->sum_probability;
        }else{
        	//最小到允许的最大概率
        	$sub_data = mt_rand($this->min_probability*pow(10, 7), $allow_max_probability*pow(10, 7))/pow(10, 7);
        }
        $this->sum_probability = bcadd($this->sum_probability, $sub_data, $this->decimel);
        return $sub_data;
		
	}

	public function get_sum_probability()
	{
		return $this->sum_probability;
	}

	//根据概率获取随机值
	public function get_rand_number($data)
	{
		foreach ($data as $key => $value){
            $amount = bcmul($this->total_amount, $value / $this->max_probability, $this->decimel);
            if(bccomp($amount, $this->min_amount, $this->decimel) == -1) {
	            $data[$key] = $this->min_amount;  //0.00999996   <0.01
	        }else {
            	$data[$key] = $amount;
            }
        }
        return $data;
	}

	public function create()
	{
		
		//$data = [];//概率集合
		if($this->check_param() == false){
			return [];
		}

		if($this->num == 1){
			$data = [$this->total_amount];
			// dump($data);exit;
			return $data;

		}else{

			for ($i = 1; $i <= $this->num; $i++) { 
			
				$data[$i] = $this->create_red_package($i);

			}
			// dump($data);exit;
			$data = $this->get_rand_number($data);

			$data = $this->check_equivalence($data);
			shuffle($data);
			return $data;
		}

	}

	//检查随机数的总和与所发放红包总和的值是否对等
	public function check_equivalence($data)
	{
		$sum = 0;
        foreach ($data as $value){
            $sum = bcadd($sum, $value, $this->decimel);
        }
        //修正数据
        $balance = bcsub($this->total_amount, $sum, $this->decimel);
        $data[$this->num] = bcadd($data[$this->num], $balance, $this->decimel);
        return $data;
	}
}