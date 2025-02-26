<?php
namespace Controller\Front;
use Component\Subscription\Subscription;
use Component\Subscription\Schedule;
class TestController extends \Controller\Front\Controller
{

	public function index()
	{

$data['code']=-782;
	$data['msg']="subscription failure!";
	$data['extras']['method_result_code']="8008";
	$data['extras']['method_result_message']="거래거절 거래금액미달";

$dd = json_encode($data);
gd_debug($dd);
$t =json_decode($dd);
gd_debug($t);
echo $t->extras->method_result_message;
exit;



		set_time_limit(0); 
		$db = \App::load(\DB::class);
		$memNo=\Session::get("member.memNo");
		$subscription = new Subscription();
		$schedule = new Schedule();
		$toDay=date("Y-m-d");
		
		$sql = "SELECT a.*,b.deliveryPeriod,m.memId,m.memNm FROM wm_subSchedules a INNER JOIN wm_subApplyInfo b ON a.idxApply=b.idx INNER JOIN ".DB_MEMBER." m ON m.memNo=a.memNo where status='ready' group by  a.idxApply order by a.regDt DESC";
		$rows = $db->query_fetch($sql);


			gd_debug("==============START=================");

		foreach($rows as $k =>$t){

			//gd_debug($t['memId']);
			//gd_debug($t['memNm']);
			//gd_debug($t['idxApply']);
			//gd_debug($t['deliveryPeriod']);

			
			$s="select * from wm_subSchedules where idxApply='{$t['idxApply']}' order by idx ASC limit 0,1";
			$r = $db->fetch($s);

			$sql="select * from wm_subSchedules where idxApply='{$t['idxApply']}' order by idx ASC";
			$result = $db->query_fetch($sql);
			
			$deliveryPeriod = explode("_", $t['deliveryPeriod']);
			$period = $deliveryPeriod[0];
			$periodUnit = $deliveryPeriod[1]?$deliveryPeriod[1]:"week";

			$no=0;
			$firstDay=$r['deliveryStamp'];

			foreach($result as $key =>$v){
				$goodsNos=[];
				$goodsSql="select * from wm_subSchedulesGoods where idxSchedule='{$t['idx']}'";
				$goodsRows=$db->query_fetch($goodsSql);

				foreach($goodsRows as $gk=>$gv){
					$goodsNos[]=$gv['goodsNo'];
				}
				
				$conf = $subscription->getCfg($goodsNos);

				if($no>=1 && !empty($v['orderNo'])){
					$no++;
					continue;
				}
				
				
				if($no>=1){
					$priod = $period * $no;
					$str = "+{$priod} {$periodUnit}";
					$stamp = strtotime($str, $firstDay);

					
					while(true){
					
						$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
						$row = $db->query_fetch($sql, ["i", $stamp], false);
						if ($row && $row['isHoliday']) {
							/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
							if ($row['replaceStamp']) {
								$stamp = $row['replaceStamp'];
							} else {
								
								$stamp = strtotime("+1 day", $stamp);

							}
						}else{
							break;
						}

					}
					/* 공휴일 체크 E */
					$yoil = date("w", $stamp);
					
					if ($conf['deliveryYoils']) {
						while (!in_array($yoil, $conf['deliveryYoils'])) {
							$stamp = strtotime("+1 day", $stamp);
							$yoil = date("w", $stamp);
							/* 공휴일 체크 S */
							$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
							$row = $db->query_fetch($sql, ["i", $stamp], false);
							if ($row && $row['isHoliday']) {
								/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
								if ($row['replaceStamp']) {
									$stamp = $row['replaceStamp'];
									$yoil = date("w", $stamp);
								} else {
									$stamp = strtotime("+1 day", $stamp);
									$yoil = date("w", $stamp);
									continue;
								}
							}else{
								break;
							}
							
							/* 공휴일 체크 E */
							
						}
					}

					$payStamp = $stamp - (60 * 60 * 24 * $conf['payDayBeforeDelivery']);
					$smsStamp = $stamp - (60 * 60 * 24 * $conf['smsPayBeforeDay']);

					if(date("Y-m-d",$stamp)<=$toDay || date("Y-m-d",$payStamp)<=$toDay ){

					}else{
						
						$usql="update wm_subSchedules set deliveryStamp='$stamp' where idx='{$v['idx']}' and idxApply='{$t['idxApply']}'";
						
						//$db->query($usql);
						//echo"발송일";
						
						//echo"결제일";
						//gd_debug(date("Y-m-d",$payStamp));
						//echo "sms발송일";
						//gd_debug(date("Y-m-d",$smsStamp));

						//gd_debug($usql);
					}
				}


				$no++;
			}

			gd_debug("==============END=================");

			
		}

		 exit;
		

	}

}