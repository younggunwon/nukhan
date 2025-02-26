<?php

namespace Controller\Admin\Order;

use App;
use Request;

/**
* 배송 횟수 및 주소 변경 
*
* @author webnmobile
*/
class SubscriptionChangeDeliveryController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
        if (!$idx = Request::get()->get("idx"))
            return $this->js("alert('잘못된 접근입니다.');self.close();");
        
        $subscription = App::load(\Component\Subscription\Subscription::class);
		$db = App::load(\DB::class);
		
        $info = $subscription->getApplyInfo($idx);
		if (!$info) {
			return $this->js("alert('신청정보가 존재하지 않습니다.');self.close();");
		}
		
		$sql = "SELECT g.goodsNo FROM wm_subApplyGoods AS a 
								INNER JOIN " . DB_GOODS . " AS g ON a.goodsNo = g.goodsNo WHERE a.idxApply = ? LIMIT 0, 1";
								
		$row = $db->query_fetch($sql, ["i", $idx], false);
		$goodsNo = $row['goodsNo'];

		$deliveryPeriod = $info['deliveryPeriod'][0]."_".$info['deliveryPeriod'][1];
		$this->setData("deliveryPeriod", $deliveryPeriod);
		$conf = $subscription->getCfg([$goodsNo]);
		$this->setData('conf', $conf);
		
		$this->setData($info['address']);
		$this->setData("autoExtend", $info['autoExtend']);
	}
}