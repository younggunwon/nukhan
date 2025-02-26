<?php

namespace Component\Subscription;

use App;
use Session;
use Exception;
use Component\Subscription\CartSubAdmin;

/**
* 정기결제 스케줄 관련 
*
* @package Component\Subscription
* @author webnmobile
*/
class Schedule extends \Component\Subscription\Subscription
{
	/**
	* 오늘자 Timestamp 
	*
	* @return Integer
	*/
	public function getToday()
	{
		return strtotime(date("Ymd"));
	}
	
	/**
	* 첫배송일 추출 
	*
	* @deliveryStamp 첫 배송일
	* @return Integer
	*/
	public function getFirstDay($deliveryStamp = 0, $gnos = [])
	{
		$cfg = $this->getCfg($gnos);
		$today = $this->getToday();
		$stamp = ($cfg['payDayBeforeDelivery'] > 0)?strtotime("+{$cfg['payDayBeforeDelivery']} day", $today):$today;

		// 2024-10-28 wg-eric timestmap 형식인지 확인
		if(is_numeric($deliveryStamp) && (int)$deliveryStamp == $deliveryStamp) {
		} else {
			$deliveryStamp = strtotime($deliveryStamp);
		}
		
		//if ($deliveryStamp > 0 && $deliveryStamp > $stamp) $stamp = $deliveryStamp;
		if ($deliveryStamp > 0) $stamp = $deliveryStamp;
		
		/* 공휴일 체크 S */
		$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
		$row = $this->db->query_fetch($sql, ["i", $stamp], false);
		if ($row && $row['isHoliday']) {
			/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
			if ($row['replaceStamp']) {
				$stamp = $row['replaceStamp'];
			} else {
				while (true) {
					$stamp = strtotime("+1 day", $stamp);
					/* 공휴일 체크 S */
					$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
					$row = $this->db->query_fetch($sql, ["i", $stamp], false);
					if ($row) {
						if ($row['isHoliday']) {
							/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
							if ($row['replaceStamp']) {
								$stamp = $row['replaceStamp'];
								break;
							} else {
								$stamp = strtotime("+1 day", $stamp);
								continue;
							}
						} else {
							break;
						}
					} // endif 
				} // endwhile 
			}
		}
		/* 공휴일 체크 E */
					
		$yoil = date("w", $stamp);
		if ($cfg['deliveryYoils']) {
			while (!in_array($yoil, $cfg['deliveryYoils'])) {
				$stamp = strtotime("+1 day", $stamp);
				/* 공휴일 체크 S */
				$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
				$row = $this->db->query_fetch($sql, ["i", $stamp], false);
				if ($row && $row['isHoliday']) {
					/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
					if ($row['replaceStamp']) {
						$stamp = $row['replaceStamp'];
					} else {
						$stamp = strtotime("+1 day", $stamp);
						continue;
					}
				}
				
				/* 공휴일 체크 E */
				$yoil = date("w", $stamp);
			}
		}
		
		return $stamp;
	}
	
