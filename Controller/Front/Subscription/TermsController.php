<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Component\Agreement\BuyerInformCode;

/**
* 약관 조회
*
* @author webnmobile
*/
class TermsController extends \Controller\Front\Controller
{
	public function index()
	{
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$conf = $subscription->getCfg();
		$type = Request::get()->get("type");
		switch ($type) {
			case "subscription" : 
				$title = "정기주문 이용약관";
				$terms = $conf['terms'];
				break;
			case "privateInfoOffer" : 
				$title = "개인정보 제3자 제공, 위탁동의";
				
				$inform = \App::load('\\Component\\Agreement\\BuyerInform');
				$privateGuestOffer = $inform->getAgreementWithReplaceCode(BuyerInformCode::PRIVATE_GUEST_ORDER);
				$terms = trim($privateGuestOffer['content']);
				break;
		}
		
		$this->setData("title", $title);
		$this->setData("terms", $terms);
	}
}