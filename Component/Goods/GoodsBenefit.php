<?php

namespace Component\Goods;

use App;
use Request;

class GoodsBenefit extends \Bundle\Component\Goods\GoodsBenefit
{
	/**
     * 상품 할인 데이터 재설정 ( 프론트시용)
     *
     * @param array $goodsData 상품 데이터,array $benefitData
     *
     * @return array 상품 데이터
     *
     */
    public function goodsDataFrontConvert($goodsData,$benefitData=null)
	{

		
		$getData = parent::goodsDataFrontConvert($goodsData,$benefitData);
	
		/* 웹앤모바일 튜닝 - 2020-07-05, 정기배송, 또는 정기배송 임시 데이터인 경우 할인율 반영 */
		if ($getData['goodsNo'] && $getData['siteKey'] && $getData['isGift']) { // 사은품인 경우 가격을 0으로 조정
			$getData['goodsPrice'] = 0;
		}
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$conf = $subscription->getCfg();


		if ($getData['cartType'] == 'cartSubAdmin' || $getData['cartType'] == 'cartSub') { 
			$dbTable = ($getData['cartType'] == 'cartSubAdmin')?"wm_subCartAdmin":"wm_subCart";
			$sql = "SELECT * FROM {$dbTable} WHERE siteKey = ? AND memNo = ? AND isTogetherGoods = '0'";
			$row = $this->db->query_fetch($sql, ["si", $getData['siteKey'], $getData['memNo']], false);
			if ($row) {
				$conf = $subscription->getCfg([$row['goodsNo']]);
			}
		}

		//gd_debug($conf);
		$discounts = $conf['deliveryEaDiscount'];


		$discount = $seq = 0;
		if ($getData['cartType'] == 'cartSubAdmin') { // 정기배송 임시 데이터 
			$sql = "SELECT isTemp, seq, deliveryEa FROM wm_subCartAdmin WHERE sno = ?";
			$row = $this->db->query_fetch($sql, ["i", $getData['sno']], false);	

			
			if ($row['seq'] > 0) {
				$seq = $row['seq'];
				if ($row['seq'] == 9999) {
					$discount = 0;
				} else {
					$index = $row['seq'];
					if ($conf['deliveryEaDiscountType'] == 'cycle' && $row['deliveryEa'] > 0) {
						if (count($discounts) < $row['deliveryEa']) {
							$row['deliveryEa'] = count($discounts);
						}
						if(!empty($row['deliveryEa'])){
						$index = $row['seq'] % $row['deliveryEa'];
						$discount = $discounts[$index];
						}
					} else {
						$discount = $discounts[$index];				
						if ($row['seq'] > 0 && empty($discount)) {						
							$discount = $discounts[count($discounts) - 1];
						}
					}
				}
			} else {
				$discount = $discounts[0];
			}
						/* 배송비 이벤트가 있는 경우 */
			if ($conf['useFirstDelivery'] && $conf['firstDeliverySno']) {
			
				$getData['deliverySno'] = $conf['firstDeliverySno'];
			}
		} else if ($getData['cartType'] == 'cartSub') { // 정기배송 1회차 할인율 적용 
			$discount = $discounts[0];
			
			/* 배송비 이벤트가 있는 경우 */
			if ($conf['useFirstDelivery'] && $conf['firstDeliverySno']) {
			
				$getData['deliverySno'] = $conf['firstDeliverySno'];
			}
		} // endif 



		if ($discount > 0 && ($getData['cartType'] == 'cartSubAdmin' || $getData['cartType'] == 'cartSub')) {
			$getData['goodsPermissionPriceStringFl']="n";
			//정기결제시에는 중복할인안되며 정기결제할인만 되도록 값강제설정종료
			$getData['goodsDiscount']=0;
			$getData['goodsDiscountUnit']="percent";
			//정기결제시에는 중복할인안되며 정기결제할인만 되도록 값 강제설정시작

			if ($discount > 0) {
				if ($getData['goodsDiscountFl'] == 'n') {
					$getData['goodsDiscountFl'] = 'y';
					$getData['goodsDiscount'] = $discount;
					$getData['goodsDiscountUnit'] = "percent";
					$getData['fixedGoodsDiscount'] = "option^|^text";																			
				} else {
					if ($getData['goodsDiscountUnit'] == 'percent') {
						$getData['goodsDiscount'] += $discount;
						$getData['fixedGoodsDiscount'] = "option^|^text";		
					}
					/*else if($getData['goodsDiscountUnit'] == 'price'){
						$discountPercent = $discount / 100;

						$configTrunc = gd_policy('basic.trunc');
						$goodsConfigTrunc = $configTrunc['goods']; // 상품금액절사

						$dc=gd_number_figure(($goodsData['goodsPrice'] * $discountPercent), "percent", $goodsConfigTrunc['unitRound']);

						$getData['goodsDiscount']=$goodsData['goodsPrice'] - gd_number_figure(($goodsData['goodsPrice'] * $discountPercent), $goodsConfigTrunc['unitPrecision'], $goodsConfigTrunc['unitRound']);
						
					}// endif 
					*/
				} // endif 
				$getData['goodsDiscountGroup'] = 'all';
			} // endif 
		} // endif 
		
		if ($getData['siteKey'] && $getData['goodsNo']) {
			$sql = "SELECT shortDescription FROM " . DB_GOODS . " WHERE goodsNo = ?";
			$row = $this->db->query_fetch($sql, ["i", $getData['goodsNo']], false);
			$getData['shortDescription'] = $row['shortDescription'];
		}
		
		/* 1회차 배송비 이벤트가 있는 경우 */
		//if (($getData['cartType'] == 'cartSubAdmin' || $getData['cartType'] == 'cartSub') && empty($seq)) { 	
		if (($getData['cartType'] == 'cartSubAdmin' || $getData['cartType'] == 'cartSub')) { 		
			if ($conf['useFirstDelivery']) {
				$getData['deliverySno'] = $conf['firstDeliverySno'];
			}
		} // endif 
		
				//gd_debug($discount);
		//if(\Request::getRemoteAddress()=="112.145.36.156"){
		//카드등록 여부 시작
		$memNo=\Session::get("member.memNo");
		$mrow = $this->db->fetch("select count(idx) as cnt from wm_subCards where memNo='$memNo'");
		$getData['subscriptionCardCnt']=$mrow['cnt'];
		//카드등록 여부 종료
		//}
		
		/* 튜닝 END */
	//if(\Request::getRemoteAddress()=="112.146.205.124"){
	
			/*$getData['goodsDiscount']=0;
			$getData['goodsDiscountFl']='n';
			$getData['goodsDiscountUnit']="";
			$getData['goodsDiscountGroup']="group";
			$getData['goodsPermission']="group";
			$getData['goodsPermissionGroup']=1;
			$getData['orderCnt']=0;
			$getData['goodsPrice']=100000;
		*/
			//gd_debug($getData);
			
			
			
		//}
		return $getData;
	}
}