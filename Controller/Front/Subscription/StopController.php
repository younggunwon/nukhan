<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Session;

/**
* 정기배송 그만 받기
* 
* @author webnmobile
*/
class StopController extends \Controller\Front\Controller
{
	public function index()
	{
		$this->getView()->setDefine("header", "outline/_share_header.html");
		$this->getView()->setDefine("footer", "outline/_share_footer.html");
		try {
			$idx = Request::get()->get("idx");
			if (!$idx)
				throw new Exception("잘못된 접근입니다.");
			
			if (!gd_is_login())
				throw new Exception("로그인이 필요한 페이지 입니다.");
			
			/* 정기결제 신청 정보 추출 */
			$memNo = Session::get("member.memNo");
			$subscription = App::load(\Component\Subscription\Subscription::class);
			$info = $subscription->getApplyInfo($idx);

			
			$cfg=$subscription->getCfg();
			$pause=$cfg['pause'];
			
			$pause_period=explode(",",$pause);
			$this->setData("pause_period",$pause_period);

			if (!$info)
				throw new Exception("신청정보가 존재하지 않습니다.");
			
			if ($info['memNo'] != $memNo)
				throw new Exception("본인이 신청건만 취소할수 있습니다.");
			
			$this->setData("idx", $idx);
			
		} catch (Exception $e) {
			$this->js("alert('".$e->getMessage()."');parent.wmLayer.close();");
		}
	}
}