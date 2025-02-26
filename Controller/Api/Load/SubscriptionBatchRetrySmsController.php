<?php 

// 2024-07-18 wg-eric 실패건 결제 1일 전 sms 발송
namespace Controller\Api\Load;

use App;
use Request;
use Exception;
use Component\Subscription\Subscription;
use Component\Subscription\SubscriptionBatch;

class SubscriptionBatchRetrySmsController extends \Controller\Api\Controller
{
	public function index() { 
		$subObj = new Subscription();
        $subBatch  = new SubscriptionBatch();
		$this->db = App::load(\DB::class);

		$conf = $subObj->getCfg();
		$now_date = date("Y-m-d");

		if(empty($conf['order_fail_retry']))
            exit;

		$sql  = "
			select sf.*, s.orderCellPhone from wm_subscription_fail as sf
			LEFT JOIN wm_subSchedules as s ON s.idx = sf.scheduleIdx
			where sf.status=? 
			AND s.status = 'ready'
			AND s.orderNo <= 0
			order by sf.idx ASC
		";
        $list = $this->db->query_fetch($sql,['s','first']);

gd_debug($this->db->getBindingQueryString($sql, ['s','first']));
gd_Debug($list);
exit;

		$order_fail_retry = explode(",",$conf['order_fail_retry']);

		if ($list) {
			foreach ($list as $li) {
				$chkDay=[];
				foreach($order_fail_retry as $ckey =>$t){
					$TmpRegDt = substr($li['fail_regdt'],0,10);

					if($t > 0) {
						$timeString = ' +'.$t.' day';
					} else {
						$timeString = ' -'.$t.' day';
					}

					$chkDay[] = date("Y-m-d",strtotime($TmpRegDt.$timeString));
				}
				$deliveryDate = date("Y-m-d",strtotime($now_date." +".$conf['smsPayBeforeDay']." day"));
				$payDate = date("Y-m-d",strtotime($deliveryDate." -".$conf['payDayBeforeDelivery']." day"));

				if(in_array($payDate, $chkDay)===false){
					continue;
				}

				$mobile = str_replace("-", "", $li['orderCellPhone']);
				if($mobile) {
					$li['deliveryDate'] = date('Y.m.d', strtotime($deliveryDate));
					$li['payDate'] = date('Y.m.d', strtotime($payDate));
					//$subBatch->sendSms($mobile, $conf['smsTemplate'], $li);
					//$param = [
						//'smsSentStamp = ?',
					//];

					//$bind = [
						//'ii', 
						//time(),
						//$li['scheduleIdx'],
					//];

					//$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
				}
			}
		}

		
		exit;
	}
}