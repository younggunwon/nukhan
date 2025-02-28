<?php

namespace Component\Order;

use Request;
use App;
use Component\Mail\MailAutoObserver;
use Component\Godo\NaverPayAPI;
use Component\Member\Member;
use Component\Naver\NaverPay;
use Component\Database\DBTableField;
use Component\Delivery\OverseasDelivery;
use Component\Deposit\Deposit;
use Component\ExchangeRate\ExchangeRate;
use Component\Mail\MailMimeAuto;
use Component\Mall\Mall;
use Component\Mall\MallDAO;
use Component\Member\Manager;
use Component\Member\Util\MemberUtil;
use Component\Mileage\Mileage;
use Component\Policy\Policy;
use Component\Sms\Code;
use Component\Sms\SmsAuto;
use Component\Sms\SmsAutoCode;
use Component\Sms\SmsAutoObserver;
use Component\Validator\Validator;
use Component\Goods\SmsStock;
use Component\Goods\KakaoAlimStock;
use Component\Goods\MailStock;
use Encryptor;
use Exception;
use Framework\Application\Bootstrap\Log;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Helper\MallHelper;
use Framework\Utility\ArrayUtils;
use Framework\Utility\ComponentUtils;
use Framework\Utility\NumberUtils;
use Framework\Utility\StringUtils;
use Framework\Utility\UrlUtils;
use Globals;
use Logger;
use LogHandler;
use Session;
use Framework\Utility\DateTimeUtils;
use DateTime;

class Order extends \Bundle\Component\Order\Order
{
	public $excuteSql = [];
    
	public function generateOrderNo() {

		$tmp = parent::generateOrderNo();

		$db=\App::load(\DB::class);
		$sql="select count(orderNo) as cnt from ".DB_ORDER." where orderNo=?";
		$row = $db->query_fetch($sql,['s',$tmp],false);
		
		if($row['cnt']<=0){
			return $tmp;
		}else{
			while(1){
				$result = $this->wgenerateOrderNo();

				$sql="select count(orderNo) as cnt from ".DB_ORDER." where orderNo=?";
				$row = $db->query_fetch($sql,['s',$result],false);

				if($row['cnt']<=0){
					break;
				}

			}
			return $result;
		}
		
	}
    public function wgenerateOrderNo()
    {
        // 0 ~ 999 마이크로초 중 랜덤으로 sleep 처리 (동일 시간에 들어온 경우 중복을 막기 위해서.)
        usleep(mt_rand(0, 999));

        // 0 ~ 99 마이크로초 중 랜덤으로 sleep 처리 (첫번째 sleep 이 또 동일한 경우 중복을 막기 위해서.)
        usleep(mt_rand(0, 99));

        // microtime() 함수의 마이크로 초만 사용
        list($usec) = explode(' ', microtime());

        // 마이크로초을 4자리 정수로 만듬 (마이크로초 뒤 2자리는 거의 0이 나오므로 8자리가 아닌 4자리만 사용함 - 나머지 2자리도 짜름... 너무 길어서.)
        $tmpNo = sprintf('%04d', round($usec * 10000));

        // PREFIX_ORDER_NO (년월일시분초) 에 마이크로초 정수화 한 값을 붙여 주문번호로 사용함, 16자리 주문번호임
        return PREFIX_ORDER_NO . $tmpNo;
    }

	public function updateRecomMileagePayFl($orderGoodsSno)
	{
		$sql = 'UPDATE es_orderGoods SET recomMileagePayFl = "y" WHERE sno ='.$orderGoodsSno;
		$this->excuteSql[] = $sql;
		$this->db->slave()->query($sql);
	}

	public function updateMemberRecomMileagePayFl($memNo)
	{
		$sql = 'UPDATE es_member SET recomMileagePayFl = "y" WHERE memNo ='.$memNo;
		$this->db->slave()->query($sql);
	}

