<?php

namespace Controller\Admin\Policy;

use App;
use Request;

/**
* 정기결제 휴무일관리 
*
* @author webnmobile
*/
class SubscriptionHolidayController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->callMenu("policy", "subscription", "holiday");
		$get = Request::get()->all();
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		
		$yoils = $subscription->getYoils();
		$this->setData("yoils", $yoils);
		
		$year = $get['year']?$get['year']:date("Y");
		$month = $get['month']?$get['month']:date("m");
		$month = (Integer)$month;
		$nextYear = $prevYear = $year;
		$nextMonth = $prevMonth = $month;
		switch ($month) {
			case 1: 
				$prevYear = $year - 1;
				$prevMonth = 12;
				$nextMonth = $month + 1;
				break;
			case 12: 
				$prevMonth = $month - 1;
				$nextYear = $year + 1;
				$nextMonth = 1;
				break;
			default : 
				$prevMonth = $month - 1;
				$nextMonth = $month + 1;
		}

		if (strlen($month) == 1)
			$month = "0".$month;
		
		if (strlen($prevMonth) == 1)
			$prevMonth = "0".$prevMonth;
		
		if (strlen($nextMonth) == 1)
			$nextMonth = "0".$nextMonth;
		
		
		$days = $subscription->getCalendarDates($year, $month);
		$this->setData("days", $days);
		
		$this->setData("month", $month);
		$this->setData("nextMonth", $nextMonth);
		$this->setData("prevMonth", $prevMonth);
		
		$this->setData("year", $year);
		$this->setData("nextYear", $nextYear);
		$this->setData("prevYear", $prevYear);
	}
}