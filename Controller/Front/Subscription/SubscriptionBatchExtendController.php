<?php

namespace Controller\Front\Subscription;

use App;

/**
* 정기결제 일괄 자동연장
*
* @author webnmobile
*/
class SubscriptionBatchExtendController extends \Controller\Front\Controller
{
	public function index()
	{
		$db=\App::load(\DB::class);
		$db->query("insert into wm_test set content='extends',regDt=sysdate()");

		$subscriptionBatch = App::load(\Component\Subscription\SubscriptionBatch::class);
		$subscriptionBatch->getBatchExtend();
		exit;
	}
}