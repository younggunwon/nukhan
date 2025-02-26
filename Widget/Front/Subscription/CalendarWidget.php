<?php

namespace Widget\Front\Subscription;

use App;

/**
* 수령일 변경 달력 
* 
* @author webnmobile
*/
class CalendarWidget extends \Widget\Front\Widget
{
	public function index()
	{
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$year = $this->getData("year");
		$year = $year?$year:date("Y");
		$month = $this->getData("month");
		$month = $month?$month:date("m");
		$idx = $this->getData("idx");
		$info = $subscription->getSchedule($idx);
		$deliveryType = $info['deliveryType']?$info['deliveryType']:"parcel";
		$goodsNos = array_column($info['goods'], "goodsNo");
		$deliveryStamp = $info['deliveryStamp'];
		$maxDeliveryStamp = $info['deliveryStamp'] + (60 * 60 * 24 * 30);
		$days = $subscription->getCalendarDates($year, $month,$goodsNos);
		
		foreach ($days as $k => $day) {
			if ((Integer)$day['day'] == 1) {
				$year = date("Y", $day['stamp']);
				$month = date("m", $day['stamp']);
				break;
			}
		}
		
		foreach ($days as $k => $day) {
			if ($day['stamp'] > $maxDeliveryStamp) {
				$day['available'] = false;
			}
			
			$days[$k] = $day;	
		}
		
		$month = (Integer)$month;
        $prev_year = $next_year = $year;
        $prev_month = $next_month = $month;
        
        if ($month == 12) {
            $next_year = $year + 1;
            $next_month = 1;
            $prev_month = $month - 1;
        } else if ($month == 1) {
            $prev_year = $year - 1;
            $prev_month = 12;
            $next_month = $month + 1;
        } else {
            $prev_month = $month - 1;
            $next_month = $month + 1;
        }
		
		if (strlen($month) == 1) $month = "0".$month;
		$this->setData("year", $year);
        $this->setData("month", $month);
        $this->setData("prev_year", $prev_year);
        $this->setData("prev_month", $prev_month);
        $this->setData("next_year", $next_year);
        $this->setData("next_month", $next_month);
		$this->setData("days", $days);
		$this->setData("yoils", $subscription->getYoils());
		$this->setData("idx", $idx);
		$this->setData("deliveryStamp", $deliveryStamp);
		$this->setData("maxDeliveryStamp", $maxDeliveryStamp);
	}
}