<?php

namespace Controller\Front\Subscription;

use App;

/**
* 정기결제 일괄 처리 
*
* @author webnmobile
*/
class SubscriptionBatchPayController extends \Controller\Front\Controller
{
	public function index()
	{

		//$db=\App::load(\DB::class);
		//$db->query("insert into wm_test set content='pay',regDt=sysdate()");

		$subscriptionBatch = App::load(\Component\Subscription\SubscriptionBatch::class);

		$subscriptionBatch->pauseUnLock();//일시정지된 정기결제중 기간이 있는경우 기간이 지났는지 체크후 다시 제개처리함

		$subscriptionBatch->getBatchPayList(null, true);
		exit;
	}
}

