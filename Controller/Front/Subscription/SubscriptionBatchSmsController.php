<?php

namespace Controller\Front\Subscription;

use App;

/**
* 정기결제 일괄 처리 
*
* @author webnmobile
*/
class SubscriptionBatchSmsController extends \Controller\Front\Controller
{
	public function index()
	{

		$db=\App::load(\DB::class);
		$db->query("insert into wm_test set content='sms',regDt=sysdate()");

		$subscriptionBatch = App::load(\Component\Subscription\SubscriptionBatch::class);
		$subscriptionBatch->getBatchSmsList(null, true);
		exit;
	}
}