<?php

namespace Controller\Front\Subscription;

/**
* 결제 카드 비밀번호 체크 
*
* @author webnmobile
*/
class CardPassController extends \Controller\Front\Subscription\RegisterCardController
{
	public function post()
	{
		$this->setData("order_pass", 1);
		$this->addCss(["musign/mu_layout.css"]);

		$method = \Request::get()->get('method');
		$this->getView()->setDefine("tpl", "subscription/_register_card.html");
	}
}