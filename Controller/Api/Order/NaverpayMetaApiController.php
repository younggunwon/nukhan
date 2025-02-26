<?php

namespace Controller\Api\Order;

class NaverpayMetaApiController extends \Controller\Api\Controller
{
	public function index() {
		$this->db = \App::load('DB');

		$sql = "
			SELECT o.*, oi.orderEmail, oi.orderCellPhone FROM es_order as o
			LEFT JOIN es_orderInfo as oi ON oi.orderNo = o.orderNo
			WHERE o.orderChannelFl = 'naverpay'
			AND o.wgMetaNaverPayFl = 'n'
			AND o.regDt >= CURDATE() - INTERVAL 1 DAY
			AND o.orderStatus NOT LIKE 'c%' 
			AND o.orderStatus NOT LIKE 'f%'
		";
		$result = $this->db->slave()->query_fetch($sql);
		gd_debug('네이버페이 주문조회 쿼리 : ' . $sql);
		gd_debug('네이버페이 주문 개수 : ' . count($result));

		$pixelId = '794625862153992';
		$accessToken = 'EAAD5hwghCoABOzkA64eQI2wYgilECEZCRc8Avd2MaW2YAKQlPQvktfZBosKmiPAEtDNzSp6fInjZAOydCZCupkYztojHNZBGWXflNKOD60iQZBRx0VCmXnvT34h8AzZBDCcAFqdM60CYVqPAetWMCNK39RFjgB9XeChaqziFeFKFHsrK6eBeSt7V0F94sVx2D0s6gZDZD';

		foreach($result as $key => $val) {
			$data = [
				[
					"event_name" => "Purchase",
					"event_time" => strtotime(date('Y-m-d H:i:s')),
					"user_data" => [
						"em" => [
							hash('sha256', strtolower(trim($val['orderEmail'])))
						],
						"ph" => [
							hash('sha256', $this->normalizeKoreanPhoneNumber($val['orderCellPhone']))
						],
						"client_ip_address" => $val['orderIp'],
						"client_user_agent" => "$CLIENT_USER_AGENT",
						//"fbc" => "fb.1.1554763741205.AbCdEfGhIjKlMnOpQrStUvWxYz1234567890",
						//"fbp" => "fb.1.1558571054389.1098115397"
					],
					"custom_data" => [
						"currency" => "krw",
						"value" => gd_isset($val['settlePrice'], 0),
						"id" => $val['orderNo'],

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

			// Output the response from Facebook Graph API
			gd_debug($response);

			// 완료 시 실행 여부 y로 저장
			$sql = "
				UPDATE es_order set wgMetaNaverPayFl = 'y'
				WHERE orderNo = '".$val['orderNo']."'
			";
			$this->db->query($sql);
			gd_debug('완료 시 실행 여부 y로 저장 : ' . $sql);
		}

		exit;
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