<?php

namespace Component\Subscription\Traits;

use Component\Subscription\CartSubAdmin;
use Component\Database\DBTableField;
use Exception;
use Request;
use Session;
use App;

/**
* 정기결제 신청관련 
*
* @package Component\Subscription\Traits
* @author webnmobile
*/
trait SubscriptionApply 
{
	private $isAdmin = false;
	
	/**
	* 관리자 모드 여부 
	*
	* @param Boolean $isAdmin 어드민 여부
	* @return $this;
	*/
	public function setAdmin($isAdmin = false)
	{
		$this->isAdmin = $isAdmin;
		
		return $this;
	}
	
	/**
	* 정기결제 신청 목록
	*
	* @param Integer $memNo 회원번호
	* @param Array $arrWhere 검색조건
	* @param Boolean $withPaging 페이징 포함여부 
	* 
	* @return Array
	*/
	public function getApplyList($memNo = null, $arrWhere = [], $withPaging = false, $page = 1, $limit = 10)
	{
		$get = Request::request()->all();
		$bind = $bind2 = [];
		$arrWhere = $arrWhere?$arrWhere:[];
		
		if (empty($memNo) && !$this->isAdmin) {
			$memNo = Session::get("member.memNo");
		}
		
		if(!$this->isAdmin){
			if(!empty($get['searchPeriod'])){
				$selectDate=$get['searchPeriod'];
				if($selectDate==1)$selectDate=0;

				$get['wDate'][0] = date('Y-m-d', strtotime("-$selectDate days"));
				$get['wDate'][1] = date('Y-m-d', strtotime("now"));
			}
		}

		if ($get['wDate']) {
			if ($get['wDate'][0]) {
				$sdate = date("Y-m-d", strtotime($get['wDate'][0]));
				$arrWhere[] = "a.regDt >= ?";
				$this->db->bind_param_push($bind, "s", $sdate);
				$this->db->bind_param_push($bind2, "s", $sdate);
			}
			
			if ($get['wDate'][1]) {
				$edate = date("Y-m-d", strtotime($get['wDate'][1]) + (60 * 60 * 24));
				$arrWhere[] = "a.regDt < ?";
				$this->db->bind_param_push($bind, "s", $edate);
				$this->db->bind_param_push($bind2, "s", $edate);
			}
		}

		// 2024-09-23 wg-eric 배송주기 검색
		if($get['payMethodFl'] == 'period') {
			$arrWhere[] = " a.payMethodFl = 'period' ";

			if ($get['deliveryCycle']) {
				$inArr = [];
				foreach ($get['deliveryCycle'] as $period) {
					$inArr[] = "?";
					$this->db->bind_param_push($bind, "s", $period);
					$this->db->bind_param_push($bind2, "s", $period);
				}
				
				$arrWhere[] = "a.deliveryPeriod IN (" . implode(",", $inArr) . ")";
			}
		} else if($get['payMethodFl'] == 'dayPeriod') {
			$arrWhere[] = " a.payMethodFl = 'dayPeriod' ";

			if($get['dayPeriod']) {
				$arrWhere[] = " a.deliveryDayPeriod = '".$get['dayPeriod']."' ";
			}
		}
		
		if ($get['sopt'] && $get['skey']) {
			switch ($get['sopt']) {
				case "all" : 
					$arrWhere[] = "CONCAT(m.memId, m.memNm, b.orderName, b.receiverName, b.orderPhone, b.orderCellPhone, b.receiverPhone, b.receiverCellPhone, a.idx) LIKE ?";
					break;
				case "name" : 
					$arrWhere[] = "CONCAT(m.memId, m.memNm, b.orderName, b.receiverName) LIKE ?";
					break;
				case "mobile" : 
					$arrWhere[] = "CONCAT(b.orderPhone, b.orderCellPhone, b.receiverPhone, b.receiverCellPhone) LIKE ?";
					break;
				case "goodsNm" : 
				
					break;
				default : 
					$get['sopt'] = $this->db->escape($get['sopt']);
					$arrWhere[] = $get['sopt'] . " LIKE ?";
					break;
					
			}
			if  ($get['sopt'] != 'goodsNm') {
				$this->db->bind_param_push($bind, "s", "%".$get['skey']."%");
				$this->db->bind_param_push($bind2, "s", "%".$get['skey']."%");
			}
		}
		

		/* 신청번호 검색 START */
		if ($this->searchIdxes) {
			$inArr2 = [];
			foreach ($this->searchIdxes as $idx) {
				$inArr2[] = "?";
				$this->db->bind_param_push($bind, "i", $idx);
				$this->db->bind_param_push($bind2, "i", $idx);
			} // endoreach 
			
			$arrWhere[] = " a.idx IN (".implode(",", $inArr2).")";
		}
		/* 신청번호 검색 END */
		
		/* 서비스 이용중 주문건 검색 S */
		$subWhere=[];
		$now=time();

		foreach($get['isCurrentSubOrder'] as $k =>$t){
			if ($t==1) {

				//$arrWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='ready' or sub1.status='pause' OR a.autoExtend = 1)) > 0";
				$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='ready' or (a.autoExtend = 1 and sub1.status!='pause')  and sub1.deliveryStamp>$now)) > 0";
				//$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='ready' or sub1.status='paid' or (a.autoExtend = 1 and sub1.status!='pause')  and sub1.deliveryStamp>$now)) > 0";
			}
			if($t==2){
				$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND sub1.status='pause'  and sub1.deliveryStamp>$now and a.autoExtend=0) > 0";
			}
			if($t==3){
				$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='stop' OR a.autoExtend = 0) and sub1.deliveryStamp>$now) > 0";
			}		
		}


		if(!empty($get['outMember'])){
			$arrWhere[]="(select count(memNo) as cnt from ".DB_MEMBER." mm where mm.memNo=a.memNo)<='0'";
		}

		/*
		if ($get['isCurrentSubOrder']==1) {

			//$arrWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='ready' or sub1.status='pause' OR a.autoExtend = 1)) > 0";
			$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='ready' or sub1.status='paid' or (a.autoExtend = 1 and sub1.status!='pause'))) > 0";
		}
		if($get['isCurrentSubOrder']==2){
			$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND ( sub1.status='pause')) > 0";
		}
		if($get['isCurrentSubOrder']==3){
			$subWhere[] = "(SELECT COUNT(*) FROM wm_subSchedules AS sub1 WHERE sub1.idxApply = a.idx AND (sub1.status='stop' OR a.autoExtend = 0)) > 0";
		}
		*/

		if(count($subWhere)>0){
			$arrWhere[]="(".implode(" or ",$subWhere).")";

			//gd_debug($arrWhere);
		}


		/* 서비스 이용중 주문건 검색 E */
		
		$conds = $arrWhere?implode(" AND ", $arrWhere) . " AND ":"";
		
		if ($this->isAdmin) {
			$conds = $arrWhere?" WHERE " . implode(" AND ", $arrWhere):"";
			$sql = "SELECT a.*, m.memId, m.memNm, m.cellPhone FROM wm_subApplyInfo AS a 
						LEFT JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
						LEFT JOIN wm_subDeliveryInfo AS b ON b.idxApply = a.idx 
					{$conds} ORDER BY a.idx DESC";
		} else {
			$sql = "SELECT a.*, m.memId, m.memNm, m.cellPhone FROM wm_subApplyInfo AS a LEFT JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo WHERE {$conds} a.memNo = ? ORDER BY a.idx DESC";
			
			$this->db->bind_param_push($bind, "i", $memNo);
			$this->db->bind_param_push($bind2, "i", $memNo);
		}

	
		
		if ($withPaging) {
			$page = gd_isset($page, 1);
			$limit = gd_isset($limit, 10);
			$offset = ($page - 1) * $limit;
			$this->db->bind_param_push($bind, "i", $offset);
			$this->db->bind_param_push($bind, "i", $limit);
			$sql .= " LIMIT ?, ?";
		}
		
		$list = $this->db->query_fetch($sql, $bind);
			
		if (gd_isset($list)) {
			foreach ($list as $k => $v) {
				$v['card'] = $v['idxCard']?$this->getCard($v['idxCard']):[];
				if (!$this->adminList) {
					$info = $this->getApplyInfo($v['idx']);
					$v = array_merge($v, $info);
				}
				$list[$k] = $v;
				unset($goodsNo);
				$goodsNo[]=$v['goodsNo'];
				$cfg = $this->getCfg($goodsNo);
				$list[$k]['min_order']=$cfg['min_order'];
				//gd_debug($this->getCfg($goodsNo));
			}
		}

		//if(\Request::getRemoteAddress()=="112.145.36.156"){
		foreach($list as $key1 =>$val1){
			$chk_count=0;

			foreach($val1['schedules'] as $key2 =>$val){
			
				//if(substr($val['orderStatus'],0,1)=="o" || substr($val['orderStatus'],0,1)=="p"  || substr($val['orderStatus'],0,1)=="d"  || substr($val['orderStatus'],0,1)=="g"){
				if($val['orderStatusStr']!="결제중단" && $val['orderStatusStr']!="환불완료"){
					$chk_count++;
				}
				
				// 2024-08-26 wg-eric 실패로그 가져오기
				$FailLogSQL = "select * from wm_subscription_fail where scheduleIdx=?";
				$FailLogROW = $this->db->query_fetch($FailLogSQL,['i',$val['idx']]);
				$list[$key1]['schedules'][$key2]['fail_log']=$FailLogROW[0]['fail_log'];
			}

			if($chk_count<=0 && $val1['autoExtend']==0){
				unset($list[$key1]['card']);
			}
		}

		//gd_debug($list);
		//}
		
		if ($withPaging) {
			if ($this->isAdmin) {
				$sql = "SELECT COUNT(*) as cnt FROM wm_subApplyInfo";
			} else {
				$sql = "SELECT COUNT(*) as cnt FROM wm_subApplyInfo WHERE memNo = ?";
			}
			$row = $this->db->query_fetch($sql, ["i", $memNo], false);
			$amount = gd_isset($row['cnt'], 0);
			
			if ($this->isAdmin) {
				$conds = $arrWhere?" WHERE " . implode(" AND ", $arrWhere):"";
				$sql = "SELECT COUNT(*) as cnt FROM wm_subApplyInfo AS a 
							LEFT JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
							LEFT JOIN wm_subDeliveryInfo AS b ON b.idxApply = a.idx 
							{$conds}";
			} else {
				$sql = "SELECT COUNT(*) as cnt FROM wm_subApplyInfo AS a LEFT JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo WHERE {$conds}a.memNo = ?";
			}
			$row = $this->db->query_fetch($sql, $bind2, false);
			$total = gd_isset($row['cnt'], 0);
			
			$pageObj = App::load(\Component\Page\Page::class, $page, $total, $amount, $limit);
			$pageObj->setUrl(http_build_query($get));
			$pagination = $pageObj->getPage();
			
			
			$result = [
				'amount' => $amount,
				'total' => $total,
				'list' => gd_isset($list, []),
				'pagination' => $pagination,
				'page' => $pageObj,
			];
			return $result;
		} else {
			return gd_isset($list, []);
		}
	}
	
	/**
	* 정기결제 신청정보 
	*
	* @param Integer $idx 신청 번호
	* @return Array
	*/
	public function getApplyInfo($idx = null)
	{
		if ($idx) {			
			
			$GoodsClass = new \Component\Goods\Goods();

			$sql = "SELECT * FROM wm_subApplyInfo WHERE idx = ?";
			$info = $this->db->query_fetch($sql, ["i", $idx], false);
			$server = Request::server()->toArray();
			$subCalendarDays = 30;


			if ($info) {
				$nextDeliveryStamp = 0;
				$schedules = [];
				$list = $this->getSchedules($info['idx']);

				
				$memNo = $info['memNo'];

				

				$orderCnt = $readyCnt = 0;
				if ($list) {					
					foreach ($list as $k => $v) {
						
						$s = $this->getSchedule($v['idx']);
						if (!$nextDeliveryStamp && $s['deliveryStamp'] > strtotime(date("Ymd"))) {
							$nextDeliveryStamp = $s['deliveryStamp'];
						}
			
						/* 배송요일 */
						$s['dYoilStr'] = $this->getYoilStr($s['deliveryStamp']);
						
						/* 결제요일 */
						$s['pYoilStr'] = $this->getYoilStr($s['payStamp']);
						
						/* 배송 상품 */
						$goods = $this->getScheduleGoods($v['idx']);

						//스케줄링별 상품정보시작
						foreach($goods as $goodsKey => $goodsVal){
							$getGoodsView = $GoodsClass->getGoodsView($goodsVal['goodsNo']);
							$addGoodsNm="";
							$addGoodsNmDesc="";
							if(!empty($goodsVal['addGoodsNo'])){
								$addGoodsNo = json_decode(stripslashes($goodsVal['addGoodsNo']));
								foreach($addGoodsNo as $addKey =>$addVal){
								
									foreach($getGoodsView['addGoods'] as $addKey2 => $addVal1){
										$addNum = 0;
										foreach($addVal1['addGoodsList'] as $addKey3 => $addVal12){
											
											if($addVal12['addGoodsNo'] == $addVal) {
												$addGoodsNm.=$addVal12['goodsNm'].",";
												$addGoodsNmDesc.=$addVal12['goodsNm']." / ".JSON_DECODE(stripslashes($goodsVal['addGoodsCnt']), true)[$addNum]."개,";
												$addNum++;
											}
										}
									}
									
								}
							}
							
							
							$goods[$goodsKey]['goodsNm']	= $getGoodsView['goodsNm'];
							$goods[$goodsKey]['optionName'] = $getGoodsView['optionName'];
							if(!empty($addGoodsNm)) {
								$goods[$goodsKey]['addGoodsNm'] = substr($addGoodsNm,0,-1);
								$goods[$goodsKey]['addGoodsNmDesc'] = substr($addGoodsNmDesc,0,-1);
							} else {
								$goods[$goodsKey]['addGoodsNm'] = "";
								$goods[$goodsKey]['addGoodsNmDesc'] = "";
							}
						}
						//스케줄링별 상품정보종료
						
						$s['goods'] = $goods;
						
						/* 임시 수기 장바구니 삭제 START */
						$bind = [
							'si',
							Session::get("siteKey"),
							$memNo,
						];
						
						$this->db->set_delete_db("wm_subCartAdmin", "siteKey = ? AND memNo = ? AND isTemp = 1", $bind);
						/* 임시 수기 장바구니 삭제 END */
						
						$cartSnos = [];
						if(\Request::getRemoteAddress()=="112.146.205.124"){
							$cart = new \Component\Subscription\CartSubAdmin($memNo);
						}else{
							$cart = new CartSubAdmin($memNo);
						}
						$cart->deliveryType = $v['deliveryType'];
						
						$server = Request::server()->toArray();
						
						
						foreach ($goods as $g) {
							$g['addGoodsCnt'] =  $g['addGoodsCnt']?json_decode(gd_htmlspecialchars_stripslashes($g['addGoodsCnt'])):[];
							$g['addGoodsNo'] =  $g['addGoodsNo']?json_decode(gd_htmlspecialchars_stripslashes($g['addGoodsNo'])):[];

							$TmpSQL="select goodsPrice from ".DB_GOODS." where goodsNo=?";
							$TmpRow = $this->db->query_fetch($TmpSQL,['i',$g['goodsNo']],false);
							$params = [
								'goodsPrice'=>$TmpRow['goodsPrice'],
								'cartType' => 'cartSubWrite',
								'goodsNo' => $g['goodsNo'],
								'optionSno' => $g['optionSno'],
								'goodsCnt' => $g['goodsCnt'],
								'addGoodsNo' => $g['addGoodsNo']?$g['addGoodsNo']:"",
								'addGoodsCnt' => $g['addGoodsCnt']?$g['addGoodsCnt']:"",
								'optionText' => $g['optionText']?$g['optionText']:"",
								'setGoodsNo' => $g['setGoodsNo'],
							];
							

							$cartSnos[] = $cart->saveGoodsToCart($params);
							
						}
				
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
									$k,
									$s['deliveryEa'],
									$sno,
								 ];
								 
								 $this->db->set_update_db("wm_subCartAdmin", $param, "sno = ?", $bind);
								
							} // endforeach 
	
							$cart->notMerge = true;							
							$cartInfo = $cart->getCartGoodsData($cartSnos, $v['receiverAddress']);
							

							 $s['settleInfo'] = [
								'totalGoodsPrice' => $cart->totalGoodsPrice,
								'totalDeliveryCharge' => $cart->totalDeliveryCharge,
								'totalDiscount' => ($cart->totalGoodsDcPrice + $cart->totalMemberDcPrice + $cart->totalMemberOverlapDcPrice + $cart->totalMyappDcPrice + $cart->totalCouponGoodsDcPrice),
								'totalSettlePrice' => $cart->totalSettlePrice,		
							 ];
							 
							  if ($s['settleInfo']['totalDiscount'] > 0) {
								  $rate = round(($s['settleInfo']['totalDiscount'] / $s['settleInfo']['totalGoodsPrice']) * 100);
							  } else {
								  $rate = 0;
							  }
							
							$s['settleInfo']['rate'] = (Integer)$rate;
							 
							/* 주문번호가 있는 경우 */
							if ($s['orderNo']) {
								$sql = "SELECT * FROM " . DB_ORDER . " AS o 
												LEFT JOIN " . DB_ORDER_INVOICE . " AS oi ON o.orderNo = oi.orderNo 
												WHERE o.orderNo = ? LIMIT 0, 1";
								$row = $this->db->query_fetch($sql, ["s", $s['orderNo']], false);
								if (empty($row)) {
									unset($s['orderNo']);
								} else {
									$orderStatusStr = $this->getOrderStatus($row['orderStatus']);
									$orderStatus = substr($row['orderStatus'], 0, 1);
									if (in_array($orderStatus, ["p", "g","d", "s"])) {
										$orderCnt++;
									}
									$s['orderStatus'] = $row['orderStatus'];
									$s['orderStatusStr'] = $orderStatusStr;
									$s['invoiceCompanySno'] = $row['invoiceCompanySno'];
									$s['invoiceNo'] = $row['invoiceNo'];
									
									$s['settlePrice'] = $row['settlePrice'];
									$s['totalGoodsPrice'] = $row['totalGoodsPrice'];
									$s['totalDeliveryCharge'] = $row['totalDeliveryCharge'];
									$totalDiscount = $s['totalGoodsPrice'] + $s['totalDeliveryCharge']  - $s['settlePrice'];
									
									$goodsDiscount = $row['totalGoodsDcPrice'] + $row['totalMemberDcPrice'] + $row['totalMemberBankDcPrice'] + $row['totalMemberOverlapDcPrice'] + $row['totalMemberDeliveryDcPrice'] + $row['totalMyappDcPrice'];
									$couponDiscount = $row['totalCouponGoodsDcPrice']+ $row['totalCouponOrderDcPrice'] + $row['totalCouponDeliveryDcPrice'];
									$mileage = $row['useMileage'];
									$deposit = $row['useDeposit'];
									$realPayStamp = ($row['paymentDt'] != '0000-00-00 00:00:00' && $row['paymentDt'])?strtotime($row['paymentDt']):0;
									$s['settleInfo'] = [
										'totalGoodsPrice' => $row['totalGoodsPrice'],
										'totalDeliveryCharge' => $row['totalDeliveryCharge'],
										'totalDiscount' => $totalDiscount,
										'totalSettlePrice' => $row['settlePrice'],
										'goodsDiscount' => $goodsDiscount,
										'couponDiscount' => $couponDiscount,
										'mileage' => $mileage,
										'deposit' => $deposit,
										'realPayStamp' => $realPayStamp,
									];
									
									 if ($s['settleInfo']['totalDiscount'] > 0) {
										  $rate = round(($s['settleInfo']['totalDiscount'] / $s['settleInfo']['totalGoodsPrice']) * 100);
									  } else {
										  $rate = 0;
									  }
									
									$s['settleInfo']['rate'] = (Integer)$rate;
								}
							} // endif 
							 
							 if (empty($s['orderNo']) && $s['status'] == 'stop') {
								 $s['orderStatusStr'] = '결제중단';
							 }else if($s['status'] == 'pause'){
								$s['orderStatusStr'] = '일시정지';
							 }
							 
							 if ($k == 0) {
								 $info['totalGoodsPrice'] = $cart->totalGoodsPrice;
							 }
						} // endif 
						
						if ($s['status'] == 'ready' || $s['status'] == 'pause') {
							$readyCnt++;
						}

						$schedules[] = $s;
						
						if ($k == 0) {
							//gd_debug($goods);
							$goodsNo = array_column($goods, "goodsNo");
							$optionSno = array_column($goods, "optionSno");
							//gd_debug($optionSno);
							if ($goodsNo) {
								$goodsNo = $goodsNo[0];
								$g = $this->getGoods($goodsNo,false,$optionSno);

								
								if ($g) {
									$g = $g[0];
									$goodsNm = $g['goodsNm'];
									if (count($goods) > 1) $goodsNm .= "외 " . (count($goods) - 1)."건";
									$info['goodsImageSrc'] = $g['goodsImageSrc'];
									$info['goodsNm'] = $goodsNm;
									$info['goodsNo'] = $g['goodsNo'];
									$info['brandNm'] = $g['brandNm'];
									$info['optionName'] =$g['optionName'];
									
								}
							} // endif 
						} // endif 

					} // endforeach 
				} // endif 
				
				/* 주소 추출 START */
				$sql = "SELECT * FROM wm_subDeliveryInfo WHERE idxApply = ?";
				$row = $this->db->query_fetch($sql, ["i", $info['idx']], false);
				$info['address'] = $row?$row:[];
				/* 주소 추출 END */

				$info['checkDeliveryPeriod']=$info['deliveryPeriod'];
				$info['deliveryPeriod'] = explode("_", $info['deliveryPeriod']);
				
				$info['yoilStr'] = $nextDeliveryStamp?$this->getYoilStr($nextDeliveryStamp):"";
				$info['nextDeliveryStamp'] = $nextDeliveryStamp;
				
				$info['orderCnt'] = $orderCnt; // 주문 수 
				$info['readyCnt'] = $readyCnt; // 결제 예정 수
				
				/* 이전 회차 stamp 추출 */
				$prevDeliveryStamp = 0;
				foreach ($schedules as $k => $v) {
					if ($v['deliveryStamp'] < $nextDeliveryStamp) {
						$prevDeliveryStamp = $v['deliveryStamp'];
					}

					// 2024-08-26 wg-eric 실패로그 가져오기
					$FailLogSQL = "select * from wm_subscription_fail where scheduleIdx=?";
					$FailLogROW = $this->db->query_fetch($FailLogSQL,['i',$v['idx']]);
					$schedules[$k]['fail_log']=$FailLogROW[0]['fail_log'];
				}
				
				$prevDeliveryStamp = $prevDeliveryStamp?$prevDeliveryStamp:$schedules[0]['deliveryStamp'];
				
				$info['prevDeliveryStamp'] = $prevDeliveryStamp;
				$info['utilDeliveryStamp'] = $prevDeliveryStamp + (60 * 60 * 24 * $subCalendarDays);

				$info['schedules'] = $schedules;
				$info['card'] = $this->getCard($info['idxCard']);

				$goodsInfo=$this->db->fetch("select goodsNo from wm_subApplyGoods where idxApply='{$idx}'");
				$goodsNo=[];
				$goodsNo[]=$goodsInfo['goodsNo'];
				
				$cfg = $this->getCfg($goodsNo);
				$info['min_order']=$cfg['min_order'];
			}
		} // endif 

		
		
		return gd_isset($info, []);
	}
	
	/**
	* 배송지 sno로 정기결제 배송지 변경 
	*
	* @param Integer $idxApply 정기결제 신청번호 
	* @param Integer $shippingSno 배송지 번호 
	*
	* @return Boolean
	*/
	public function changeAddressBySno($idxApply = null, $shippingSno = null)
	{

		if ($idxApply && $shippingSno) {
			
			$sql = "SELECT * FROM wm_subDeliveryInfo WHERE idxApply = ?";
			$prevAddress = $this->db->query_fetch($sql, ["i", $idxApply], false);
			$prevAddr = "[".$prevAddress['receiverZonecode']."]".$prevAddress['receiverAddress'] . " " .$prevAddress['receiverAddressSub'] . " / ".$prevAddress['receiverName'] . "/".$prevAddress['receiverCellPhone'];
			$manager = \Session::get("manager");
			$managerId = $manager['managerId'];
			$memNo = \Session::get("member.memNo");
			
			$sql = "SELECT * FROM " . DB_ORDER_SHIPPING_ADDRESS . " WHERE sno = ?";
			
			$row = $this->db->query_fetch($sql, ["i", $shippingSno], false);
			$newAddr = "[".$row['shippingZonecode']."]".$row['shippingAddress'] . " " .$row['shippingAddressSub'] . " / ".$row['shippingName'] . "/".$row['shippingCellPhone'];
			
			$param = [
				'receiverName = ?',
				'receiverPhone = ?',
				'receiverCellPhone = ?',
				'receiverZipcode = ?',
				'receiverZonecode = ?',
				'receiverAddress = ?',
				'receiverAddressSub = ?',
			];
			
			$list = $this->getSchedules($idxApply);
			
			$OrderStatus_List=array("o1","p1");//입금전,결제완료 상태에서만 수정가능하도록

			if ($list) {
				foreach ($list as $li) {
					//if (!$li['orderNo'])
					//	continue;

					//if(\Request::getRemoteAddress()=="112.145.36.156"){

					if ($li['orderNo']){
						
						$strSQL="select orderStatus from ".DB_ORDER." where orderNo=?";
						$orderInfo = $this->db->query_fetch($strSQL,['i',$li['orderNo']],false);
						
						$orderStatus = $orderInfo['orderStatus'];

						if(in_array($orderStatus,$OrderStatus_List)===false){
							continue;
						}

						$orderSQL="update ".DB_ORDER_INFO." set receiverName=?,receiverPhone=?,receiverCellPhone=?,receiverAddress=?,receiverAddressSub=?,receiverZipcode=?,receiverZipcode=? where orderNo=?";
						$this->db->bind_query($orderSQL,['sssssssi',$row['shippingName'],$row['shippingPhone'],$row['shippingCellPhone'],$row['shippingAddress'],$row['shippingAddressSub'],$row['shippingZipcode'],$row['shippingZonecode'],$li['orderNo']]);
					}

					//}			

					$bind = [
						'sssssssi',
						$row['shippingName'],
						$row['shippingPhone'],
						$row['shippingCellPhone'],
						$row['shippingZipcode'],
						$row['shippingZonecode'],
						$row['shippingAddress'],
						$row['shippingAddressSub'],
						$li['idx'],
					];
					
					$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
				} // endforeach 
			} // endif 
			
			$bind = [
				'sssssssi',
				$row['shippingName'],
				$row['shippingPhone'],
				$row['shippingCellPhone'],
				$row['shippingZipcode'],
				$row['shippingZonecode'],
				$row['shippingAddress'],
				$row['shippingAddressSub'],
				$idxApply,
			];
			
			$affectedRows = $this->db->set_update_db("wm_subDeliveryInfo", $param, "idxApply = ?", $bind);
		} // endif 
		
		if ($affectedRows > 0) {
			$param = [
				'memNo',
				'managerId',
				'prevAddress',
				'newAddress', 
				'idxApply',
			];
					
			$bind = [
				'isssi',
				$memNo,
				$managerId, 
				$prevAddr,
				$newAddr,
				$idxApply,
			];
					
			$this->db->set_insert_db("wm_subAddressChangeLog", $param, $bind, "y");
		}
		
		return $affectedRows > 0;
	}
	
	/**
	* 배송 스케줄 추가 
	*
	* @param Integer $idxApply 정기배송 신청 IDX 
	* @param Integer $addCnt 추가 스케줄 갯수 
	*
	* @return Boolean
	* @throw Exception
	*/
	public function addSchedule($idxApply = 0, $addCnt = 0, $useException = false)
	{
		if (empty($idxApply)) {
			if ($useException) {
				throw new Exception("정기결제 신청번호 누락");
			}
			
			return false;
		}
		
		if (empty($addCnt)) {
			if ($useException) {
				throw new Exception("추가 스케줄 갯수를 입력하세요.");
				
				return false;
			}
		}
		
		$info = $this->getApplyInfo($idxApply);
		if (!$info || !$info['schedules']) {
			if ($useException) {
				throw new Exception("신청정보가 존재하지 않습니다.");
			}
			
			return false;
		}

		/* 공통 배송 정보 추출 */
		$sql = "SELECT * FROM wm_subDeliveryInfo WHERE idxApply = ?";
		$delivery = $this->db->query_fetch($sql, ["i", $idxApply], false);
		/* 공통 추가 상품 추출 */
		$sql = "SELECT * FROM wm_subApplyGoods WHERE idxApply = ? ORDER BY idx";
		$goodsList = $this->db->query_fetch($sql, ["i", $idxApply]);
		$goods = array_column($goodsList, "goodsNo");

		$deliveryStamp = $info['schedules'][count($info['schedules']) - 1]['deliveryStamp']?$info['schedules'][count($info['schedules']) - 1]['deliveryStamp']:0;
		
		$schedule = new \Component\Subscription\Schedule();
		$firstStamp = $schedule->getFirstDay();


		$cfg = $this->getCfg($goods);
		$deliveryStamp = $deliveryStamp?$deliveryStamp:$firstStamp;
		//$no = 1;
		$list = [];

		//while ($no <= $addCnt) {
		$plus=0;
		for($no=1;$no<=$addCnt;$no++){
			// 2024-06-28 wg-eric 결제일 선택 추가
			if($info['payMethodFl'] == 'dayPeriod') {
				if(end($list)) {
					$setDeliveryStamp = end($list);
				} else {
					$setDeliveryStamp = $deliveryStamp;
				}

				$newDate = new \DateTime();
				$newDate->setTimestamp($setDeliveryStamp);

				// 현재 날짜의 년도와 월을 가져옴
				$currentDay = $newDate->format('d');
				$currentYear = $newDate->format('Y');
				$currentMonth = $newDate->format('m');

				// 해당 달의 마지막 날 구하기
				$lastDayOfMonth = (int) $newDate->format('t');

				// 입력한 일이 해당 달의 마지막 일을 초과하는지 확인
				if ($info['deliveryDayPeriod'] > $lastDayOfMonth) {
					$deliveryDayPeriod = $lastDayOfMonth;
				} else {
					$deliveryDayPeriod = $info['deliveryDayPeriod'];
				}
				// 날짜를 설정
				$targetDate = new \DateTime("$currentYear-$currentMonth-$deliveryDayPeriod");

				// 주어진 날짜가 지난 경우 다음 달로 설정
				if ($newDate > $targetDate) {
					$targetDate = new \DateTime("$currentYear-$currentMonth-01");
					$targetDate->modify('+1 month');

					// 현재 날짜의 년도와 월을 가져옴
					$targetYear = $targetDate->format('Y');
					$targetMonth = $targetDate->format('m');

					// 해당 달의 마지막 날 구하기
					$lastDayOfMonth = (int) $targetDate->format('t');

					// 입력한 일이 해당 달의 마지막 일을 초과하는지 확인
					if ($info['deliveryDayPeriod'] > $lastDayOfMonth) {
						$deliveryDayPeriod = $lastDayOfMonth;
					} else {
						$deliveryDayPeriod = $info['deliveryDayPeriod'];
					}
					// 날짜를 설정
					$targetDate = new \DateTime("$targetYear-$targetMonth-$deliveryDayPeriod");
				}

				// 타임스탬프 형식으로 출력
				$newStamp = $targetDate->getTimestamp();

				$yoil = date("w", $newStamp);
			
				$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
				$row = $this->db->query_fetch($sql, ["i", $newStamp], false);
				$newStamp = $newStamp + (60  * 60 * 24 * gd_isset($conf['payDayBeforeDelivery'], 1));

				if ($row['isHoliday']) {
					while(1){
						$newStamp = strtotime("+1 day", $newStamp);
						$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
						$row = $this->db->query_fetch($sql, ["i", $newStamp], false);
						if (!$row['isHoliday']){
							break;
						}
					}
				}
				//continue;
			} else {
				$str = "+".$info['deliveryPeriod'][0] * $no. " " .$info['deliveryPeriod'][1];
				$newStamp = strtotime($str, $deliveryStamp);

				$yoil = date("w", $newStamp);
			
				$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
				$row = $this->db->query_fetch($sql, ["i", $newStamp], false);
				if ($newStamp < $firstStamp || $row['isHoliday']){

					while(1){
						$newStamp = strtotime("+1 day", $newStamp);
						$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
						$row = $this->db->query_fetch($sql, ["i", $newStamp], false);
						if (!$row['isHoliday']){
							break;
						}
					}
					//continue;
				}
			}
			
			$yoil = date("w", $newStamp);
			if ($cfg['deliveryYoils'] && !in_array($yoil, $cfg['deliveryYoils'])) {
				
				while(1){
					$newStamp = strtotime("+1 day", $newStamp);
					$yoil = date("w", $newStamp);

					if(in_array($yoil, $cfg['deliveryYoils'])){
						break;
					}
				}
				//continue;
			}
			
			$list[] = $newStamp;
			//$no++;
		}
		
		foreach ($list as $stamp) {
			// 2024-11-19 wg-eric 배송예정일 중복되면 건너뛰기
			$sql = "
				SELECT idx FROM wm_subSchedules
				WHERE idxApply = ".$idxApply."
				AND deliveryStamp = ".$stamp."
				AND (status = 'ready' OR status = 'pause')
			";
			$sameDelivery = $this->db->slave()->query_fetch($sql, null, false);
			if($sameDelivery) continue;

			$delivery['deliveryStamp'] = $stamp;
			$arrBind = $this->db->get_binding(DBTableField::tableWmSubSchedules(), $delivery, "insert", array_keys($delivery));
			$this->db->set_insert_db("wm_subSchedules", $arrBind['param'], $arrBind['bind'], "y");
			$affectedRows = $this->db->affected_rows();
			if ($affectedRows > 0) {
				$idxSchedule = $this->db->insert_id();
				foreach ($goodsList as $g) {
					$g['idxSchedule'] = $idxSchedule;
					$arrBind = $this->db->get_binding(DBTableField::tableWmSubSchedulesGoods(), $g, "insert", array_keys($g));
					$this->db->set_insert_db("wm_subSchedulesGoods", $arrBind['param'], $arrBind['bind'], "y");
				}
			}
		}
		
		return true;
	}
	
	/**
	* 정기배송 모두 중단 
	*
	* @param Integer $idx 정기결제 신청 번호 
	* @param Boolean $useException 예외 출력 여부 
	* 
	* @return Boolean 
	*/
	public function stopAll($idx = null, $useException = false,$pause_period="")
	{
		if (!$idx) {
			if ($useException) {
				throw new Exception("정기졀제 신청번호 누락");
			}
			
			return false;
		}

		$now_date=date("Y-m-d");
		$status_period="";
	
		$list = $this->getSchedules($idx);

		$strSQL="update wm_subApplyInfo set autoExtend='0' where idx='{$idx}'";
		$this->db->query($strSQL);

		foreach ($list as $li) {
			
			if ($li['status'] == 'ready' || $li['status'] == 'pause') {
				$param = [
					'status = ?',
					'status_period=?',
				];
				
				$bind = [
					'sii', 
					'stop',
					'0',
					$li['idx'],
				];
				
				$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
			}
		} // endforeach 
		
		return true;
	}

	public function pauseAll($idx = null, $useException = false,$pause_period="",$scheduleIdx=0)
	{
		if (!$idx) {
			if ($useException) {
				throw new Exception("정기졀제 신청번호 누락");
			}
			
			return false;
		}

		$now_date=date("Y-m-d");
		$status_period="";
		if($pause_period>0 && !empty($pause_period))
			$status_period = date("Y-m-d",strtotime($now." +".$pause_period." day"));

		

		$list = $this->getSchedules($idx);
		$chk=0;
		foreach ($list as $key=>$li) {

			if(!empty($scheduleIdx) && $li['idx']!=$scheduleIdx){
				continue;
			}
			if ($li['status'] == 'ready' || $li['status'] == 'pause') {
				
				if($chk==0){
					$deliveryStamp=strtotime(date("Y-m-d",$li['deliveryStamp'])." + ".$pause_period." day");
				}else{
				
					$r=$this->db->fetch("select deliveryPeriod from wm_subApplyInfo where idx='{$li['idxApply']}'");

					$deliveryPeriod = explode("_",$r['deliveryPeriod']);
					$deliveryStamp=strtotime(date("Y-m-d",$deliveryStamp)." + ".$deliveryPeriod[0]." ".$deliveryPeriod[1]);

				}
				$chk++;
				
				if(empty($scheduleIdx) && $chk != 1) {
					// 2024-10-07 wg-eric 일시정지 하면 한개만 일시정지 
					$param = [
						'deliveryStamp = ?',
					];
					$bind = [
						'si', 
						$deliveryStamp,
						$li['idx'],
					];
				} else {
					$param = [
						'status = ?',
						'deliveryStamp = ?',
					];
					$bind = [
						'ssi', 
						'pause',
						$deliveryStamp,
						$li['idx'],
					];
				}
				$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
			}
		} // endforeach 

		return true;
	}
}