	/**
	* 정기결제 스케줄 목록 
	*
	* @param Array $params 
	* 					deliveryStamp 첫배송일 
	*					deliveryPeriod 배송 주기
	*					deliveryEa 배송 횟수 
	*					cartSno 장바구니 sno 
	*
	* @param Boolean scheduleOnly 스케줄만 추출 여부
	* 
	* @return Array
	*/
	public function getList($params = [], $scheduleOnly = false)
	{
		if (!$params['deliveryPeriod'] || !$params['deliveryEa'])
			return []; 
		
		/* cartSno 있는 경우 주문상품 추출 S */
		$goodsList = $gnos = [];
		if ($params['cartSno']) {
			$bind = $where = [];
			foreach ($params['cartSno'] as $sno) {
				$where[] = "?";
				$this->db->bind_param_push($bind, "i", $sno);
			
			}
			
			$sql = "SELECT sno, goodsNo, goodsCnt, optionSno, addGoodsNo, addGoodsCnt, optionText, isTogetherGoods FROM wm_subCart WHERE sno IN (" . implode(",", $where) . ") ORDER BY sno";
			$goodsList = $this->db->query_fetch($sql, $bind);
			$goodsList = gd_isset($goodsList, []);

			
			if ($goodsList) {
				foreach ($goodsList as $g) {
					if (!$g['isTogetherGoods']) {
						$gnos[] = $g['goodsNo'];

						// 2024-02-01 wg-eric 정기결제 설정 가져오기
						$sql = "
							SELECT * FROM wm_subGoodsConf 
							WHERE goodsNo = '".$g['goodsNo']."'
						";
						$result = $this->db->slave()->query_fetch($sql, null, false);
						if($result) $subGoodsConf = $result;
					}
				}
			}
		}
		//$cfg = $this->getCfg($gnos);
		/* cartSno 있는 경우 주문상품 추출 E */
		$conf = $this->getCfg($gnos);
		$firstStamp = $this->getFirstDay($params['deliveryStamp'], $gnos);
		
		$deliveryPeriod = explode("_", $params['deliveryPeriod']);
		$period = $deliveryPeriod[0];
		$periodUnit = $deliveryPeriod[1]?$deliveryPeriod[1]:"week";
	
		$no = 1;
		$stampList = [$firstStamp];
		while (true) {
			if ($no > 365)
				break;
			
			if (count($stampList) >= $params['deliveryEa'])
				break;
			
			// 2024-02-01 wg-eric 정기배송 상품 자동 전환시 배달예정일 수정
			if($subGoodsConf['wgAutoChangeGoodsFl'] == 'y' && $subGoodsConf['wgAutoChangeGoodsNo'] && $subGoodsConf['wgAutoChangeGoodsDay'] >= 1) {
				if($no == 1) {
					$priod = $subGoodsConf['wgAutoChangeGoodsDay'];
				} else {
					$priod = $subGoodsConf['wgAutoChangeGoodsDay'] + ($period * ($no-1));
				}
			} else {
				$priod = $period * $no;
			}
			$str = "+{$priod} {$periodUnit}";
			$stamp = strtotime($str, $firstStamp);
			$no++;
			
			/* 공휴일 체크 S */
			//$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
			//$row = $this->db->query_fetch($sql, ["i", $stamp], false);
			//if ($row && $row['isHoliday']) {
				/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
				//if ($row['replaceStamp']) {
				//	$stamp = $row['replaceStamp'];
				//} else {
				//	continue;
				//}
			//}
			
			while(true){
			
				$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
				$row = $this->db->query_fetch($sql, ["i", $stamp], false);
				if ($row && $row['isHoliday']) {
					/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
					if ($row['replaceStamp']) {
						$stamp = $row['replaceStamp'];
					} else {
						
						$stamp = strtotime("+1 day", $stamp);

					}
				}else{
					break;
				}

			}
			/* 공휴일 체크 E */
			$yoil = date("w", $stamp);
			
			if ($conf['deliveryYoils']) {
				while (!in_array($yoil, $conf['deliveryYoils'])) {
					$stamp = strtotime("+1 day", $stamp);
					$yoil = date("w", $stamp);
					/* 공휴일 체크 S */
					$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
					$row = $this->db->query_fetch($sql, ["i", $stamp], false);
					if ($row && $row['isHoliday']) {
						/* 대체배송일이 있는 경우 대체해 주고 없는 경우 건너 뜀 */
						if ($row['replaceStamp']) {
							$stamp = $row['replaceStamp'];
							$yoil = date("w", $stamp);
						} else {
							$stamp = strtotime("+1 day", $stamp);
							$yoil = date("w", $stamp);
							continue;
						}
					}else{
						break;
					}
					
					/* 공휴일 체크 E */
					
				}
			}
			$stampList[] = $stamp;
		} // endwhile





		sort($stampList, SORT_NUMERIC);
		
		if ($scheduleOnly)
			return $stampList;
		

		/* 각 결제 할인율 정보 추출 S */



		$list = $this->getTmpOrderList($goodsList, $params['deliveryEa']);


		if ($list) {			
			$today = $this->getToday();
			foreach ($list as $k => $v) {
				$stamp = $stampList[$k];
				$payStamp = $stamp - (60 * 60 * 24 * $conf['payDayBeforeDelivery']);
				if ($payStamp < $today) $payStamp = $today;
				$smsStamp = $stamp - (60 * 60 * 24 * $conf['smsPayBeforeDay']);
				if ($smsStamp < $today) $smsStamp = $today;

				if ($v['totalDiscount'] > 0){
					$rate = round(($v['totalDiscount'] / $v['totalGoodsPrice']) * 100);
				}else 
					$rate = 0;
				
				$v['cnt'] = $k+1;
				$v['payStamp'] = $payStamp;
				$v['smsStamp'] = $smsStamp;
				$v['deliveryStamp'] = $stamp;
				$v['payDate'] = date("Y.m.d", $payStamp);
				$v['smsDate'] = date("Y.m.d", $smsStamp);
				$v['deliveryDate'] = date("Y.m.d", $stamp);
				$v['pYoilStr'] = $this->getYoilStr($payStamp);
				$v['sYoilStr'] = $this->getYoilStr($smsStamp);
				$v['dYoilStr'] = $this->getYoilStr($stamp);
				$v['dcPrice'] = $rate;

				// 2024-02-01 wg-eric 정기결제 설정 가져오기
				$v['subGoodsConf'] = $subGoodsConf;
				
				$list[$k] = $v;
			}
		} // endif 
		/* 각 결제 할인율 정보 추출 E */
		
		return gd_isset($list, []);
	}
	
