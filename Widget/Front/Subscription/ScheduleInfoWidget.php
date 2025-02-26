<?php

namespace Widget\Front\Subscription;

use App;

/**
* 정기배송 상세정보 
*
* @author webnmobile
*/
class ScheduleInfoWidget extends \Widget\Front\Widget
{
	public function index()
	{
		$schedule = App::load(\Component\Subscription\Schedule::class);
		
		$cartSno = $this->getData("cartSno");
		$sPeriod=0;



		$gnos = [];
		if (empty($cartSno)) {
			$cartSno = [];
			$cartInfo = $this->getData("cartInfo");

			
			if ($cartInfo) {
				foreach ($cartInfo as $values) {
					foreach ($values as $value) {
						foreach ($value as $v) {
							$cartSno[] = $v['sno'];
							$gnos[] = $v['goodsNo'];

							if(!empty($v['sPeriod'])){
							
								$sPeriod=$v['sPeriod'];
							}
						}
					}
				}
			}
		}
		$this->setData("sPeriod",$sPeriod);

		
		$subConf = $schedule->getCfg($gnos);

	
		if ($cartSno) {
			$deliveryPeriod = $this->getData("deliveryPeriod");
			$deliveryEa = $this->getData("deliveryEa");
			
			
			$deliveryPeriod = $deliveryPeriod?$deliveryPeriod:$subConf['deliveryCycle'][0];
			$deliveryEa = $deliveryEa?$deliveryEa:$subConf['deliveryEa'][count($subConf['deliveryEa']) - 1];
			
			
			$deliveryEaMax = $subConf['deliveryEa'][count($subConf['deliveryEa']) - 1];
			
			//추가시작--전체설정 배송횟수로만 계산되는 오류처리
			if($deliveryEaMax<$deliveryEa)
				$deliveryEaMax=$deliveryEa;
			//추가종료

			$schedule->deliveryEa = $deliveryEa;

			//gd_debug($deliveryEaMax);
			
			$params = [
				'deliveryPeriod' => $deliveryPeriod,
				'deliveryEa' => $deliveryEaMax,
				'cartSno' => $cartSno,
			];

			
			$list = $schedule->getList($params);

			$this->setData("list", $list);
			$this->setData("deliveryEa", $deliveryEa);
		} // endif 


	}
}