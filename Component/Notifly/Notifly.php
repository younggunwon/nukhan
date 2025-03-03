<?php

namespace Component\Notifly;

class Notifly
{
	const TOKEN_URL = 'https://api.notifly.tech/authenticate';
	const EVENT_URL = 'https://api.notifly.tech/track-event';
	const USER_URL = 'https://api.notifly.tech/set-user-properties';
	const DELETE_USER_URL = 'https://api.notifly.tech/users';
	const PROJECT_ID = '0038af44d3fd5859a7ac0242eb089f27';
	const ACCESS_KEY = '5e466869a711579b82ffbd6ec6d51f17';
	const SECRET_KEY = 'OOvM)xA7i^rw';
	
	public $db;

	public function __construct()
    {
        $this->db = \App::load('DB');
    }

	public function getToken() {
		$policy = \App::load('\\Component\\Policy\\Policy');
		$tokenInfo = gd_policy('notifly.token');
		if(date('YmdHis') - $tokenInfo['createTime'] < 3000) {
			return $tokenInfo['token'];
		} else {
			// 2. 전송할 JSON 데이터 준비
			$data = [
				"accessKey" => self::ACCESS_KEY, // 실제 access_key로 교체
				"secretKey" => self::SECRET_KEY  // 실제 secret-key로 교체
			];

			// 3. JSON 형식으로 변환
			$jsonData = json_encode($data);

			// 4. cURL 초기화
			$ch = curl_init(self::TOKEN_URL);

			// 5. cURL 옵션 설정
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
			curl_setopt($ch, CURLOPT_POST, true);           // POST 요청 설정
			curl_setopt($ch, CURLOPT_HTTPHEADER, [          // 헤더 설정
				"Content-Type: application/json",
				"Content-Length: " . strlen($jsonData)
			]);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // 전송할 JSON 데이터 설정
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL 인증서 검증 비활성화 (테스트용, 운영 시 주의)

			// 6. 요청 실행 및 응답 받기
			$response = curl_exec($ch);
			

			// 7. 에러 체크
			if (curl_errno($ch)) {
				//echo "cURL 오류: " . curl_error($ch); // 오류 메시지 출력
				// 오류 로그 남기기
				$sql = "
					INSERT INTO wg_apiLog(apiType, requestData, responseData)
					VALUES('sendNotiflyEvent', '".json_encode($data, JSON_UNESCAPED_UNICODE)."', '".curl_error($ch)."');
				";
				$this->db->query($sql);
			} else {
				curl_close($ch);
				$sql = "
					INSERT INTO wg_apiLog(apiType, requestData, responseData)
					VALUES('sendNotiflyEvent', '".json_encode($data, JSON_UNESCAPED_UNICODE)."', '".$response."');
				";
				$this->db->query($sql);
				
				$response = json_decode($response, 1);
				$policy->setValue('notifly.token', ['token' => $response['data'], 'createTime' => date('YmdHis')]);
				return $response['data'];
			}
		}
	}

