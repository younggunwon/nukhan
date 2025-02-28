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
		$jsonData = json_encode($data);

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
}

