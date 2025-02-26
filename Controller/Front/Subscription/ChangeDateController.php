<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Session;
use Exception;

/**
* 배송일 변경 
*
* @author webnmobile
*/
class ChangeDateController extends \Controller\Front\Controller
{
	public function index()
	{
		$this->getView()->setDefine("header", "outline/_share_header.html");
		$this->getView()->setDefine("footer", "outline/_share_footer.html");
		try {
			$idxApply = Request::get()->get("idxApply");
			if (!$idxApply) 
				throw new Exception("잘못된 접근입니다.");		
			
			if (!gd_is_login())
				throw new Exception("로그인이 필요한 페이지 입니다.");
			
			$memNo = Session::get("member.memNo");
			$subscription = App::load(\Component\Subscription\Subscription::class);
			
			/* 정기결제 신청 정보 추출 */
			$info = $subscription->getApplyInfo($idxApply);
			//gd_debug(date("Y-m-d",$info['utilDeliveryStamp']));
			if (!$info)
				throw new Exception("신청정보가 존재하지 않습니다.");
			
			if ($info['memNo'] != $memNo)
				throw new Exception("본인이 신청건만 변경하실 수 있습니다.");
			
			// 2024-07-16 wg-eric 리스트 1개만 가져오기
			//$newInfo = $info;
			//if($info['schedules']) {
				//unset($newInfo['schedules']);
				//foreach($info['schedules'] as $key => $val) {
					//$newInfo['schedules'][] = $val;
					//if($info['prevDeliveryStamp'] < $val['deliveryStamp'] && $val['status'] == 'ready') {
						//break;
					//}
				//}
			//}

			$this->setData("info", $info);
			$this->addScript(["wm/layer.js"]);
		} catch (Exception $e) {
			$this->js("alert('".$e->getMessage()."');parent.wmLayer.close();");
		}
	}
}