	public function sendEvent($eventName, $eventParams = [], $segmentationEventParamKeys = []) {
		$token = $this->getToken();
		// 2. 전송할 JSON 데이터 준비 (배열 형태)
		$data = [
			[
				"projectId" => self::PROJECT_ID, // 실제 프로젝트 ID로 교체
				"userId" => "tester",                   // 실제 사용자 ID로 교체
				"eventName" => "{$eventName}",             // 실제 이벤트 이름으로 교체
				"eventParams" => [],         // 빈 객체 (필요 시 속성 추가)
				"segmentationEventParamKeys" => []       // 빈 배열
			]
		];

		// 3. JSON 형식으로 변환
		$jsonData = json_encode($data);

		// 4. 인증 키 설정
		$authKey = "{$token}"; // 실제 auth-key로 교체

		// 5. cURL 초기화
		$ch = curl_init(self::EVENT_URL);

		// 6. cURL 옵션 설정
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
		curl_setopt($ch, CURLOPT_POST, true);           // POST 요청 설정
		curl_setopt($ch, CURLOPT_HTTPHEADER, [          // 헤더 설정
			"Content-Type: application/json",
			"Authorization: " . $authKey,
			"Content-Length: " . strlen($jsonData)
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // 전송할 JSON 데이터 설정
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL 검증 비활성화 (테스트용)

		// 7. 요청 실행 및 응답 받기
		$response = curl_exec($ch);

		// 8. 에러 체크 및 결과 출력
		if (curl_errno($ch)) {
			//echo "cURL 오류: " . curl_error($ch); // 오류 메시지 출력
			$sql = "
				INSERT INTO wg_apiLog(apiType, requestData, responseData)
				VALUES('sendNotiflyEvent', '".json_encode($data, JSON_UNESCAPED_UNICODE)."', '".curl_error($ch)."');
			";
			$this->db->query($sql);
		} else {
			$sql = "
				INSERT INTO wg_apiLog(apiType, requestData, responseData)
				VALUES('sendNotiflyEvent', '".json_encode($data, JSON_UNESCAPED_UNICODE)."', '".$response."');
			";
			$this->db->query($sql);
		}

		// 9. cURL 세션 종료
		curl_close($ch);
	}

	public function setUsers($userInfoArray) {
		$token = $this->getToken();
		
		// 1000개씩 데이터 분할
		$chunks = array_chunk($userInfoArray, 1000);
		
		foreach($chunks as $chunk) {
			$data = [];	
			foreach($chunk as $userInfo) {
				$data[] = [
					"projectId" => self::PROJECT_ID,
					"userProperties" => $userInfo,
					"userId" => $userInfo['memId']
				];
			}
			$jsonData = json_encode($data);

			// 인증 키 설정
			$authKey = "{$token}";

			// cURL 초기화
			$ch = curl_init(self::USER_URL);

			// cURL 옵션 설정
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json",
				"Authorization: " . $authKey,
				"Content-Length: " . strlen($jsonData)
			]);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

			// 요청 실행 및 응답 받기
			$response = curl_exec($ch);

			// 에러 체크 및 결과 출력
			if (curl_errno($ch)) {
				$sql = "
					INSERT INTO wg_apiLog(apiType, requestData, responseData)
					VALUES('setNotiflyUser', '".addslashes($jsonData)."', '".curl_error($ch)."');
				";
				$this->db->query($sql);
			} else {
				$sql = "
					INSERT INTO wg_apiLog(apiType, requestData, responseData)
					VALUES('setNotiflyUser', '".addslashes($jsonData)."', '".$response."');
				";
				$this->db->query($sql);
			}

			// cURL 세션 종료
			curl_close($ch);
		}
	}
	/**
		$userInfo = [
			'memId'	 => 'testUser',
			'$email' => 'email@email.com',
			'$phone_number' => '010101010',
			'testField3' => 'testValue3',
		];
	**/
	public function setUser($userInfo) {
		$token = $this->getToken();
		// 2. 전송할 JSON 데이터 준비 (배열 형태)

		// 3. JSON 형식으로 변환
		$data = [
			"projectId" => self::PROJECT_ID,
			"userProperties" => [
			],
			"userId" => $userInfo['memId']
		];
		foreach($userInfo as $key => $val) {
			if($key == 'memId') continue;
			$data['userProperties'][$key] = $val;
		}
		$jsonData = json_encode([$data]);

		// 4. 인증 키 설정
		$authKey = "{$token}"; // 실제 auth-key로 교체

		// 5. cURL 초기화
		$ch = curl_init(self::USER_URL);

		// 6. cURL 옵션 설정
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
		curl_setopt($ch, CURLOPT_POST, true);           // POST 요청 설정
		curl_setopt($ch, CURLOPT_HTTPHEADER, [          // 헤더 설정
			"Content-Type: application/json",
			"Authorization: " . $authKey,
			"Content-Length: " . strlen($jsonData)
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // 전송할 JSON 데이터 설정
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL 검증 비활성화 (테스트용)

		// 7. 요청 실행 및 응답 받기
		$response = curl_exec($ch);

		// 8. 에러 체크 및 결과 출력
		if (curl_errno($ch)) {
			//echo "cURL 오류: " . curl_error($ch); // 오류 메시지 출력
			$sql = "
				INSERT INTO wg_apiLog(apiType, requestData, responseData)
				VALUES('setNotiflyUser', '".addslashes($jsonData)."', '".curl_error($ch)."');
			";
			$this->db->query($sql);
		} else {
			$sql = "
				INSERT INTO wg_apiLog(apiType, requestData, responseData)
				VALUES('setNotiflyUser', '".addslashes($jsonData)."', '".$response."');
			";
			$this->db->query($sql);
		}

		// 9. cURL 세션 종료
		curl_close($ch);
	}

	public function deleteUser($userId) {
		$token = $this->getToken();
		
		// 2. 전송할 JSON 데이터 준비 (배열 형태)
		$data = [
			"projectId" => self::PROJECT_ID,
			"userId" => $userId
		];

		// 3. JSON 형식으로 변환
		$jsonData = json_encode($data);

		// 4. 인증 키 설정
		$authKey = "{$token}"; // 실제 auth-key로 교체

		// 5. cURL 초기화
		$ch = curl_init(self::DELETE_USER_URL);

		gd_debug([          // 헤더 설정
			"Content-Type: application/json",
			"Authorization: " . $authKey,
			"Content-Length: " . strlen($jsonData)
		]);

		// 6. cURL 옵션 설정
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 응답을 문자열로 반환
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE"); // POST를 DELETE로 변경
		curl_setopt($ch, CURLOPT_HTTPHEADER, [          // 헤더 설정
			"Content-Type: application/json",
			"Authorization: " . $authKey,
			"Content-Length: " . strlen($jsonData)
		]);

		gd_debug($jsonData);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData); // 전송할 JSON 데이터 설정
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL 검증 비활성화 (테스트용)

		// 7. 요청 실행 및 응답 받기
		$response = curl_exec($ch);

		// 8. 에러 체크 및 결과 출력
		if (curl_errno($ch)) {
			//echo "cURL 오류: " . curl_error($ch); // 오류 메시지 출력
			$sql = "
				INSERT INTO wg_apiLog(apiType, requestData, responseData)
				VALUES('deleteNotiflyUser', '".addslashes($jsonData)."', '".curl_error($ch)."');
			";
			$this->db->query($sql);
		} else {
			$sql = "
				INSERT INTO wg_apiLog(apiType, requestData, responseData)
				VALUES('deleteNotiflyUser', '".addslashes($jsonData)."', '".$response."');
			";
			$this->db->query($sql);
		}

		// 9. cURL 세션 종료
		curl_close($ch);
	}

	public function setUserOrderFl($orderNo) {
		// 주문정보로 회원정보 가져오기
		$sql = "SELECT memId, o.memNo FROM es_order o LEFT JOIN es_member m ON o.memNo = m.memNo WHERE o.orderNo = '". $orderNo ."'";
		$member = $this->db->query_fetch($sql)[0];
		if($member['memId']) {
			// 회원정보가 있을 경우 회원의 주문중 결제완료 이후의 주문이 존재하는지 검사
			$sql = "SELECT 1 FROM es_order o LEFT JOIN wm_subSchedules s ON o.orderNo = s.orderNo WHERE o.memNo = '{$member['memNo']}' AND s.idx IS NULL AND o.orderStatus NOT IN('f1', 'f2', 'f3', 'c1', 'c2', 'c3', 'r1', 'r2', 'r3', 'e1', 'e2', 'e3', 'b1', 'b2', 'b3')";
			$orderFl = $this->db->query_fetch($sql);
			if(!$orderFl) {
				$orderFl = 'n';
			}
			$sql = "SELECT 1 FROM es_order o LEFT JOIN wm_subSchedules s ON o.orderNo = s.orderNo WHERE o.memNo = '{$member['memNo']}' AND s.idx IS NOT NULL AND o.orderStatus NOT IN('f1', 'f2', 'f3', 'c1', 'c2', 'c3', 'r1', 'r2', 'r3', 'e1', 'e2', 'e3', 'b1', 'b2', 'b3')";
			$subscriptionPayFl = $this->db->query_fetch($sql);
			if(!$subscriptionPayFl) {
				$subscriptionPayFl = 'n';
			} else {
				$subscriptionPayFl = 'y';
			}
			$sql = "SELECT 1 FROM es_order o LEFT JOIN wm_subSchedules s ON o.orderNo = s.orderNo WHERE o.memNo = '{$member['memNo']}' AND s.idx IS NOT NULL AND o.orderStatus IN('d2', 's1')";
			$subscriptionFl = $this->db->query_fetch($sql);
			if($subscriptionFl) {
				$subscriptionFl = 'y';
			} else {
				$subscriptionFl = 'n';
			}
			
			$this->setUser(['memId' => $member['memId'], 'orderFl' => $orderFl, 'subscriptionPayFl' => $subscriptionPayFl, 'subscriptionFl' => $subscriptionFl]);
		}
	}

	// 구독 제품별 n회차 고객 노티플라이에 회원 속성 업데이트
	public function setUserSubscription() {
		$memberInfo = [];
		$sql = "
			SELECT m.memId, goodsNo, idxApply, COUNT(*) as cnt FROM wm_subSchedules s
			LEFT JOIN es_orderGoods og
			ON s.orderNo = og.orderNo AND og.goodsType != 'addGoods'
            LEFT JOIN es_member m
			ON s.memNo = m.memNo
			WHERE s.orderNo IS NOT NULL
			AND og.orderStatus NOT IN('c1','c2','c3','f1','f2','f3','f4','b1','b2','b3','e1','e2','e3','r1','r2','r3')
			GROUP BY s.memNo, idxApply
		";
		$subscription = $this->db->query_fetch($sql);
		foreach($subscription as $sub) {
			$memberInfo[$sub['memId']][$sub['goodsNo']] = $sub['cnt'];
		}

		$sendMemberData = [];
		$key = 0;
		foreach($memberInfo as $memId => $memberGoodsData) {
			$key++;
			$sendMemberData[$key]['memId'] = $memId;
			foreach($memberGoodsData as $goodsNo => $cnt) {
				$sendMemberData[$key]['subscriptionPay'.$goodsNo] = $cnt;
				$sendMemberData[$key]['subscriptionPayFl'] = 'y';
			}
		}
		$this->setUsers($sendMemberData);

		$memberInfo = [];
		$sql = "
			SELECT m.memId, goodsNo, idxApply, COUNT(*) as cnt FROM wm_subSchedules s
			LEFT JOIN es_orderGoods og
			ON s.orderNo = og.orderNo AND og.goodsType != 'addGoods'
            LEFT JOIN es_member m
			ON s.memNo = m.memNo
			WHERE s.orderNo IS NOT NULL
			AND og.orderStatus IN('d2', 's1')
			GROUP BY s.memNo, idxApply
		";
		$subscription = $this->db->query_fetch($sql);
		foreach($subscription as $sub) {
			$memberInfo[$sub['memId']][$sub['goodsNo']] = $sub['cnt'];
		}

		$sendMemberData = [];
		$key = 0;
		foreach($memberInfo as $memId => $memberGoodsData) {
			$key++;
			$sendMemberData[$key]['memId'] = $memId;
			foreach($memberGoodsData as $goodsNo => $cnt) {
				$sendMemberData[$key]['subscription'.$goodsNo] = $cnt;
				$sendMemberData[$key]['subscriptionFl'] = 'n';
			}
		}
		$this->setUsers($sendMemberData);
	}
}