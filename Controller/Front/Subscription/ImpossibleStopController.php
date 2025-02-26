<?php

namespace Controller\Front\Subscription;

/**
* 그만받기 불가 안내 
*
* @author webnmobile
*/
class ImpossibleStopController extends \Controller\Front\Controller
{
	public function index()
	{
		$min_order=\Request::get()->get("min_order");
		$this->setData("min_order",$min_order);
		$this->getView()->setDefine("header", "outline/_share_header.html");

		$this->getView()->setDefine("footer", "outline/_share_footer.html");
	}
}