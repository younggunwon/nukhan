<?php
namespace Widget\Front\Subscription;

class NextDeliveryWidget extends \Widget\Front\Widget
{

	public function index()
	{
		$db=\App::load(\DB::class);
		$nextDeliveryStamp=$this->getData("nextDeliveryStamp");
		$yoilStr=$this->getData("yoilStr");
		$idx=$this->getData("idx");


//gd_debug(date("Y-m-d",$nextDeliveryStamp));
//gd_debug($idx);


		$delivery_count=0;

		$r=$db->fetch("select deliveryStamp from wm_subSchedules where idxApply='$idx' and status='ready' order by idx ASC limit 0,1");
		
		if(empty($r['deliveryStamp']) ){
			
			$delivery_count=-1;
		}else{
			$nextDeliveryStamp=$r['deliveryStamp'];
		}
		$this->setData("delivery_count",$delivery_count);

		$this->setData("nextDeliveryStamp",$nextDeliveryStamp);
		$this->setData("yoilStr",$yoilStr);

	}

}