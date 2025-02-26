<?php

namespace Component\Traits;

use App;

/**
* 날짜 요일 관련 
*
* author webnmobile
*/
trait DateDay
{
	/**
	* 요일 추출 
	*
	* @return Array
	*/ 
	public function getYoils()
	{
		$yoils = ['일','월','화','수','목','금','토'];
		
		return $yoils;
	}
	
	/**
	* timestamp로 요일 추출 
	*
	* @param Integer $stamp Unix Timestamp 
	* @return String 요일 
	*/
	public function getYoilStr($stamp = 0)
	{
		$stamp = $stamp?$stamp:time();
		$yoils = $this->getYoils();
		
		return $yoils[date("w", $stamp)];
	}
	
	/**
	* 달력 날짜 
	*
	* @param String $year 년
	* @param String $month 월
	*
	* @return Array
	*/
	public function getCalendarDates($year = null, $month = null,$goodsNo=[])
    {
		$year = $year?$year:date("Y");
		$month = $month?$month:date("m");
		
		if (strlen($month) == 1)
			$month = "0".$month;
		
		$startDate = $year.$month."01";
		$sstamp = strtotime($startDate);
		
		$endStamp = strtotime("next month", $sstamp) - 1;
		$lastDay = date("d", $endStamp);
		$yoils = $this->getYoils();
		
		$days = [];
		for ($i = 0; $i < $lastDay; $i++) {
			$newStamp = $sstamp + (60 * 60 * 24 * $i);
			$yoil = date("w", $newStamp);
			
			$d = [
				'stamp' => $newStamp, 
				'day' => date("d", $newStamp),
				'yoil' => $yoil,
				'yoilStr' => $yoils[$yoil],
			];
			
			$days[] = $d;
		}
	
		$days2 = [];
		for($i = $days[0]['yoil']; $i > 0; $i--) {
			$newStamp = $sstamp - (60 * 60 * 24 * $i);
			$yoil = date("w", $newStamp);
			$d = [
				'stamp' => $newStamp, 
				'day' => date("d", $newStamp),
				'yoil' => $yoil,
				'yoilStr' => $yoils[$yoil],
			];
			
			$days2[] = $d;
		}
		
        $days = array_merge($days2, $days);
		$gap = 42 - count($days);
		if ($gap >= 7) $last = 35;
		else $last = 42;
		
		$estamp = strtotime(date("Ymd", $endStamp));
		$days3 = [];
		for ($i = count($days); $i < $last; $i++) {
			$no = $i - count($days) + 1;
			$newStamp = $estamp  + (60 * 60 * 24 * $no);
			$yoil = date("w", $newStamp);
			$d = [
				'stamp' => $newStamp, 
				'day' => date("d", $newStamp),
				'yoil' => $yoil,
				'yoilStr' => $yoils[$yoil],
			];
			
			$days3[] = $d;
		}
		
		
		
		$days = array_merge($days, $days3);
		
		$schedule = new \Component\Subscription\Schedule();
		$firstStamp = $schedule->getFirstDay("",$goodsNo);
		$cfg = $this->getCfg($goodsNo);

		//if(\Request::getRemoteAddress()=="182.216.219.157"){
			//2023.06.29 웹앤모바일 정기배송일 사용자가 변경시 이틀후부터 시작하도록 요청수정함 시작
			$server = \Request::server()->toArray();
			
			if($server['PHP_SELF'] == "/subscription/calendar_change_date.php"){
				$firstStamp = strtotime(date("Y-m-d",$firstStamp)." +2 day");
			}
			//2023.06.29 웹앤모바일 정기배송일 사용자가 변경시 이틀후부터 시작하도록 요청수정함 종료
			//gd_debug(date("Y-m-d",$firstStamp));
		//}

		foreach ($days as $k => $v) {
			$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
			$row = $this->db->query_fetch($sql, ["i", $v['stamp']], false);
			$v['isHoliday'] = $row['isHoliday']?1:0;
			$v['replaceStamp'] = gd_isset($row['replaceStamp'], 0);
			$v['memo'] = $row['memo'];
			
			$v['available'] = true;
	
			if ($v['stamp'] < $firstStamp || $v['isHoliday']) {
				$v['available'] = false;
			} 
			
			$yoil = date("w", $v['stamp']);
			if ($cfg['deliveryYoils'] && !in_array($yoil, $cfg['deliveryYoils'])) {
				$v['available'] = 0;
				//gd_debug($v);
			}
		
			$days[$k] = $v;
		}
			//gd_debug($days);

		return $days;
	}
	
	/**
	* 오늘날짜 Unix Timestamp
	*
	* @return Interger 
	*/
	public function today()
	{
		return strtotime(date("Ymd"));
	}
}