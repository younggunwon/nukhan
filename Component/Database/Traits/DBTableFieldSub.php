<?php

namespace Component\Database\Traits;

/**
* 정기결제 관련 테이블 
*
* @author webnmobile
*/
trait DBTableFieldSub
{
	/**
	* 정기배송 설정
	*
	*/
	public static function tableWmSubscriptionConf()
	{
		$arrField = [
			['val' => 'useType', 'typ' => 's', 'def' => 'test'], // 사용형태, real - 실사용, test - 테스트	
			['val' => 'mid', 'typ' => 's', 'def' => ''], // 상점ID(MID)
			['val' => 'signKey', 'typ' => 's', 'def' => ''], // 사인키(Signkey)
			['val' => 'lightKey', 'typ' => 's', 'def' => ''], // 이니라이트키
			['val' => 'deliveryYoils', 'typ' => 's', 'def' => ''], // 배송가능요일
			['val' => 'smsPayBeforeDay', 'typ' => 'i', 'def' => 0], // 정기결제과금 사전알림
			['val' => 'payDayBeforeDelivery', 'typ' => 'i', 'def' => 0], // 정기결제일
			['val' => 'cancelEa', 'typ' => 'i', 'def' => 0], // 정기배송취소 최소 구매횟수
			['val' => 'deliveryCycle', 'typ' => 's', 'def' => ''], // 배송주기 
			['val' => 'deliveryEa', 'typ' => 's', 'def' => ''], // 배송횟수 
			['val' => 'deliveryEaDiscount', 'typ' => 's', 'def' => ''], // 배송회차별 할인율
			['val' => 'deliveryEaDiscountType', 'typ' => 's', 'def' => 'cycle'], // 배송회차별 할인 적용방식
			['val' => 'smsTemplate', 'typ' => 's', 'def' => ''], // 결제알림 SMS문구
			['val' => 'terms', 'typ' => 's', 'def' => ''], // 정기배송약관
			['val' => 'cardTerms', 'typ' => 's', 'def' => ''],  // 결제카드등록약관
			['val' => 'useFirstDelivery', 'typ' => 'i', 'def' => 0], // 별도 배송정책 사용
			['val' => 'firstDeliverySno', 'typ' => 'i', 'def' => 0], // 배송정책 선택
			['val' => 'togetherGoodsNo', 'typ' => 's', 'def' => ''], // 함께 구매 상품 
			['val' => 'pause', 'typ' => 's', 'def' => ''], // 일시정지기간
			['val' => 'orderTerms', 'typ' => 's', 'def' => ''], // 구매안내
			['val' => 'min_order', 'typ' => 'i', 'def' => '2'], // 구매안내
			['val' => 'unitPrecision', 'typ' => 's', 'def' => '0.1'], // 정기결제단위설정
			['val' => 'unitRound', 'typ' => 's', 'def' => 'floor'], // 버림올림
			['val' => 'memberGroupSno', 'typ' => 'i', 'def' => '0'], // 정기결제 신청시회원등급
			['val' => 'order_fail_retry', 'typ' => 's', 'def' => ''], // 결제실패건 재시도
			
		    ['val' => 'failSmsUse', 'typ' => 's', 'def' => 'n'], // 결제실패 SMS문구_사용여부
		    ['val' => 'failSmsTemplate', 'typ' => 's', 'def' => ''], // 결제실패 SMS문구
		    ['val' => 'rFailSmsUse', 'typ' => 's', 'def' => 'n'], // 재결제실패 SMS문구_사용여부
		    ['val' => 'rFailSmsTemplate', 'typ' => 's', 'def' => ''], // 재결제실패 SMS문구

		];
		
		return $arrField;
	}
	
