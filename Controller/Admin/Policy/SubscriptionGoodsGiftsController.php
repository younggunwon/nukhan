<?php

namespace Controller\Admin\Policy;

use App;
use Request;

/**
* 정기배송 상품별 사은품 설정
*
* @author webnmobile
*/
class SubscriptionGoodsGiftsController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$db = App::load(\DB::class);
		
		$goodsNo = Request::get()->get("goodsNo");
		if (!$goodsNo) {
			return $this->js("alert('잘못된 접근입니다.');self.close();");
		}
		
		$list = [];
		$sql = "SELECT * FROM wm_subGoodsGifts WHERE rootGoodsNo = ? ORDER BY orderCnt, listOrder, idx";
		$tmp = $db->query_fetch($sql, ["i", $goodsNo]);
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
		
		$this->setData("goodsNo", $goodsNo);
	}
}