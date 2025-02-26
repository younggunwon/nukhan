<?php

namespace Controller\Admin\Goods;

use App;
use Request;
use Framework\Utility\SkinUtils;

/**
* 정기배송 상품 개별 설정 
*
* @author webnmobile
*/
class SubscriptionGoodsSetController extends \Controller\Admin\Controller
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
		$goods = $subscription->getGoods($goodsNo);
		if (!$goods) {
			return $this->js("alert('상품이 존재하지 않습니다.');self.close();");
		}
		$goods = $goods[0];
		$sql = "SELECT togetherGoodsNo, showSubscriptionLink, subLinkGoodsNo, subLinkGoodsNo2, subLinkGoodsNo3, subLinkGoodsNo4, subLinkGoodsNo5, subLinkButtonNm FROM " . DB_GOODS . " WHERE goodsNo = ?";
		$row = $db->query_fetch($sql, ["i", $goodsNo], false);
		$goods = $row?array_merge($goods, $row):$goods;
		$this->setData("goods", $goods);
		
		
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

			// 2024-01-19 wg-eric 상품 전환 추가 - start
			$cfg['wgAutoChangeGoodsFl']=$row['wgAutoChangeGoodsFl'];
			$cfg['wgAutoChangeGoodsDay']=$row['wgAutoChangeGoodsDay'];

			if($row['wgAutoChangeGoodsNo']) {
				$Goods = \App::load('\\Component\\Goods\\Goods');	
				$goodsData = $Goods->getGoodsInfo($row['wgAutoChangeGoodsNo']);
				$cfg['wgAutoChangeGoodsData']['goodsNo'] = $goodsData['goodsNo'];
				$cfg['wgAutoChangeGoodsData']['goodsNm'] = $goodsData['goodsNm'];
				$cfg['wgAutoChangeGoodsData']['goodsPrice'] = $goodsData['goodsPrice'];
				$goodsImage = $Goods->getGoodsImage($goodsData['goodsNo'], 'main')[0];
				$cfg['wgAutoChangeGoodsData']['goodsImageSrc'] = SkinUtils::imageViewStorageConfig($goodsImage['imageName'], $goodsData['imagePath'], $goodsData['imageStorage'], 100, 'goods')[0];
				$cfg['wgAutoChangeGoodsData']['wgAutoChangeOptionSno'] = $row['wgAutoChangeOptionSno'];

				$sql		= "select sno,concat(optionValue1,optionValue2,optionValue3,optionValue4,optionValue5) as optionName from ".DB_GOODS_OPTION." where goodsNo=?";
				$optionRow	= $db->query_fetch($sql,['i',$goodsData['goodsNo']]);
				
				$optionInfo = [];
				$addInfo	= [];
				
				foreach($optionRow as $key => $t){
					if($t['sno']) {
						$optionInfo['sno'][]=$t['sno'];
						if($t['optionName']) {
							$optionInfo['optionName'][]=$t['optionName'];
						} else {
							$optionInfo['optionName'][]='없음';
						}
					}
				}
				$cfg['wgAutoChangeGoodsData']['optionInfo'] = $optionInfo;
			}
			// 2024-01-19 wg-eric 상품 전환 추가 - end
		}

		
		
		$this->setData($cfg);
		$this->setData("goodsNo", $goodsNo);
		
		/* 배송정책 */
		$sql = "SELECT sno, method, description FROM " . DB_SCM_DELIVERY_BASIC . " ORDER BY sno";
		$deliveryList = $db->query_fetch($sql);
		$this->setData("deliveryList", gd_isset($deliveryList, []));

		$this->getView()->setPageName('goods/subscription_goods_set_test.php');
	}
}