<?php 

// 실패건 결제 1일 전 sms 발송
namespace Controller\Front\Subscription;

use App;
use Request;
use Exception;
use Component\Subscription\Subscription;
use Component\Subscription\SubscriptionBatch;

class TestSubscriptionBatchRetrySmsController extends \Controller\Front\Controller
{
	public function index() { 
		$subObj = new Subscription();
        $subBatch  = new SubscriptionBatch();
		$this->db = App::load(\DB::class);

		$conf = $subObj->getCfg();
		$deliveryDate = date("Y-m-d",strtotime($TmpRegDt." +".$conf['smsPayBeforeDay']." day"));
		$payDate = date("Y-m-d",strtotime($TmpRegDt." +".$conf['payDayBeforeDelivery']." day"));

		if(empty($conf['order_fail_retry']))
            exit;

		$sql  = "
			select sf.*, s.orderCellPhone from wm_subscription_fail as sf
			LEFT JOIN wm_subSchedules as s ON s.idx = sf.scheduleIdx
			where sf.status=? 
			order by sf.idx ASC
		";
        $list = $this->db->query_fetch($sql,['s','first']);

		gd_debug($this->db->getBindingQueryString($sql, ['s','first']));
		gd_debug($list);
		exit;

		$order_fail_retry = explode(",",$conf['order_fail_retry']);

		if ($list) {
			foreach ($list as $li) {
				foreach($order_fail_retry as $ckey =>$t){
					$TmpRegDt = substr($li['fail_regdt'],0,10);

					if($t > 0) {
						$timeString = ' +'.$t.' day';
					} else {
						$timeString = ' -'.$t.' day';
					}

					$chkDay[] = date("Y-m-d",strtotime($TmpRegDt.$timeString));
				}

				if(in_array($payDate,$chkDay)===false){
					continue;
				}

				$mobile = str_replace("-", "", $li['orderCellPhone']);
				if($mobile) {
					$li['deliveryDate'] = date('Y.m.d', strtotime($deliveryDate));
					$li['payDate'] = date('Y.m.d', strtotime($payDate));
					$subBatch->sendSms($mobile, $conf['smsTemplate'], $li);
					$param = [
						'smsSentStamp = ?',
					];

					$bind = [
						'ii', 
						time(),
						$li['scheduleIdx'],
					];

					$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
				}
			}
		}

		
		exit;
	}
}