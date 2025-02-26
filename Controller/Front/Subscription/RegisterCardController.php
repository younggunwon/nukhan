<?php

namespace Controller\Front\Subscription;

use App;
use Request;

/**
* 카드등록 
*
* @author webnmobile
*/
class RegisterCardController extends \Controller\Front\Controller
{
	public function index()
	{
		header('Set-Cookie: same-site-cookie=foo; SameSite=Lax');
		header('Set-Cookie: cross-site-cookie=bar; SameSite=None; Secure');
		setcookie('samesite-test', '1', 0, '/; samesite=strict');
		/**
		* 1. _register_card_terms 약관 동의 
		* 2. _register_card_type 결제 방식 선택 
		* 3. _register_card 카드 등록
		*/ 
		$isAgree = Request::post()->get("isAgree");
		$payType = Request::post()->get("payType");
		$template = $isAgree?"_register_card":"_register_card_terms";
		
		$payType = "card";
		if ($template == '_register_card' && empty($payType)) {
			$template = "_register_card_type";
		}
		
		$this->getView()->setDefine("tpl", "subscription/{$template}.html");
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$conf = $subscription->getCfg();
		$this->setData("conf", $conf);
		$this->addScript(["subscription/card.js"]);
		$this->addCss(["musign/mu_layout.css"]);
		$this->setData("isAgree", $isAgree);
		$this->setData("payType", $payType);
		
		/* 비밀번호 입력 랜덤 숫자 */
		$chars = $subscription->getShuffleChars();
		$this->setData("chars", $chars);

		$tmpParam=\Request::request()->all();
		$method=$tmpParam['method'];
		$this->setData("method",$method);



	}
}