	/**
	* 결제카드 
	*
	*/
	public static function tableWmSubscriptionCards()
	{
		$arrField = [
			['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호 
			['val' => 'cardNm', 'typ' => 's', 'def' => ''], // 카드명
			['val' => 'payKey', 'typ' => 's', 'def' => ''], // 빌링키 
			['val' => 'settleLog', 'typ' => 's', 'def' => ''], // 신청로그
			['val' => 'password', 'typ' => 's', 'def' => ''], // 결제비밀번호 
			['val' => 'memo', 'typ' => 's', 'def' => ''], // 메모
		];
		
		return $arrField;
	}
	
	/**
	* 정기배송 카드 설정 
	*
	*/
	public static function tableWmCardSet() 
	{
		$arrField = [
			['val' => 'cardType', 'typ' => 's', 'def' => 'card'], // 카드종류, card - 신용카드, bank - 은행 
			['val' => 'cardCode', 'typ' => 's', 'def' => ''], // PG 카드, 은행 구분번호
			['val' => 'cardNm', 'typ' => 's', 'def' => ''], // 카드명, 은행명 
			['val' => 'backgroundColor', 'typ' => 's', 'def' => ''], // 카드 배경색
			['val' => 'fontColor', 'typ' => 's', 'def' => ''], // 폰트배경색
		];
		
		return $arrField;
	}
	
	/* 정기배송 장바구니 */
	public static function tableWmCartSub()
	{
		$arrField = self::tableCart();
		
		return $arrField;
	}

	/* 정기배송 장바구니(수기) */
	public static function tableWmCartSubWrite()
	{
		$arrField = self::tableCart();
		$arrField[] = ["val" => 'isTemp', 'typ' => 'i', 'def' => 0]; // 임시데이터 여부
		$arrField[] = ["val" => 'seq', 'typ' => 'i', 'def' => 0]; // 정기배송 회차 순서 0
		
		return $arrField;
	}
	
	/**
	* 정기배송 신청 정보 
	* 
	*/
	public static function tableWmSubApplyInfo()
	{
		$arrField = [
			['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호
			['val' => 'deliveryPeriod', 'typ' => 's', 'def' => '1_week'], // 배송주기 
			['val' => 'deliveryEa', 'typ' => 'i', 'def' => 1], // 배송횟수
			['val' => 'autoExtend', 'typ' => 'i', 'def' => 1], // 자동연장여부
			['val' => 'idxCard', 'typ' => 'i', 'def' => 0], // 결제카드번호
		];
		
		return $arrField;
	}
	
	/**
	* 정기배송 메인 배송지 정보 
	*
	*/
	public static function tableWmSubDeliveryInfo()
	{
		$arrField = [
			['val' => 'idxApply', 'typ' => 'i', 'def' => 0], // 정기결제 신청IDX
			['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호
			['val' => 'orderName', 'typ' => 's', 'def' => ''], // 주문자 이름
			['val' => 'orderEmail', 'typ' => 's', 'def' => ''], // 주문자 e-mail
			['val' => 'orderPhone', 'typ' => 's', 'def' => ''], // 주문자 전화번호
			['val' => 'orderCellPhone', 'typ' => 's', 'def' => ''], // 주문자 핸드폰 번호
			['val' => 'orderZipcode', 'typ' => 's', 'def' => ''], // 주문자 우편번호
			['val' => 'orderZonecode', 'typ' => 's', 'def' => ''], // 주문자 우편번호(10자리)
			['val' => 'orderAddress', 'typ' => 's', 'def' => ''], // 주문자 주소
			['val' => 'orderAddressSub', 'typ' => 's', 'def' => ''], // 주문자 나머지 주소
			['val' => 'receiverName', 'typ' => 's', 'def' => ''], // 수취인 이름
			['val' => 'receiverPhone', 'typ' => 's', 'def' => ''], // 수취인 전화번호
			['val' => 'receiverCellPhone', 'typ' => 's', 'def' => ''], // 수취인 핸드폰 번호
			['val' => 'receiverZipcode', 'typ' => 's', 'def' => ''], // 수취인 우편번호
			['val' => 'receiverZonecode', 'typ' => 's', 'def' => ''], // 수취인 우편번호(10자리)
			['val' => 'receiverAddress', 'typ' => 's', 'def' => ''], // 수취인 주소
			['val' => 'receiverAddressSub', 'typ' => 's', 'def' => ''], // 수취인 나머지 주소
			['val' => 'orderMemo', 'typ' => 's', 'def' => ''], // 배송메모
		];
	
		return $arrField;
	}
	
	/**
	* 정기배송 스케줄 정보 
	*
	*/
	public static function tableWmSubSchedules()
	{
		$arrField = [
			['val' => 'idxApply', 'typ' => 'i', 'def' => 0], // 정기결제 신청IDX
			['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호
			['val' => 'status', 'typ' => 's', 'def' => 'ready'], // 결제 처리 상태, ready - 결제예정, paid - 결제완료, stop - 결제 중단
			['val' => 'orderName', 'typ' => 's', 'def' => ''], // 주문자 이름
			['val' => 'orderEmail', 'typ' => 's', 'def' => ''], // 주문자 e-mail
			['val' => 'orderPhone', 'typ' => 's', 'def' => ''], // 주문자 전화번호
			['val' => 'orderCellPhone', 'typ' => 's', 'def' => ''], // 주문자 핸드폰 번호
			['val' => 'orderZipcode', 'typ' => 's', 'def' => ''], // 주문자 우편번호
			['val' => 'orderZonecode', 'typ' => 's', 'def' => ''], // 주문자 우편번호(10자리)
			['val' => 'orderAddress', 'typ' => 's', 'def' => ''], // 주문자 주소
			['val' => 'orderAddressSub', 'typ' => 's', 'def' => ''], // 주문자 나머지 주소
			['val' => 'receiverName', 'typ' => 's', 'def' => ''], // 수취인 이름
			['val' => 'receiverPhone', 'typ' => 's', 'def' => ''], // 수취인 전화번호
			['val' => 'receiverCellPhone', 'typ' => 's', 'def' => ''], // 수취인 핸드폰 번호
			['val' => 'receiverZipcode', 'typ' => 's', 'def' => ''], // 수취인 우편번호
			['val' => 'receiverZonecode', 'typ' => 's', 'def' => ''], // 수취인 우편번호(10자리)
			['val' => 'receiverAddress', 'typ' => 's', 'def' => ''], // 수취인 주소
			['val' => 'receiverAddressSub', 'typ' => 's', 'def' => ''], // 수취인 나머지 주소
			['val' => 'orderMemo', 'typ' => 's', 'def' => ''], // 배송메모
			['val' => 'deliveryStamp', 'typ' => 'i', 'def' => 0], // 희망배송일
			['val' => 'payStamp', 'typ' => 'i', 'def' => 0], // 결재 예정일 
			['val' => 'smsSentStamp', 'typ' => 'i', 'def' => 0], // SMS결제 알림 전송일
			['val' => 'orderNo', 'typ' => 's', 'def' => ''], // 주문번호
		];
		
		return $arrField;
	}
	
	/**
	* 정기배송 신청 상품(공통)
	*
	*/
	public static function tableWmSubApplyGoods()
	{
		$arrField = [
			['val' => 'idxApply', 'typ' => 'i', 'def' => 0], // 정기배송 신청 IDX
			['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호
			['val' => 'goodsNo', 'typ' => 'i', 'def' => 0], // 상품번호
			['val' => 'optionSno', 'typ' => 'i', 'def' => 0], // 옵션번호
			['val' => 'goodsCnt', 'typ' => 'i', 'def' => 0], // 상품 구매 수량
			['val' => 'addGoodsNo', 'typ' => 's', 'def' => ''], // 추가 상품 번호
			['val' => 'addGoodsCnt', 'typ' => 's', 'def' => ''], // 추가 상품 수량
			['val' => 'optionText', 'typ' => 'i', 'def' => 0], // 텍스트 옵션 정보
		];
		
		return $arrField;
	}
	
	/**
	* 정기배송 신청 개별상품
	*
	*/
	public static function tableWmSubSchedulesGoods()
	{
		$arrField = [
			['val' => 'idxSchedule', 'typ' => 'i', 'def' => 0], // 스케줄 등록 IDX
			['val' => 'memNo', 'typ' => 'i', 'def' => 0], // 회원번호
			['val' => 'goodsNo', 'typ' => 'i', 'def' => 0], // 상품번호
			['val' => 'optionSno', 'typ' => 'i', 'def' => 0], // 옵션번호
			['val' => 'goodsCnt', 'typ' => 'i', 'def' => 0], // 상품 구매 수량
			['val' => 'addGoodsNo', 'typ' => 's', 'def' => ''], // 추가 상품 번호
			['val' => 'addGoodsCnt', 'typ' => 's', 'def' => ''], // 추가 상품 수량
			['val' => 'optionText', 'typ' => 'i', 'def' => 0], // 텍스트 옵션 정보
		];
		
		return $arrField;
	}
	
	/**
	* 정기배송 사은품(공통)
	*
	*/
	public static function tableWmSubGifts()
	{
		$arrField = [
			['val' => 'orderCnt', 'typ' => 'i', 'def' => 1], // 주문횟수 
			['val' => 'goodsNo', 'typ' => 'i', 'def' => 0], // 상품번호
			['val' => 'listOrder', 'typ' => 'i', 'def' => 0], // 진열순서
			['val' => 'isOpen', 'typ' => 'i', 'def' => 0], // 진열여부 
		];
		
		return $arrField;
	}
	
	/**
	* 정기배송 사은품(상품별)
	*
	*/
	public static function tableWmSubGoodsGifts()
	{
		$arrField = self::tableWmSubGifts();
		$arrField[] = ['val' => 'rootGoodsNo', 'typ' => 'i', 'def' => 0]; // 사은품 적용될 상품번호
		
		return $arrField;
	}
}