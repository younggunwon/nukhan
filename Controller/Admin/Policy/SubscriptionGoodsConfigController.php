<?php

namespace Controller\Admin\Policy;

use App;
use Request;

/**
* 정기결제 상품개별 설정 
*
* @author webnmobile
*/
class SubscriptionGoodsConfigController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
		$goodsNo = Request::get()->get("goodsNo");
		if (!$goodsNo) {
			return $this->js("alert('잘못된 접근입니다.');self.close();");
		}
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$db = App::load(\DB::class);
		
		$cfg = $subscription->getCfg();
		
		$yoils = $subscription->getYoils();
		$this->setData("yoils", $yoils);
		
		$sql = "SELECT * FROM wm_subGoodsConf WHERE goodsNo = ?";
		$row = $db->query_fetch($sql, ["i", $goodsNo], false);
		if ($row) {
			$cfg['deliveryYoils'] = $row['deliveryYoils']?explode(",", $row['deliveryYoils']):[];
			$cfg['deliveryCycle'] = $row['deliveryCycle']?explode(",", $row['deliveryCycle']):[];
			$cfg['deliveryEa'] = $row['deliveryEa']?explode(",", $row['deliveryEa']):[];
			$cfg['deliveryEaDiscount'] = $row['deliveryEaDiscount']?explode(",", $row['deliveryEaDiscount']):[];
			$cfg['deliveryEaDiscountType'] = gd_isset($row['deliveryEaDiscountType'], 'cycle');
			$cfg['useFirstDelivery'] = $row['useFirstDelivery']?1:0;
			$cfg['firstDeliverySno'] = gd_isset($row['firstDeliverySno'], 0);
			$cfg['useConfig'] = $row['useConfig']?1:0;
		}
		
		$this->setData($cfg);
		$this->setData("goodsNo", $goodsNo);
		
		/* 배송정책 */
		$sql = "SELECT sno, method, description FROM " . DB_SCM_DELIVERY_BASIC . " ORDER BY sno";
		$deliveryList = $db->query_fetch($sql);
		$this->setData("deliveryList", gd_isset($deliveryList, []));
	}
}