	public function getMemberRecomMileagePayFl($memNo)
	{
		$sql = 'SELECT recomMileagePayFl FROM es_member WHERE memNo ='.$memNo;
		$recomMileagePayFl = $this->db->query_fetch($sql, null, false)['recomMileagePayFl'];
		return $recomMileagePayFl;
	}
	public function saveOrder($cartInfo, $orderInfo, $order, $isWrite = false)
    {
		parent::saveOrder($cartInfo, $orderInfo, $order, $isWrite);
		//루딕스-brown 추천인있을때 recomMileagePayFl = w
		$session = \App::getInstance('session');
		$memNo = $session->get('member.memNo');
		$sql = 'SELECT recommId FROM es_member WHERE memNo = '.$memNo;
		$recommId = $this->db->query_fetch($sql, null, false)['recommId'];
		if($recommId){
			$sql = 'UPDATE es_orderGoods SET recomMileagePayFl="w" WHERE orderNo ='.$this->orderNo;
			$this->db->slave()->query($sql);
		}

	}

	public function recomMileageSchedule()
	{
		//모듈
		$recomMileageConfig = gd_policy('member.recomMileageGive');
		$order = \App::load('Component\\Order\\Order');
		$member = \App::load('Component\\Member\\Member');
		$goods = \App::load('\\Component\\Goods\\Goods');
		$mileage = \App::load('\\Component\\Mileage\\Mileage');
		$today = new DateTime(); //오늘 날짜
		$blackList = explode(',',$recomMileageConfig['blackList']); //블랙리스트

		if($recomMileageConfig['blackListFl'] == 'y') {
			$strBlackListWhere = 'AND m.recommId NOT IN ("'.implode('","', $blackList).'")';
		}

		$sql = '
		SELECT  idxApply, orderNo, regDate, recommId, memNo, recomMileagePayFl, couponNm
		FROM (
			SELECT 
				 ss.idxApply, ss.orderNo, o.regDt as regDate, m.recommId, ss.memNo, m.recomMileagePayFl, oc.couponNm
			FROM 
				wm_subSchedules ss
			LEFT JOIN 
				es_member m ON m.memNo = ss.memNo
			LEFT JOIN 
				es_order o ON o.orderNo = ss.orderNo
			LEFT JOIN 
				es_orderCoupon oc ON oc.orderNo = o.orderNo
			WHERE
				ss.status = "paid" 
				AND (m.recommId != "" '.$strBlackListWhere.')	
				AND m.recomMileagePayFl = "n"
				AND oc.orderNo IS NOT NULL
			GROUP BY ss.idxApply
			HAVING COUNT(ss.idx) >= 1 AND regDate > "2024-01-25"
			
			UNION ALL

			SELECT 
				ss.idxApply, o.orderNo, o.regDt as regDate, m.recommId, m.memNo, m.recomMileagePayFl, NULL as couponNm
			FROM 
				es_order o
			LEFT JOIN 
				es_orderGoods og ON og.orderNo = o.orderNo 
			LEFT JOIN 
				es_member m ON m.memNo = o.memNo 
			LEFT JOIN 
				wm_subSchedules ss ON ss.orderNo = o.orderNo 
			WHERE
				m.recomMileagePayFl = "n"
				AND og.paymentDt != "0000-00-00 00:00:00" 
				AND LEFT(o.orderStatus, 1) NOT IN ("r", "f", "d", "c") 
				AND (m.recommId != "" '.$strBlackListWhere.')	
				AND ss.orderNo IS NULL
				AND og.recomMileagePayFl = "w"
				AND o.regDt > "2024-01-25 00:00:00"

			GROUP BY o.orderNo

			UNION ALL

			SELECT 
				 ss.idxApply, ss.orderNo, o.regDt as regDate, m.recommId, ss.memNo, m.recomMileagePayFl, NULL as couponNm
			FROM 
				wm_subSchedules ss
			LEFT JOIN 
				es_member m ON m.memNo = ss.memNo
			LEFT JOIN 
				es_order o ON o.orderNo = ss.orderNo
			LEFT JOIN 
				es_orderCoupon oc ON oc.orderNo = o.orderNo
			WHERE
				ss.status = "paid" 

				AND (m.recommId != "" '.$strBlackListWhere.')	
				AND m.recomMileagePayFl = "n"
				AND oc.orderNo IS NULL
			GROUP BY ss.idxApply
			HAVING COUNT(ss.idx) >= 1 AND regDate > "2024-01-25"

		) AS combined_table
		ORDER BY regDate ASC;
		';
			
		$orderData = $this->db->query_fetch($sql);

		$this->excuteSql[] = $sql;
		foreach($orderData as $key => $val ){
			//orderData에 같은 회원이 다른주문을 주문시 마일리지 중복지급 방지(회원당 추천인에게 마일리지 적립 1회만 지급)
			$memberRecomMileagePayFl = $order->getMemberRecomMileagePayFl($val['memNo']);
			if($memberRecomMileagePayFl == 'n') {
				$getRecomMemNo = $member->getRecomMemNo($val['recommId']); //추천인 회원번호
				$orderData[$key]['goods'] = $order->getOrderGoods($val['orderNo'], null, null); //주문상품정보
				$totalMileage = 0;

				if($val['idxApply']) { //정기배송주문
					//해당 idxApply에서 paid인 주문들이 환불/반품이 제외 2개 이상일때 지급(쿠폰 지급여부에 따라 )
					$sql = 'SELECT count(o.orderNo) as cnt FROM wm_subSchedules ss LEFT JOIN es_order o ON o.orderNo = ss.orderNo WHERE ss.idxApply='.$val['idxApply'].' AND ss.status="paid" AND LEFT(o.orderStatus, 1) NOT IN ("b","r")';
					$applyCnt = $this->db->query_fetch($sql, null, false)['cnt'];
					if($val['couponNm']) {
						if($applyCnt < 2){
							continue;
						}
					}else {
						if($applyCnt < 1){
							continue;
						}
					}
					
					foreach($orderData[$key]['goods'] as $goodsData) {
						//첫결제 이후 60일 지난 시점인지 검사 
						$firstOrderDate = new DateTime($goodsData['paymentDt']);
						$interval = $today->diff($firstOrderDate);
						//if($val['couponNm']) {
						//	if($interval->days < 60) {
						//		continue;
						//	}
						//} else {
						//	if($interval->days < 45) {
						//		continue;
						//	}
						//}
						if(!$goodsData['userHandleSno'] && !$goodsData['handleSno'] && !in_array(substr($goodsData['orderStatus'], 0, 1), ['r','f','c','d'])){ //해당 주문이 반품/환불/취소/실패인지 검사
							if($goodsData['recomMileagePayFl'] == 'w' && $goodsData['orderStatus'] == 'p1') { //해당 주문상품이 추천인 마일리지를 지급했는지 안했는지
								$totalMileage += $order->recomSubMileagePrice($goodsData);	
								$order->updateRecomMileagePayFl($goodsData['sno']); //es_orderGoods recomMileagePayFl 업데이트	
							}
						}
					}

					//적립금 지급
					if($totalMileage > 0) {
						$order->updateMemberRecomMileagePayFl($val['memNo']); //es_member recomMileagePayFl 업데이트	
						$mileage->setRecomMemberMileage($getRecomMemNo, $totalMileage, '01005507',  'o', $val['orderNo'], null, '정기배송주문 시 추천인 마일리지 지급');		
					}	
				}else { //일반주문
					foreach($orderData[$key]['goods'] as $goodsData) {
						if(!$goodsData['userHandleSno'] && !$goodsData['handleSno'] && !in_array(substr($goodsData['orderStatus'], 0, 1), ['r','f','c','d'])){ 
							if($goodsData['recomMileagePayFl'] == 'w' && $goodsData['orderStatus'] == 'p1') { //해당 주문상품이 추천인 마일리지를 지급했는지 안했는지
								//상품별 마일리지데이터 가져오기		
								$totalMileage += $order->recomMileagePrice($goodsData);
								$order->updateRecomMileagePayFl($goodsData['sno']);
							}
						}
					}

					//적립금 지급
					if($totalMileage > 0) {
						$order->updateMemberRecomMileagePayFl($val['memNo']); //회원 추천인 적립 fl 업데이트	
						$mileage->setRecomMemberMileage($getRecomMemNo, $totalMileage, '01005506',  'o', $val['orderNo'], null, '1회구매 시 추천인 마일리지 지급');		
					}
				}	
			}
		}
		//추천인 마일리지 지급
	}

