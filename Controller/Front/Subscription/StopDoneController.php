<?php

namespace Controller\Front\Subscription;

/**
* 정기배송 그만 받기 - 취소완료
* 
* @author webnmobile
*/
class StopDoneController extends \Controller\Front\Controller
{
	public function index()
	{
		$this->getView()->setDefine("header", "outline/_share_header.html");
		$this->getView()->setDefine("footer", "outline/_share_footer.html");
	}
}