	/**
	* 임시 주문 테이블 추가 하여 결제 정보 추출 
	* 
	* @param Array $list 
	* 					goodsNo 상품번호
	*					optionSno 옵션번호 
	*			        goodsCnt 수량 
	*                   addGoodsNo 추가상품번호
	*  					addGoodsCnt 추가상품수량 
	*                   optionText 텍스트옵션 정보 
	* @param Integer $deliveryEa 배송횟수
	* @param Integer $memNo 회원번호 
	* 
	* @return Array
	*/
	public function getTmpOrderList($list= [], $deliveryEa = 1, $memNo = 0)
	{
		$detailList = [];
		$conf = $this->getCfg();
		if ($list) {
			$gnos = array_column($list, "goodsNo");

		
			$conf = $this->getCfg($gnos);

			if (empty($memNo)) $memNo = Session::get("member.memNo", 0);
			try {
				$this->db->begin_tran();


				/* 회차별 장바구니 데이터 추가 S */
				for ($i = 0; $i < $deliveryEa; $i++) {
					/* 임시 수기 장바구니 삭제 S */
					$bind = [
						'si',
						Session::get("siteKey"),
						$memNo,
					];
					$this->db->set_delete_db("wm_subCartAdmin", "siteKey = ? AND memNo = ? AND isTemp = 1", $bind);
					/* 임시 수기 장바구니 삭제 E */
					$cartSnos = [];
					$cart = new CartSubAdmin($memNo);
					

					foreach ($list as $li) {

						
						$sql="select goodsPrice from ".DB_GOODS." where goodsNo=?";
						$goodsInfo = $this->db->query_fetch($sql,['i',$li['goodsNo']],false);
						$li['goodsPrice']=$goodsInfo['goodsPrice'];

						
						$li['addGoodsNo'] = $li['addGoodsNo']?json_decode(gd_htmlspecialchars_stripslashes($li['addGoodsNo']), true):[];
						$li['addGoodsCnt'] = $li['addGoodsCnt']?json_decode(gd_htmlspecialchars_stripslashes($li['addGoodsCnt']), true):[];
						$li['optionText'] = $li['optionText']?json_decode(gd_htmlspecialchars_stripslashes($li['optionText']), true):[];

						
						try{
							$cartSnos[] = $cart->saveGoodsToCart($li);
						}catch(Exception $e){
							//gd_debug($e);
						}
					} // endforeach 

					//gd_debug($cartSnos);
					if ($cartSnos) {
						foreach ($cartSnos as $sno) {
							$param = [
								'isTemp = ?',
								'seq = ?',
								'deliveryEa = ?',
							];
						 
							$bind = [
								'iiii', 
								1,
								$i,
								gd_isset($this->deliveryEa, $deliveryEa),
								$sno,
							];
							
							$this->db->set_update_db("wm_subCartAdmin", $param, "sno = ?", $bind);
						} // endforeach 
						
						$cartInfo=$cart->getCartGoodsData($cartSnos);

						
						$detailList[] = [
							'totalGoodsPrice' => $cart->totalGoodsPrice,
							'totalDeliveryCharge' => $cart->totalDeliveryCharge,
							'totalMileage' => $cart->totalGoodsMileage,
							'totalDiscount' => ($cart->totalGoodsDcPrice + $cart->totalMemberDcPrice + $cart->totalMemberOverlapDcPrice + $cart->totalMyappDcPrice + $cart->totalCouponGoodsDcPrice),
							'totalSettlePrice' => $cart->totalSettlePrice,		
						];

						//gd_debug($detailList);
					} // endif 
					
				} // endif 
				/* 회차별 장바구니 데이터 추가 E */
				$this->db->commit();
			} catch (Exception $e) {
				$this->db->rollback();
			}	
		} // endif 
		
		return gd_isset($detailList, []);
	}