	//일반주문 적립금 구하기
	public function recomMileagePrice($goodsData) 
	{
		$recomMileageConfig = gd_policy('member.recomMileageGive');
		$goods = \App::load('\\Component\\Goods\\Goods');
		$order = \App::load('Component\\Order\\Order');
		$goodsView = $goods->getGoodsInfo($goodsData['goodsNo']);
		$recomMileagFl = $goodsView['recomMileageFl'];

		# (상품가격+상품의옵션가격+상품의텍스트옵션가격)*상품개수
		$settlePrice = ($goodsData['goodsPrice'] + $goodsData['optionTextPrice'] +$goodsData['optionPrice']) * $goodsData['goodsCnt'];

		# 타임세일,상품할인,회원할인 적용
		$settlePrice = $settlePrice - ($goodsData['goodsDcPrice'] + $goodsData['memberDcPrice'] + $goodsData['timeSalePrice']);
	
		if($goodsView['optionFl'] == 'y') {
			$goodsOpt = $goods->getGoodsOptionInfo($goodsData['optionSno']);
			if($goodsOpt['optionNoRecomMileage']) {
				$optionRecomNoMileage =$goodsOpt['optionNoRecomMileage'];
				$optionRecomNoMileageUnit =$goodsOpt['optionNoRecomMileageUnit'];
			}
		}

		if($recomMileageConfig['giveFl'] == 'y') { //추천인 적립금 사용할때
			if($recomMileagFl == 'g') { //개별설정
				//기존 마일리지
				$recomNoMileage = $goodsView['recomNoMileage'];
				$recomNoMileageUnit = $goodsView['recomNoMileageUnit'];
			}else { //통합설정
				//기존 통합설정
				$recomNoMileage =$recomMileageConfig['goods']; //통합 일반주문시 금액
				$recomNoMileageUnit =$recomMileageConfig['singleUnit']; //통합 일반주문시 금액단위
			}	

			//옵션 마일리지 존재할때
			if($optionRecomNoMileage) {
				$recomNoMileage = $optionRecomNoMileage;
				$recomNoMileageUnit = $optionRecomNoMileageUnit;
			}

			if($goodsData['divisionUseMileage'] || $goodsData['divisionGoodsDeliveryUseMileage']){ //주문시 마일리지 사용시
				if($recomMileageConfig['excludeFl'] == 'y'){ //예외조건 : 정상적 지급
					if($recomNoMileageUnit == 'price') {
						$setRecomMileage = $goodsData['goodsCnt']*$recomNoMileage;
					}else {
						$setRecomMileage = gd_number_figure(($settlePrice*$recomNoMileage/100), 0.1, 'round');
					}

					$totalMileage += $setRecomMileage;
				}else if($recomMileageConfig['excludeFl'] == 'n'){//예외조건 : 지급안함
					
				}else if($recomMileageConfig['excludeFl'] == 'r'){ //예외조건 : 지급률 재계산
					if($recomNoMileageUnit == 'price') {
						$setRecomMileage = $goodsData['goodsCnt']*$recomNoMileage;
					}else {
						$useMileage = $goodsData['divisionUseMileage']+$goodsData['divisionGoodsDeliveryUseMileage']; //사용 마일리지
						$setRecomMileage = gd_number_figure((($settlePrice-$useMileage)*$recomNoMileage/100), 0.1, 'round');
					}
					
					$totalMileage += $setRecomMileage;
				}
			}else { //주문시 마일리지 사용x
				$recomNoMileage =$recomMileageConfig['goods']; 
				$recomNoMileageUnit =$recomMileageConfig['singleUnit'];

				if($recomNoMileageUnit == 'price') {
					$setRecomMileage = $goodsData['goodsCnt']*$recomNoMileage;
				}else {
					$setRecomMileage = gd_number_figure(($settlePrice*$recomNoMileage/100), 0.1, 'round');
				}
				$totalMileage += $setRecomMileage;
			}
		}		
		return $totalMileage;
	}

