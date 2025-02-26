<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link      http://www.godo.co.kr
 */
namespace Component\Order;


/**
 * 주문 class
 * @author Shin Donggyu <artherot@godo.co.kr>
 */
class OrderNew extends \Bundle\Component\Order\OrderNew
{
	public function saveOrder($orderInfo, $order, $memberData, $deliveryInfo, $aCartData, $history, $couponInfo, $taxPrice)
    {
		parent::saveOrder($orderInfo, $order, $memberData, $deliveryInfo, $aCartData, $history, $couponInfo, $taxPrice);
		//루딕스-brown 추천인있을때 recomMileagePayFl = w
		$session = \App::getInstance('session');
		$memNo = $session->get('member.memNo');
		$sql = 'SELECT recommId FROM es_member WHERE memNo = '.$memNo;
		$recommId = $this->db->query_fetch($sql, null, false)['recommId'];
		
		if($recommId){
			$sql = 'UPDATE es_orderGoods SET recomMileagePayFl="w" WHERE orderNo ='.$this->orderNo;
			$this->db->slave()->query($sql);
		}

		return true;
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
}