	//상품 정기결제 할인율
	public function goodsRatio($goodsNo)
	{
		$gnos=array();
		if(empty($goodsNo))
			return false;

		$strSQL="select useSubscription from ".DB_GOODS." where goodsNo=?";
		
		$this->db->bind_param_push($arrBind,'i',$goodsNo);
		$row =$this->db->query_fetch($strSQL,$arrBind,false);

		if(empty($row['useSubscription']))
			return false;

		$gnos[]=$goodsNo;

		$subConf = $this->getCfg($gnos);

		//배송주기
		$cycle=$subConf['deliveryCycle'];
		$delivery_cycle=array();
		$period=array();

		foreach($cycle as $k =>$t){
			
			$tmp=explode("_",$t);
			
			if($tmp[1]=="week"){
				$delivery_cycle[$k]=$tmp[0]."주";
			}else if($tmp[1]=="day"){
				$delivery_cycle[$k]=$tmp[0]."일";
			}else{
				$delivery_cycle[$k]=$tmp[0]."달";
			}

			$period[$k]=$t;
		}
		
		//배송횟수
		$ea=$subConf['deliveryEa'];

		//배송회차별 할인율
		$discount=$subConf['deliveryEaDiscount'];


		unset($arrBind);
		$sql="select goodsPrice from ".DB_GOODS." where goodsNo='$goodsNo'";
		$this->db->bind_param_push($arrBind,'i',$goodsNo);
		$goodsInfo=$this->db->query_fetch($sql,$arrBind,false);

		$delivery_dc=array();
		$delivery_dc_ratio=array();


		// 절사 내용
        $tmp['trunc'] = \Globals::get('gTrunc.goods');

		///절삭기준 변경시작2022.04.06민트웹
		$tmp['trunc']['unitRound']=$subConf['unitRound'];
		$tmp['trunc']['unitPrecision']=$subConf['unitPrecision'];

		///절삭기준 변경종료
		foreach($discount as $key =>$v){

			$dcTmp = $goodsInfo['goodsPrice']*$v/100;




			$dc = gd_number_figure($dcTmp , $tmp['trunc']['unitPrecision'], $tmp['trunc']['unitRound']);
						//if(\Request::getRemoteAddress()=="112.146.205.124"){
				//gd_debug($dcTmp);
				//gd_debug($tmp);
				//gd_debug($v);
				//gd_debug($dc);
			//}
			$delivery_dc[$key]=$dc;
			$delivery_dc_ratio[$key]=$v;
			
		}

		$list=array();
		$list['period']=$period;
		$list['delivery_cycle']=$delivery_cycle;
		$list['delivery_dc']=$delivery_dc;
		$list['delivery_dc_ratio']=$delivery_dc_ratio;
		$list['goodsPrice']=(int)$goodsInfo['goodsPrice'];

	
		/* 각 결제 할인율 정보 추출 E */
		return gd_isset($list, []);

	}

}