	//정기배송 적립금 구하기
	public function recomSubMileagePrice($goodsData) 
	{
		$recomMileageConfig = gd_policy('member.recomMileageGive');
		$goods = \App::load('\\Component\\Goods\\Goods');
		$order = \App::load('Component\\Order\\Order');		
		$goodsView = $goods->getGoodsInfo($goodsData['goodsNo']);
		$recomMileagFl = $goodsView['recomMileageFl'];

		# (상품가격+상품의옵션가격+상품의텍스트옵션가격)*상품개수
		$settlePrice = ($goodsData['goodsPrice'] + $goodsData['optionTextPrice'] +$goodsData['optionPrice']) * $goodsData['goodsCnt'];
		
		# 타임세일,상품할인,회원할인 적용
		$settlePrice = $settlePrice - ($goodsData['goodsDcPrice'] + $goodsData['memberDcPrice'] + $goodsData['timeSalePrice']);
			
		if($goodsView['optionFl'] == 'y') {
			$goodsOpt = $goods->getGoodsOptionInfo($goodsData['optionSno']);
			if($goodsOpt['optionSubRecomMileage']) {
				$optionRecomSubMileage =$goodsOpt['optionSubRecomMileage'];
				$optionRecomSubMileageUnit =$goodsOpt['optionSubRecomMileageUnit'];
			}
		}

		if($recomMileageConfig['giveFl'] == 'y') {
			if($recomMileagFl == 'g') {//개별설정
				//기존 마일리지
				$recomSubMileage =$goodsView['recomSubMileage'];
				$recomSubMileageUnit =$goodsView['recomSubMileageUnit'];
			}else { //통합설정					
				$recomSubMileage = $recomMileageConfig['subGoods']; //통합 정기구독 주문시 금액
				$recomSubMileageUnit =$recomMileageConfig['subUnit']; //통합 정기구독 주문시 금액단위
			}

			//옵션 마일리지
			if($optionRecomSubMileage) {
				$recomSubMileage = $optionRecomSubMileage;
				$recomSubMileageUnit = $optionRecomSubMileageUnit;
			}
			
			//주문시 마일리지 사용시
			if($goodsData['divisionUseMileage'] || $goodsData['divisionGoodsDeliveryUseMileage']){ 
				if($recomMileageConfig['excludeFl'] == 'y'){ //예외조건 : 정상적 지급
					if($recomSubMileageUnit == 'price') {
						$setRecomMileage = $goodsData['goodsCnt']*$recomSubMileage;
					}else {
						$setRecomMileage = gd_number_figure(($settlePrice*$recomSubMileage/100), 0.1, 'round');
					}

					$totalMileage += $setRecomMileage;
				}else if($recomMileageConfig['excludeFl'] == 'n'){//예외조건 : 지급안함
					
				}else if($recomMileageConfig['excludeFl'] == 'r'){ //예외조건 : 지급률 재계산
					if($recomSubMileageUnit == 'price') {
						$setRecomMileage = $goodsData['goodsCnt']*$recomSubMileage;
					}else {
						$useMileage = $goodsData['divisionUseMileage']+$goodsData['divisionGoodsDeliveryUseMileage']; //사용 마일리지
						$setRecomMileage = gd_number_figure(($settlePrice-$useMileage)*$recomSubMileage/100, 0.1, 'round');
					}

					
					$totalMileage += $setRecomMileage;
				}
			}else { //주문시 마일리지 사용x
				$recomSubMileage = $recomMileageConfig['subGoods']; 
				$recomSubMileageUnit = $recomMileageConfig['subUnit'];

				if($recomSubMileageUnit == 'price') {
					$setRecomMileage = $goodsData['goodsCnt']*$recomSubMileage;
				}else {
					$setRecomMileage = gd_number_figure(($settlePrice*$recomSubMileage/100), 0.1, 'round');
				}
				$totalMileage += $setRecomMileage;
			}
		}
		return $totalMileage;
	}
	// 2024-01-09 wg-eric 페이스북 api 저장
	public function wgSaveOrderFacebookApi($orderInfo, $order, $pgName, $orderNo) {
		$pixelId = '794625862153992';
		$accessToken = 'EAAD5hwghCoABOzkA64eQI2wYgilECEZCRc8Avd2MaW2YAKQlPQvktfZBosKmiPAEtDNzSp6fInjZAOydCZCupkYztojHNZBGWXflNKOD60iQZBRx0VCmXnvT34h8AzZBDCcAFqdM60CYVqPAetWMCNK39RFjgB9XeChaqziFeFKFHsrK6eBeSt7V0F94sVx2D0s6gZDZD';

		if($pgName == 'sub') {
			$settlePrice = $orderInfo['settlePrice'] * 6;
		} else {
			$settlePrice = $orderInfo['settlePrice'];
		}

		$data = [
			[
				"event_name" => "Purchase",
				"event_time" => strtotime(date('Y-m-d H:i:s')),
				"user_data" => [
					"em" => [
						hash('sha256', strtolower(trim($orderInfo['orderEmail'])))
					],
					"ph" => [
						hash('sha256', $this->normalizeKoreanPhoneNumber($orderInfo['orderCellPhone']))
					],
					"client_ip_address" => $order['orderIp'],
					"client_user_agent" => "$CLIENT_USER_AGENT",
					//"fbc" => "fb.1.1554763741205.AbCdEfGhIjKlMnOpQrStUvWxYz1234567890",
					//"fbp" => "fb.1.1558571054389.1098115397"
				],
				"custom_data" => [
					"currency" => "krw",
					"value" => gd_isset($settlePrice, 0),
					"id" => $orderNo,

					//"contents" => [
						//[
							//"id" => "product123",
							//"quantity" => 1,
							//"delivery_category" => "home_delivery"
						//]
					//]
				],
				"event_source_url" => str_replace(':443', '', \request::getReferer()).'/order/order_end.php',
				"action_source" => "website"
			]
		];

		$url = "https://graph.facebook.com/v18.0/{$pixelId}/events";

		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, [
			'data' => json_encode($data),
			'access_token' => $accessToken
		]);

