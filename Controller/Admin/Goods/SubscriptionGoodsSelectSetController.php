<?php

namespace Controller\Admin\Goods;

use App;
use Request;

/**
* 정기배송 상품 개별 설정 
*
* @author webnmobile
*/
class SubscriptionGoodsSelectSetController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
		$arrGoodsNo = Request::post()->get("goodsNoList");
		$this->setData("arrGoodsNo",$arrGoodsNo);

		$goodsNo_List=explode(",",$arrGoodsNo);

		$goodsNo=$goodsNo_List[0];

		if (!$goodsNo) {
			return $this->js("alert('잘못된 접근입니다.');self.close();");
		}
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$db = App::load(\DB::class);
		$cfg = $subscription->getCfg();

		$goods_where = "IN ('".implode("','",$goodsNo_List)."')";
		$sql="select goodsNm,goodsNo,goodsPrice from ".DB_GOODS." where goodsNo $goods_where";
		$rows = $db->query_fetch($sql);

		$goodsNm=[];
		foreach($rows as $key =>$t){
			$goodsNm[$t['goodsNo']]="상품명:".$t['goodsNm'].", 상품번호:".$t['goodsNo'].", 판매가:".number_format($t['goodsPrice'])."원";
		}
		$this->setData("goodsInfo",$goodsNm);
		//gd_debug($goodsNm);
		/*
		$goods = $subscription->getGoods($goodsNo);

		if (!$goods) {
			return $this->js("alert('상품이 존재하지 않습니다.');self.close();");
		}
		$goods = $goods[0];
		
		$sql = "SELECT togetherGoodsNo, showSubscriptionLink, subLinkGoodsNo, subLinkGoodsNo2, subLinkGoodsNo3, subLinkGoodsNo4, subLinkGoodsNo5, subLinkButtonNm FROM " . DB_GOODS . " WHERE goodsNo = ?";
		$row = $db->query_fetch($sql, ["i", $goodsNo], false);
		$goods = $row?array_merge($goods, $row):$goods;
		$this->setData("goods", $goods);
		*/

		
		
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
			$cfg['min_order']=$row['min_order'];
		}

		
		
		$this->setData($cfg);
		$this->setData("goodsNo", $goodsNo);

		
		
		/* 배송정책 */
		$sql = "SELECT sno, method, description FROM " . DB_SCM_DELIVERY_BASIC . " ORDER BY sno";
		$deliveryList = $db->query_fetch($sql);
		$this->setData("deliveryList", gd_isset($deliveryList, []));
	}
}