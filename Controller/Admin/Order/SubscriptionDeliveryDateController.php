<?php

namespace Controller\Admin\Order;

use App;
use Request;

/**
* 정기 배송일 변경 
*
* @author webnmobile
*/
class SubscriptionDeliveryDateController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
        if (!$idx = Request::get()->get("idx"))
            return $this->js("alert('잘못된 접근입니다.');self.close();");
        
        $subscription = App::load(\Component\Subscription\Subscription::class);
		
		$info = $subscription->getSchedule($idx);
		if (!$info) {
			return $this->js("alert('신청정보가 존재하지 않습니다.');self.close();");
		}
		
		$this->setData("idx", $idx);
		$this->setData("deliveryStamp", $info['deliveryStamp']);
	}
}