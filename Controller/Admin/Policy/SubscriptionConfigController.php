<?php

namespace Controller\Admin\Policy;

use App;

/**
* 정기결제 설정 
*
* @author webnmobile
*/
class SubscriptionConfigController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->callMenu("policy", "subscription", "config");
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$db = App::load(\DB::class);
		
		$cfg = $subscription->getCfg();

		$this->setData($cfg);
		$yoils = $subscription->getYoils();
		$this->setData("yoils", $yoils);
		
		/* 배송정책 */
		$sql = "SELECT sno, method, description FROM " . DB_SCM_DELIVERY_BASIC . " ORDER BY sno";
		$deliveryList = $db->query_fetch($sql);
		$this->setData("deliveryList", gd_isset($deliveryList, []));

		/*회원그룹*/
		$memberGroup = new \Component\Member\MemberGroup();

		$memberGroupList = $memberGroup->getGroupList();

		$this->setData("memberGroupList",$memberGroupList);

		//gd_debug($memberGroupList);

		$this->getView()->setPageName('policy/subscription_config_test');
	}
}