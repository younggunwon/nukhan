<?php
namespace Controller\Admin\Order;

use App;
use Request;

/**
* 정기결제 일괄 처리 
*
* @author webnmobile
*/
class SubscriptionBatchController extends \Controller\Admin\Controller 
{
    public function index()
    {
		$this->callMenu("order", "subscription", "batch");
		$get = Request::get()->all();
		$get['date'] = $get['date']?$get['date']:date("Y-m-d");
		
		$get['mode'] = $get['mode']?$get['mode']:'extend';
        $subscription = App::load(\Component\Subscription\SubscriptionBatch::class);
		if ($get['mode'] == 'sms') {
			$list = $subscription->getBatchSmsList($get['date']);
		} else if ($get['mode'] == 'pay') {
			$list = $subscription->getBatchPayList($get['date']);
		} else if ($get['mode'] == 'extend') {
			$list = $subscription->getBatchExtend();
		}
		
		$this->setData("list", $list);
		$this->setData("search", $get);
    }
}