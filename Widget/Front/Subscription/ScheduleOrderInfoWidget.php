<?php

namespace Widget\Front\Subscription;

use App;

/**
* 정기배송 상세정보(주문서)
*
* @author webnmobile
*/
class ScheduleOrderInfoWidget extends \Widget\Front\Widget
{
	public function index()
	{
		$schedule = App::load(\Component\Subscription\Schedule::class);
		
		$gnos = [];
		$cartSno = $this->getData("cartSno");
		if (empty($cartSno)) {
			$cartSno = $cartSno2 = $items = $tgItems = [];
			$cartInfo = $this->getData("cartInfo");
			if ($cartInfo) {
				foreach ($cartInfo as $values) {
					foreach ($values as $value) {
						foreach ($value as $v) {
							$cartSno[] = $v['sno'];
							if (!$v['isTogetherGoods']) {
								$cartSno2[] = $v['sno'];
								$items[] = $v;
							} else {
								$tgItems[] = $v;
								$gnos[] = $v['goodsNo'];
							}
						}
					}
				}
			}
		}
		$subConf = $schedule->getCfg($gnos);
		
		if ($cartSno && $cartSno2) {
			$deliveryPeriod = $this->getData("deliveryPeriod");
			$deliveryEa = $this->getData("deliveryEa");
			$deliveryPeriod = $deliveryPeriod?$deliveryPeriod:$subConf['deliveryCycle'][0];
			$deliveryEa = 10;//10회로 고정 $deliveryEa?$deliveryEa:$subConf['deliveryEa'][0];
			
			$params = [
				'deliveryPeriod' => $deliveryPeriod,
				'deliveryEa' => 1,
				'cartSno' => $cartSno,
			];
			
			$schedule->deliveryEa = $deliveryEa;
			$list = $schedule->getList($params);
			
			$params = [
				'deliveryPeriod' => $deliveryPeriod,
				'deliveryEa' => $deliveryEa,
				'cartSno' => $cartSno2,
			];
			
			$list2 = $schedule->getList($params);
			
			$list2[0] = $list[0];
			foreach ($list2 as $k => $v) {
				$v['items'] = $items;
				if ($k == 0) {
					$v['tgItems'] = $tgItems;
				}
				
				$list2[$k] = $v;
			}

			$this->setData("list", $list2);
			$this->setData("deliveryEa", $deliveryEa);
			
		} // endif 
	}
}