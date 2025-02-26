<?php

namespace Controller\Admin\Order;

use App;
use Request;

/**
* 스케줄 회차 추가 
*
* @author webnmobile
*/
class SubscriptionAddScheduleController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
        if (!$idx = Request::get()->get("idx"))
            return $this->js("alert('잘못된 접근입니다.');self.close();");
        
        $subscription = App::load(\Component\Subscription\Subscription::class);
        $info = $subscription->getApplyInfo($idx);
		if (!$info) {
			return $this->js("alert('신청정보가 존재하지 않습니다.');self.close();");
		}
		$this->setData("idx", $idx);
	}
}