		$response = curl_exec($ch);
		curl_close($ch);

		// 로그 저장
		$sql = "
			INSERT INTO wg_orderFacebookApiLog(orderNo, value, regDt) VALUES ('".$orderNo."', '".$response."', now())
		";
		$this->db->query($sql);
	}
	// 휴대전화 변환
	function normalizeKoreanPhoneNumber($phoneNumber) {
		// 제거할 문자 및 기호 정의
		$removeChars = ['-', ' '];

		// 입력된 문자열에서 기호 및 문자 제거
		$phoneNumber = str_replace($removeChars, '', $phoneNumber);

		// 국가 코드를 추가하여 전화번호를 정규화
		$normalizedPhoneNumber = '82' . ltrim($phoneNumber, '0');

		return $normalizedPhoneNumber;
	}

	/**
     * 마일리지 지급
     * 지급예외 조건이 y이면서 사용한 마일리지가 있는 경우
     *
     * @param string $orderNo 주문 번호
     * @param arrau  $arrData 주문상태 변경 데이터
     *
     * @return bool 성공여부
     */
    public function setPlusMileageVariation($orderNo, $arrData)
    {
        // 기본설정 > 주문상태 > 혜택지급시점 정의가 변경되면 이 부분도 변경 필요
        $currentStatusPolicy = $this->getOrderCurrentStatusPolicy($orderNo);

        // 주문 시 마일리지 지급 설정의 유예기간 처리
        $currentMileagePolicy = $this->getOrderCurrentMileagePolicy($orderNo);

        $orderGoodsNo = [];
        $totalMileage = 0;
        foreach ($this->getOrderGoodsData($orderNo, null, null, null, null, false) as $key => $val) {
            // 마일리지 지급 조건과 현재 상태 비교 후 계산
            if (in_array($val['sno'], $arrData['sno'])){
                $changeStatusFlag = substr($arrData['changeStatus'], 0, 1);

                if($changeStatusFlag === 'z'){
                    //교환추가 주문상태는 교환추가완료 상태에서만 마일리지 지급 가능
                    if($arrData['changeStatus'] !== 'z5'){
                        return false;
                    }
                }
                else {
                    if(!in_array($changeStatusFlag, $currentStatusPolicy['mplus'])){
                        return false;
                    }
                    if ($changeStatusFlag == 'd' && $arrData['changeStatus'] != 'd2') {
                        return false;
                    }
                }

                // 쿠폰적립은 회원 쿠폰 차감시 마일리지 별도 지급하기 때문에 빼주고 처리해야 함
                $tmpTotalMileage = $val['totalGoodsMileage'] + $val['totalMemberMileage'];
                if ($val['orderStatus'] != 'r3' && $val['plusMileageFl'] == 'n' && $val['plusRestoreMileageFl'] == 'n' && $val['memNo'] > 0 && $tmpTotalMileage > 0) {
                    $totalMileage += $tmpTotalMileage;
                    $orderGoodsNo[] = $val['sno'];
                }
            }
        }

        // 조건이 충족한 경우 회원 마일리지 차감 후 주문서 업데이트
        if ($totalMileage > 0) {
            // 사용한 마일리지가 있는 경우 구매마일리지를 지급하지 않는다
            if ($val['mileageGiveExclude'] == 'n' && $val['totalDivisionUseMileage'] > 0) {
                return false;
            }

            // 주문 시 마일리지 지급 유예 기간 설정에 따른 지급 날짜 처리 - 스케줄러로 지급
            if ($currentMileagePolicy['give']['delayFl'] == 'y') {
                $giveDate = new \DateTime();
                $giveDate->modify('+' . $currentMileagePolicy['give']['delayDay'] . ' day');
                $giveDate = $giveDate->format('Y-m-d');

                $giveData['mileageGiveDt'] = $giveDate;
                $giveField = array_keys($giveData);
                $arrBind = $this->db->get_binding(DBTableField::tableOrderGoods(), $giveData, 'update', $giveField);
                $this->db->set_update_db(DB_ORDER_GOODS, $arrBind['param'], 'sno IN (\'' . implode('\',\'', $orderGoodsNo) . '\')', $arrBind['bind']);
                unset($arrBind);

                return true;
            }
            /** @var \Bundle\Component\Mileage\Mileage $mileage */
            $mileage = \App::load('\\Component\\Mileage\\Mileage');
            $mileage->setIsTran(false);
            
			//if ($this->changeStatusAuto) {
                $mileage->setSmsReserveTime(date('Y-m-d 15:00:00', strtotime('now')));
            //}

            if ($mileage->setMemberMileage($val['memNo'], $totalMileage, Mileage::REASON_CODE_GROUP . Mileage::REASON_CODE_ADD_GOODS_BUY, 'o', $orderNo)) {
                $orderData['plusMileageFl'] = 'y';
                $orderData['plusRestoreMileageFl'] = 'n';
                $compareField = array_keys($orderData);
                $arrBind = $this->db->get_binding(DBTableField::tableOrderGoods(), $orderData, 'update', $compareField);
                $this->db->set_update_db(DB_ORDER_GOODS, $arrBind['param'], 'sno IN (\'' . implode('\',\'', $orderGoodsNo) . '\')', $arrBind['bind']);
                unset($arrBind);

                return true;
            }
        }

        return false;
    }

	protected function setStatusChange($orderNo, $arrData, $autoProcess = false)
    {
        $returnData = parent::setStatusChange($orderNo, $arrData, $autoProcess);
		
		// 결제완료 notifly 이벤트 전송
		if($arrData['changeStatus'] == 'p1') {
			$member = \Session::get('member');
			if($member['memId']) {
				$notifly = \App::load('Component\\Notifly\\Notifly');
				$memberInfo = [];
				$memberInfo['memId'] = $member['memId'];
				$memberInfo['orderFl'] = 'y';
				$notifly->setUser($memberInfo);
				
				$eventParams = [];
				$segmentationEventParamKeys = [];
				$notifly->sendEvent('payComplete', $eventParams, $segmentationEventParamKeys);
			}
		}
	}
}