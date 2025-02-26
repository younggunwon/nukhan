<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Exception;
/**
* 배송 수령일 변경 달력 
*
* @author webnmobile
*/
class CalendarChangeDateController extends \Controller\Front\Controller
{
	public function index()
	{
		$this->getView()->setDefine("header", "outline/_share_header.html");
		$this->getView()->setDefine("footer", "outline/_share_footer.html");
		
		try {
			$in = Request::request()->all();
			$idx = $in['idx'];
			if (!$idx)
				throw new Exception("잘못된 접근입니다.");
			
			$this->setData($in);
		} catch (Exception $e) {
			$this->js("alert('".$e->getMessage() . "');parent.wmLayer.close();");
		}
	}
}