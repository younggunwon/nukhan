<?php

namespace Controller\Admin\Policy;

use App;

/**
* 정기배송 사은품 설정 
*
* @author webnmobile
*/
class SubscriptionGiftsController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$db = App::load(\DB::class);
		
		$list = [];
		$sql = "SELECT * FROM wm_subGifts ORDER BY orderCnt, listOrder, idx";
		$tmp = $db->query_fetch($sql);
		if ($tmp) {
			foreach ($tmp as $k => $v) {
				$goods = $subscription->getGoods([$v['goodsNo']]);
				if ($goods) {
					$v['goods'] = $goods[0];
				}
				
				$list[$v['orderCnt']][] = $v;
			}
		} 
		
		$this->setData("list", $list);
	}
}