<?php

namespace Widget\Front\Subscription;

use App;
use Request;

/**
* 정기결제 일반상품 링크 체크 
*
* @author webnmobile
*/
class LinkGoodsWidget extends \Widget\Front\Widget
{
	public function index()
	{
		$db = App::load(\DB::class);
		$goodsNo = $this->getData("goodsNo");
		if ($goodsNo) {
			$sql = "SELECT showSubscriptionLink, subLinkButtonNm, goodsNo FROM " . DB_GOODS . " WHERE ( subLinkGoodsNo = ? OR subLinkGoodsNo2 = ? OR subLinkGoodsNo3 = ? OR subLinkGoodsNo4 = ? OR subLinkGoodsNo5 = ? ) AND showSubscriptionLink = 1 AND useSubscription = 1 LIMIT 0, 1";
			$bind = [
				'iiiii',
				$goodsNo,
				$goodsNo,
				$goodsNo,
				$goodsNo,
				$goodsNo,
			];
			$row = $db->query_fetch($sql, $bind, false);

			$this->setData($row);
		}
	}
}