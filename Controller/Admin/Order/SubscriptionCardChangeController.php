<?php
namespace Controller\Admin\Order;

use App;
use Request;

/**
* 정기결제 카드 변경
* 
* @author webnmobile
*/
class SubscriptionCardChangeController extends \Controller\Admin\Controller 
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
		
		$list = $subscription->getCards($info['memNo']);
		$this->setData("idxCard", $info['idxCard']);
		$this->setData("idx", $idx);
		$this->setData("list", $list);
    }
}