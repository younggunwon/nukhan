<?php

namespace Controller\Front\Member\Kakao;

use Component\Member\MemberSnsService;
use Component\Member\MyPage;
use Component\Godo\GodoKakaoServerApi;
use Component\Attendance\AttendanceCheckLogin;
use Component\Member\Util\MemberUtil;
use Component\Member\Member;
use Exception;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Debug\Exception\AlertCloseException;
use Component\Member\MemberSnsDAO;

use Component\Policy\SnsLoginPolicy;
use Component\Storage\Storage;
use Framework\Debug\Exception\DatabaseException;
use Framework\Object\SimpleStorage;
use Framework\Utility\ComponentUtils;
use Framework\Utility\StringUtils;

/**
 * 카카오 로그인 및 회원가입
 * @package Bundle\Controller\Front\Member\Kakao
 * @author  sojoeng
 */
class KakaoLoginController extends \Bundle\Controller\Front\Member\Kakao\KakaoLoginController
{
    public function index()
    {


        $request = \App::getInstance('request');
        $session = \App::getInstance('session');
        $logger = \App::getInstance('logger');
        $logger->info(sprintf('start controller: %s', __METHOD__));

        $kakaoType=null;

        try {
			//2023-11-09 루딕스-brown 카카오 로그인일때

			if($request->get()->all()['kakaoType'] == 'login' || $request->get()->all()['kakaoType'] == 'join_method') {
				$session->set('kakaoLoginFl', 'y');
			}
            $functionName = 'popup';
            if(gd_is_skin_division()) {
                $functionName = 'gd_popup';
            }

            $kakaoApi = new GodoKakaoServerApi();
            $memberSnsService = new MemberSnsService();

            //state 값 decode
            $state = json_decode($request->get()->get('state'), true);

            //state 값을 이용해 분기처리
            $kakaoType= $state['kakaoType'];

            //returnUrl 추출
            // 추가내용(trim) (2020.02.20)
            $returnURLFromAuth = trim(rawurldecode($state['returnUrl']));

            // saveAutologin
            $saveAutoLogin = $state['saveAutoLogin'];

            // 카카오싱크를 통한 회원가입인 경우
            $kakaosync = $state['kakaosync'];

            // 가입코드
            $regiPath = $state['regiPath'];

            //카카오계정 로그인 팝업창에서 동의안함 클릭시 팝업창 닫힘 처리
            // 카카오 자동로그인시 회원이 아닐경우 (error : auto_login) - 2020.08.08 추가
            // 파라미터 추가 : kakao_login_check=y
            if($request->get()->get('error') == 'access_denied' || $request->get()->get('error') =='auto_login'){
                $logger->channel('kakaoLogin')->info($request->get()->get('error_description'));
               $js="
               if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                    if('".$kakaoType."' == 'join_method'){
                        location.href='../../member/join_method.php';
                    } else if('".$kakaoType."' == 'auto_login'){
                        location.href='/?kakao_login_check=y';
                    }else{
                        location.href='../../mypage/my_page.php';
                    }
                } else {
                    if('".$kakaoType."' == 'auto_login'){
                        location.href='/?kakao_login_check=y';
                    } else {
                        opener.location.reload();
                        self.close();
                    }
                }";
                $this->js($js);
            }


            if($code = $request->get()->get('code')){


                if($endlen = (strpos($request->getRequestUri(), '?'))){
                    $returnURL = $request->getDomainUrl() . substr($request->getRequestUri(), 0, $endlen);
                }

                // 토큰 정보
                $properties = $kakaoApi->getToken($code, $returnURL);

                //사용자 정보
                $userInfo = $kakaoApi->getUserInfo($properties['access_token']);

                //세션에 사용자 정보 저장
                $session->set(GodoKakaoServerApi::SESSION_USER_PROFILE, $userInfo);
                $session->set(GodoKakaoServerApi::SESSION_ACCESS_TOKEN, $properties);

                // 테스트용
                // if ($this->get_client_ip()=="211.201.140.91") {
                //     var_dump($userInfo);
                //     exit();
                // }

                $memberSns = $memberSnsService->getMemberSnsByUUID($userInfo['id']);

                // kakao 아이디로 회원가입한 회원인지 검증
                if($memberSnsService->validateMemberSns($memberSns)) {
                    $logger->channel('kakaoLogin')->info('pass validationMemberSns');

                    if($session->has(Member::SESSION_MEMBER_LOGIN)){
                        //일반회원 카카오 아이디 연동 거부 처리
                        if($kakaoType == 'connect'){
                            $logger->channel('kakaoLogin')->info('Deny app link');
                            $js = "
                               alert('" . __('이미 다른 회원정보와 연결된 계정입니다. 다른 계정을 이용해주세요.') . "');
                               if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                                  location.href='../../mypage/my_page.php';
                               } else {
                                  self.close();
                               }
                             ";
                            $this->js($js);
                        }
                        //마이페이지 회원정보 수정시 인증 정보 다를때 처리
                        if ($memberSns['memNo'] != $session->get(Member::SESSION_MEMBER_LOGIN . '.memNo', 0)) {
                            $logger->info($session->get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN));
                            $logger->channel('kakaoLogin')->info('not eq memNo');
                            $js = "
                                    alert('" . __('로그인 시 인증한 정보와 다릅니다 .') . "');
                                    if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                                        location.href='../../mypage/my_page_password.php';
                                    } else {
                                        opener.location.href='../../mypage/my_page_password.php';
                                        self.close();
                                    }
                                ";
                            $this->js($js);
                        }

                        //마이페이지 회원정보 수정시 인증
                        if($kakaoType == 'my_page_password'){
                            $memberSnsService->saveToken($userInfo['id'], $properties['access_token'], $properties['refresh_token']);
                            $logger->channel('kakaoLogin')->info('move my page');
                            $session->set(MyPage::SESSION_MY_PAGE_PASSWORD, true);
                            $js="
                                 if (typeof(window.top.layerSearchArea) == \"object\") {
                                        parent.location.href='../../mypage/my_page.php';
                                    } else if (window.opener === null || window.opener === undefined) {
                                        location.href='" . gd_isset($returnURLFromAuth, '../../mypage/my_page.php') . "';
                                    } else {
                                        opener.location.href='../../mypage/my_page.php';
                                        self.close();
                                    }
                            ";
                            $this->js($js);
                        }

                        //회원탈퇴
                        if($kakaoType == 'hack_out') {
                            $logger->channel('kakaoLogin')->info('hack out kakao id');
                            $session->set(GodoKakaoServerApi::SESSION_KAKAO_HACK, true);
                            $js = "
                                   if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                                       location.href='../../mypage/hack_out.php';
                                   } else {
                                       opener.location.href='../../mypage/hack_out.php';
                                       self.close();
                                   }
                                   ";
                            $this->js($js);
                        }

                        //일반회원 마이페이지 카카오 아이디 연동 해제
                        if($kakaoType == 'disconnect') {
                            if($memberSns['snsJoinFl'] == 'y'){
                                $logger->channel('kakaoLogin')->info('Impossible disconnect member joined by kakao');
                                $js=" alert('" . __('카카오로 가입한 회원님은 연결을 해제 할 수 없습니다.') . "');";
                                $this->js($js);
                            }
                            if ($session->has(GodoKakaoServerApi::SESSION_ACCESS_TOKEN)) {
                                $kakaoToken = $session->get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                                $kakaoApi->unlink($kakaoToken['access_token']);
                                $session->del(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                                $memberSnsService = new MemberSnsService();
                                $memberSnsService->disconnectSns($memberSns['memNo']);
                                $session->set(Member::SESSION_MEMBER_LOGIN . '.snsTypeFl', '');
                                $session->set(Member::SESSION_MEMBER_LOGIN . '.accessToken', '');
                                $session->set(Member::SESSION_MEMBER_LOGIN . '.snsJoinFl', '');
                                $session->set(Member::SESSION_MEMBER_LOGIN . '.connectFl', '');
                                $js = "
                                alert('" . __('카카오 연결이 해제되었습니다.') . "');
                                if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                                    location.href='../../mypage/my_page.php';
                                } else {
                                    opener.location.href='../../mypage/my_page.php';
                                    self.close();
                                }
                            ";
                                $this->js($js);
                            }
                        }
                    }
                    if (isset($memberSns['accessToken'])) {
                        $logger->info('isset accessToken');
                        $kakaoApi->logout($memberSns['accessToken']);
                        $logger->info('success logout');
                    }

                    // 카카오 아이디 로그인
                    $memberSnsService->saveToken($userInfo['id'], $properties['access_token'], $properties['refresh_token']);
                    $memberSnsService->loginBySns($userInfo['id']);
                    if ($saveAutoLogin == 'y') $session->set(Member::SESSION_MYAPP_SNS_AUTO_LOGIN, 'y');
                    $logger->channel('kakaoLogin')->info('success login by kakao');

                    $db = \App::getInstance('DB');

                    try {
                        $db->begin_tran();
                        $check = new AttendanceCheckLogin();
                        $message = $check->attendanceLogin();
                        $db->commit();

                        // 에이스 카운터 로그인 스크립트
                        $acecounterScript = \App::load('\\Component\\Nhn\\AcecounterCommonScript');
                        $acecounterUse = $acecounterScript->getAcecounterUseCheck();
                        if ($acecounterUse) {
                            echo $acecounterScript->getLoginScript();
                        }

                        $logger->info('commit attendance login');
                        if ($message) {
                            $logger->info(sprintf('has attendance message: %s', $message));
                            $js = "
                                    alert('" . $message . "');
                                    if (typeof(window.top.layerSearchArea) == 'object') {
                                        parent.location.href='" . $returnURLFromAuth . "';
                                    } else if (window.opener === null || window.opener === undefined) {
                                        location.href='" . $returnURLFromAuth . "';
                                    } else {
                                        opener.location.href='" . $returnURLFromAuth . "';
                                        self.close();
                                    }
                                ";
                            $this->js($js);
                        }
                    } catch (Exception $e) {
                        $db->rollback();
                        $logger->error(__METHOD__ . ', ' . $e->getFile() . '[' . $e->getLine() . '], ' . $e->getMessage());
                    }

                    if ($kakaoType == 'join_method') {

                        $logger->channel('kakaoLogin')->info('already join member');

                        // 외부 링크 접속시 리턴 URL 없을시(2020.02.20)
                        if ($returnURLFromAuth == '') $returnURLFromAuth = '/';

                        $js = "
                                alert('" . __('로그인 되었습니다.') . "');
                                if (typeof(window.top.layerSearchArea) == 'object') {
                                    parent.location.href='" .urldecode($returnURLFromAuth) . "';
                                } else if (window.opener === undefined || window.opener === null) {
                                    location.href='" . urldecode($returnURLFromAuth) . "';
                                } else {
                                    opener.location.href='" . urldecode($returnURLFromAuth) . "';
                                    self.close();
                                }
                            ";
                        $this->js($js);
                    }
                    $logger->channel('kakaoLogin')->info('move return url');

                    $loginReturnUrl = $returnURLFromAuth;
                    if ($request->isMyapp() && $request->get()->get('saveAutoLogin') == 'y') {
                        $loginReturnUrl .= '?saveAutoLogin=' . $request->get()->get('saveAutoLogin');
                    }

                    // 카카오싱크 회원가입 이후 강제 로그인 시킬 경우 (2020.02.20)
                    if ($kakaosync == 'y') {
                        if ($loginReturnUrl == '' || $loginReturnUrl == '/member/join_method.php') {
                            $loginReturnUrl = '../../main/index.php?kakaosync=y&kakao_id='. $state['kakao_id'];
                        } else {
                            if (strpos($loginReturnUrl, "?")) {
                                $loginReturnUrl .= '&kakaosync=y&kakao_id='. $state['kakao_id'];
                            } else {
                                $loginReturnUrl .= '?kakaosync=y&kakao_id='. $state['kakao_id'];
                            }
                        }
                    }

                    // 카카오 자동로그인 작업 (2020.08.08)
                    if ($kakaoType == 'auto_login') {
                        if (!$loginReturnUrl) {
                            $loginReturnUrl = '/';
                        }
                        if (strpos($loginReturnUrl, "?")) {
                            $loginReturnUrl .= '&kakao_login_check=y';
                        } else {
                            $loginReturnUrl .= '?kakao_login_check=y';
                        }
                    }


                    $js = "
                            if (typeof(window.top.layerSearchArea) == 'object') {
                                parent.location.href='" . $loginReturnUrl . "';
                            } else if (window.opener === null || window.opener === undefined) {
                                location.href='" . $loginReturnUrl . "';
                            } else {
                                opener.location.href='" . $loginReturnUrl . "';
                                self.close();
                            }
                        ";
                    $this->js($js);
                }

                // 일반회원 카카오 아이디 연동 처리
                if($kakaoType == 'connect') {
                    $kakaoToken = $session->get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                    $kakaoApi->appLink($kakaoToken['access_token']);
                    $memberSnsService->connectSns($session->get(Member::SESSION_MEMBER_LOGIN . '.memNo'), $userInfo['id'], $properties['access_token'], 'kakao');
                    $memberSnsService->saveToken($userInfo['id'], $properties['access_token'], $properties['refresh_token']);
                    $session->set(Member::SESSION_MEMBER_LOGIN . '.snsTypeFl', 'kakao');
                    $session->set(Member::SESSION_MEMBER_LOGIN . '.accessToken', $properties['access_token']);
                    $session->set(Member::SESSION_MEMBER_LOGIN . '.snsJoinFl', 'n');
                    $session->set(Member::SESSION_MEMBER_LOGIN . '.connectFl', 'y');
                    $js = "
                                alert('" . __('계정 연결이 완료되었습니다. 로그인 시 연결된 계정으로 로그인 하실 수 있습니다.') . "');
                                if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                                    location.href='../../mypage/my_page.php';
                                } else {
                                    opener.location.href='../../mypage/my_page.php';
                                    self.close();
                                }
                            ";
                    $this->js($js);
                }

                /********************* 카카오싱크 간편 회원가입을 위한 간편 회원가입 프로세스 시작 *********************/
                /* 카카오싱크를 통한 회원가입은 직접 DB Insert */
                /* 2020.02.17 */

                if ($kakaoType == 'join_method') {
                    $logger->channel('kakaoLogin')->info('kakao id applink success');

                    // 회원털퇴 후 개가입 방지를 위한 중복방지 체크(auid 중복체크)
                    if (MemberUtil::isReJoinByDupeinfo($userInfo['id']) == false) {
                        $js = "
                                if (window.opener === null || window.opener === undefined) {
                                    alert('현재 가입하실 수 없는 상태입니다(탈퇴 후 재가입 불가). 고객센터로 문의주시기 바랍니다.');
                                    location.href='/';
                                } else {
                                    alert('현재 가입하실 수 없는 상태입니다(탈퇴 후 재가입 불가). 고객센터로 문의주시기 바랍니다.');
                                    self.close();
                                }
                            ";
                        $this->js($js);
                    }

                    $memberVO = null;

                    try {
                        /** @var  \Bundle\Component\Member\Member $member */
                        $member = \App::load('\\Component\\Member\\Member');

                        \DB::begin_tran();

                        $session->set('isFront', 'y');
                        $session->set('simpleJoin', 'y');   // 카카오 회원가입시 본인인증을 스킵하기 위한 세션 셜정
                        $request->post()->set('appFl', 'y'); // 카카오 회원가입시 회원가입 승인처리를 위한 설정
                        $request->post()->set('useFl', 'y');

                        if ($session->has('pushJoin')) {
                            $request->post()->set('simpleJoinFl','push');
                        }

                        // 임시 아이디 및 임시 비밀번호 생성
                        $tmpDate = date("ymdHis");
                        $tmpId = 'KA'. $tmpDate . $this->GenerateString(3);

                        $request->post()->set('memId', $tmpId);
                        $request->post()->set('memPw','tempPassword@@');

                        // 닉네임 ,이름
                        $nickName = $userInfo['kakao_account']['profile']['nickname'];
                        $userName = $userInfo['kakao_account']['name'];

                        // 이름이 없을 경우 닉네임으로 대체함
                        if (!$userName) $userName = $nickName;

                        if (strlen($userName) > 0) {
                            $request->post()->set('memNm', $userName);
                        }

                        // email
                        $email = $userInfo['kakao_account']['email'];
                        if (strlen($email) > 0) {
                            $request->post()->set('email', $email);
                        }

                        // 핸드폰 번호
                        $tmpPhoneNumber = $userInfo['kakao_account']['phone_number'];
                        $phoneNumber = '';

                        if (strlen($tmpPhoneNumber) > 8) {
                            $arrPhoneNumber = explode( ' ', $tmpPhoneNumber );
                            $phoneNumber = '0'. $arrPhoneNumber[1];
                            $phoneNumber = str_replace('-', '', $phoneNumber);
                            $request->post()->set('cellPhone', $phoneNumber);
                        }

                        // 생년월일, 양력/음력
                        $birthYear = $userInfo['kakao_account']['birthyear'];
                        $birthDate = $userInfo['kakao_account']['birthday'];
                        $birthType = $userInfo['kakao_account']['birthday_type'];

                        if ( strlen($birthYear) == 4  && strlen($birthDate) == 4) {
                            $birthDt = $birthYear .'-'. substr_replace($birthDate,'-',2,0);
                            $request->post()->set('birthDt', $birthDt);
                        }
                        if (strlen($tmpPhoneNumber) > 0) {
                            if (strtoupper($birthType) == 'SOLAR') {
                                $birthType = 's';
                            } else {
                                $birthType = 'l';
                            }
                            $request->post()->set('calendarFl', $birthType);
                        }

                        // 성별
                        $gender = $userInfo['kakao_account']['gender'];
                        if (strlen($tmpPhoneNumber) > 0) {
                            if ($gender == 'female') {
                                $gender = 'w';
                            } else {
                                $gender = 'm';
                            }
                            $request->post()->set('sexFl', $gender);
                        }


                        /******************** 배송지 주소 시작  ************************/
                        $returnUrl = 'https://kapi.kakao.com/v1/user/shipping_address';

                        $isPost = false;

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $returnUrl);
                        curl_setopt($ch, CURLOPT_POST, $isPost);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                        $headers = ['Authorization: Bearer ${'. $properties["access_token"] . '}'];

                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                        $userResponse = curl_exec ($ch);
                        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close ($ch);

                        $userAddress = json_decode($userResponse);

                        $zonecode = '';
                        $address = '';
                        $addressSub = '';

                        // // 배송지가 있는 경우, 배송지가 여러개인 경우 Looping
                        if ($userAddress->has_shipping_addresses) {

                            foreach ($userAddress->shipping_addresses as $arrAddress) {
                                $zonecode = $arrAddress->zone_number;
                                // 우편번호(신)가 없을 경우 우편번호(구)로 사용
                                if (!$zonecode) {
                                    $zonecode = $arrAddress->zip_code;
                                }
                                $address = $arrAddress->base_address;
                                $addressSub = $arrAddress->detail_address;

                                if ($arrAddress->default) {
                                    break;
                                }
                            }

                            $request->post()->set('zonecode', $zonecode);
                            $request->post()->set('address', $address);
                            $request->post()->set('addressSub', $addressSub);

                        }

                        /******************** 배송지 주소 끝  ************************/


                        // 사용자가 선택한 약관 동의을 불러오기 위한 API 요청 URL
                        $returnUrl = 'https://kapi.kakao.com/v1/user/service/terms';

                        $isPost = false;

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $returnUrl);
                        curl_setopt($ch, CURLOPT_POST, $isPost);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                        $headers = ['Authorization: Bearer ${'. $properties["access_token"] . '}'];

                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                        $userResponse = curl_exec ($ch);
                        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close ($ch);

                        $allowedServiceTerms= json_decode($userResponse)->allowed_service_terms; //Access Token만 따로 뺌

                        $privateApprovalOption1 = "n";
						$privateOfferFl = 'n';
						$privateConsignFl = 'n';

                        foreach ($allowedServiceTerms as $key => $value) {
                            foreach ($value as $k => $val) {
                                if ($val == "privacy02") {
                                    $privateApprovalOption1 = "y";
                                }
                                // 마케팅 수신동의 (SMS, 이메일)
                                if ($val == "sms") {
                                    $request->post()->set('smsFl', 'y');
                                }
                                if ($val == "email") {
                                    $request->post()->set('maillingFl', 'y');
                                }

								if($val == 'smsAgree') {
									$request->post()->set('smsFl', 'y');
                                    $request->post()->set('maillingFl', 'y');
								}
								if($val == 'TermsOfService04') { // 개인정보 제 3자 제공 
									$privateOfferFl = 'y';
								}
								if($val == 'TermsOfService05') { // 개인정보 취급위탁 
									$privateConsignFl = 'y';
								}
                            }
                        }

                        // 개인정보 보호 약관
                        $arrPrivateApprovalOptionFl = array("7" => $privateApprovalOption1);
                        $arrPrivateOfferFl = array("4" => $privateOfferFl, "5" => $privateOfferFl);
                        $arrPrivateConsignFl = array("6" => $privateConsignFl);

                        $data['privateApprovalFl'] = 'y';
                        $data['privateApprovalOptionFl'] = $arrPrivateApprovalOptionFl;
                        $data['privateOfferFl'] = $arrPrivateOfferFl;
                        $data['privateConsignFl'] = $arrPrivateConsignFl;

                        // 각종 약관 처리를 위한 세션 생성
                        $session->set(Member::SESSION_JOIN_INFO, $data);

                        /**************************** 채널가입 여부 확인 ***********************************/
                        $returnUrl = 'https://kapi.kakao.com/v1/api/talk/channels';

                        $isPost = false;

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $returnUrl);
                        curl_setopt($ch, CURLOPT_POST, $isPost);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                        $headers = ['Authorization: Bearer ${'. $properties["access_token"] . '}'];

                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                        $userResponse = curl_exec ($ch);
                        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close ($ch);

                        // 채널 가입 쿠폰을 위한 채널 가입여부 체크
                        $channel_yn = 'n';
                        $channels = json_decode($userResponse)->channels;

                        // 채널 가입여부
                        foreach ($channels as $key => $value) {
                            foreach ($value as $k => $val) {
                                if ($k == "relation") {
                                    if ($val == 'ADDED') {
                                        $channel_yn = 'y';
                                    }
                                }
                            }
                        }
                        /**************************** 채널가입 여부 확인 끝 ***********************************/

                        $memberVO = $member->join($request->post()->xss()->all());

                        if ($memberVO != null) {
                            // 세션 초기화
                            $session->del('isFront');
                            $session->del('simpleJoin');

                            if($session->has(GodoKakaoServerApi::SESSION_USER_PROFILE)) {
                                $kakaoToken = $session->get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                                $kakaoProfile = $session->get(GodoKakaoServerApi::SESSION_USER_PROFILE);
                                $session->del(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                                $session->del(GodoKakaoServerApi::SESSION_USER_PROFILE);
                                $memberSnsService = new MemberSnsService();
                                $memberSnsService->joinBySns($memberVO->getMemNo(), $kakaoProfile['id'], $kakaoToken['access_token'], 'kakao');

                                // 회원중복방지 체크를 위해서 dupeinfo 값 update(auid);
                                $memberDAO = \App::load('\\Component\\Member\\MemberDAO');
                                $arrUpdateMember = array(
                                        'memNo' => $memberVO->getMemNo(),
                                        'dupeinfo' => $userInfo['id'],
                                );
                                $memberDAO->updateMemberKakaoAuid($arrUpdateMember);

                                // 카카오 채널가입 쿠폰 발행
                                $memberData = $member->getMember($memberVO->getMemNo(), 'memNo', 'memNo, memNm, mallSno, groupSno'); // 회원정보

                                if ($channel_yn == 'y') {
                                    $coupon = \App::load('\\Component\\Coupon\\Coupon');
                                    $coupon->setAutoCouponMemberSave('kakaochannel', $memberData['memNo'], $memberData['groupSno']);
                                }
                            }
                        }

                        \DB::commit();

                    } catch (\Exception $e) {
                        \DB::rollback();
                        // 세션 초기화
                        $session->del('isFront');
                        $session->del('simpleJoin');

                        if (get_class($e) == Exception::class) {
                            if ($e->getMessage()) {
                                $js = "
                                        if (window.opener === null || window.opener === undefined) {
                                            alert('".$e->getMessage()."');
                                            location.href='/';
                                        } else {
                                            alert('".$e->getMessage()."');
                                            self.close();
                                        }
                                    ";
                                $this->js($js);
                            }
                        } else {
                            throw $e;
                        }
                    }

                    if ($memberVO != null) {
                        $smsAutoConfig = ComponentUtils::getPolicy('sms.smsAuto');
                        $kakaoAutoConfig = ComponentUtils::getPolicy('kakaoAlrim.kakaoAuto');
                        $kakaoLunaAutoConfig = ComponentUtils::getPolicy('kakaoAlrimLuna.kakaoAuto');
                        if (gd_is_plus_shop(PLUSSHOP_CODE_KAKAOALRIMLUNA) === true && $kakaoLunaAutoConfig['useFlag'] == 'y' && $kakaoLunaAutoConfig['memberUseFlag'] == 'y') {
                            $smsDisapproval = $kakaoLunaAutoConfig['member']['JOIN']['smsDisapproval'];
                        }else if ($kakaoAutoConfig['useFlag'] == 'y' && $kakaoAutoConfig['memberUseFlag'] == 'y') {
                            $smsDisapproval = $kakaoAutoConfig['member']['JOIN']['smsDisapproval'];
                        } else {
                            $smsDisapproval = $smsAutoConfig['member']['JOIN']['smsDisapproval'];
                        }

                        StringUtils::strIsSet($smsDisapproval, 'n');
                        $sendSmsJoin = ($memberVO->getAppFl() == 'n' && $smsDisapproval == 'y') || $memberVO->getAppFl() == 'y';
                        $mailAutoConfig = ComponentUtils::getPolicy('mail.configAuto');
                        $mailDisapproval = $mailAutoConfig['join']['join']['mailDisapproval'];
                        StringUtils::strIsSet($smsDisapproval, 'n');
                        $sendMailJoin = ($memberVO->getAppFl() == 'n' && $mailDisapproval == 'y') || $memberVO->getAppFl() == 'y';

                        if ($sendSmsJoin) {
                            /** @var \Bundle\Component\Sms\SmsAuto $smsAuto */
                            $smsAuto = \App::load('\\Component\\Sms\\SmsAuto');
                            $smsAuto->notify();
                        }
                        if ($sendMailJoin) {
                            $member->sendEmailByJoin($memberVO);
                        }

                        if ($session->has('pushJoin')) {
                            $memNo = $memberVO->getMemNo();
                            $memberData = $member->getMember($memNo, 'memNo', 'memNo, memId, appFl, groupSno, mileage');
                            $coupon = new Coupon();
                            $getData = $coupon->getMemberSimpleJoinCouponList($memNo);
                            $member->setSimpleJoinLog($memNo, $memberData, $getData, 'push');
                            $session->del('pushJoin');
                        }
                    }

                    // 회원가입 후 로그인 처리
                    $callbackUri = $request->getRequestUri();
                    $redirectUri = $request->getDomainUrl() . $callbackUri;

                    $state = array();

                    $state['kakaoType'] = 'login';
                    $state['kakaosync'] = 'y';
                    $state['kakao_id'] = $tmpId;

                    // Token Error 문제 해결 (return url 없을 경우)
                    if (!$returnURLFromAuth) {
                        $returnURLFromAuth = '/';
                    }

                    $state['returnUrl'] = $returnURLFromAuth;

                    if ($startLen = strpos($request->getRequestUri(), "?")) {
                        $state['referer'] = $request->getReferer();
                        if ($request->get()->get('saveAutoLogin') == 'y') $state['saveAutoLogin'] = 'y';
                        $callbackUri = substr($request->getRequestUri(), 0, $startLen);
                    }
                    $redirectUri = $request->getDomainUrl() . $callbackUri;
                    \Logger::channel('kakaoLogin')->info('Redirect URI is %s', $redirectUri);

                    $getCodeURL = $kakaoApi->getCodeURL($redirectUri, $state);
                    \Logger::channel('kakaoLogin')->info('Code URI is %s', $getCodeURL);
					
                    $this->redirect($getCodeURL);

                }
                /********************* 카카오싱크 간편 회원가입을 위한 간편 회원가입 프로세스 끝 *********************/


                //마이페이지 회원 인증 다를경우
                // 로그인시 회원이 아닐경우 회원가입으로 유도(2020.02.20)
                if($kakaoType == 'my_page_password'){

                    //현재 받은 세션값으로 로그아웃 시키기
                    \Logger::channel('kakaoLogin')->info('different inform', $session->get(GodoKakaoServerApi::SESSION_USER_PROFILE));
                    $js = "
                                            alert('" . __('로그인 시 인증한 정보와 다릅니다 .') . "');
                                            if (window.opener === null || window.opener === undefined || Object.keys(window.opener).indexOf('" . $functionName . "') < 0) {
                                                location.href='../../mypage/my_page_password.php';
                                            } else {
                                                opener.location.href='../../mypage/my_page_password.php';
                                                self.close();
                                            }
                                        ";
                    $this->js($js);
                }

                // 비회원시 제품상세페이지에서 구매하기 -> 로그인 -> 회원가입페이지 이동시 returnUrl encoding 필수
                $returnURLFromAuth = urlencode($returnURLFromAuth);

                $js = "
                    if (typeof(window.top.layerSearchArea) == 'object') {
                            if (confirm('" . __('가입되지 않은 회원정보입니다. 회원가입을 진행하시겠습니까?') . "')) {
                                location.href = '../kakao/kakao_login.php?kakaoType=join_method&returnUrl=".$returnURLFromAuth."';
                            } else {
                                parent.location.reload();
                            }
                        } else if (window.opener === null || window.opener === undefined) {
                            if (confirm('" . __('가입되지 않은 회원정보입니다. 회원가입을 진행하시겠습니까?') . "')) {
                                location.href = '../kakao/kakao_login.php?kakaoType=join_method&returnUrl=".$returnURLFromAuth."';
                            } else {
                                location.href='/';
                            }
                        } else {
                           if (confirm('" . __('가입되지 않은 회원정보입니다. 회원가입을 진행하시겠습니까?') . "')) {
                                location.href = '../kakao/kakao_login.php?kakaoType=join_method&returnUrl=".$returnURLFromAuth."';
                           }else {
                                //opener.location.href = '';
                                self.close();
                           }
                        }
                        ;";

                $this->js($js);

            }

            if ($kakaoType != 'join_method') {
                // 카카오 로그인 팝업을 띄우는 케이스
                $callbackUri = $request->getRequestUri();
                $state = array();
                if ($startLen = strpos($request->getRequestUri(), "?")) {
                    $requestUriArray = explode('&', substr($request->getRequestUri(), ($startLen + 1)));

                    $kakaoTypeInRequestUri = $requestUriArray[0];
                    $kakaoTypeToState= explode('=', $kakaoTypeInRequestUri);
                    $state['kakaoType'] = $kakaoTypeToState[1];
                    //returnUrl이 여러 개 있을 경우
                    foreach ($requestUriArray as $key => $val) {
                        $isReturnUrl = strstr($val, 'returnUrl');
                        if ($isReturnUrl) {
                            // returnl 분리시
                            // $returnUrlToState = explode('=', $val);
                            $returnUrlToState = explode('=', $val, 2);
                            $state['returnUrl'] = $returnUrlToState[1];
                        }

                        // 가입코드
                        $isRegiPath = strstr($val, 'entry_path');
                        if ($isRegiPath) {
                            $regiPathToState = explode('=', $val);
                            $state['regiPath'] = $regiPathToState[1];
                        }
                    }

                    $state['referer'] = $request->getReferer();
                    if ($request->get()->get('saveAutoLogin') == 'y') $state['saveAutoLogin'] = 'y';
                    $callbackUri = substr($request->getRequestUri(), 0, $startLen);
                }

                $redirectUri = $request->getDomainUrl() . $callbackUri;
                \Logger::channel('kakaoLogin')->info('Redirect URI is %s', $redirectUri);

                $getCodeURL = $kakaoApi->getCodeURL($redirectUri, $state);
                \Logger::channel('kakaoLogin')->info('Code URI is %s', $getCodeURL);

                // 카카오 자동로그인 작업 - 2020.08.08 추가
                // if ($state['kakaoType'] == 'auto_login') {
                //     $getCodeURL = $getCodeURL.'&auto_login=true';
                // }

                $this->redirect($getCodeURL);
            }

        } catch (AlertRedirectException $e) {
            $logger->error($e->getTraceAsString());
            MemberUtil::logout();
            throw $e;
        } catch (AlertRedirectCloseException $e) {
            $logger->error($e->getTraceAsString());
            throw $e;
        } catch (Exception $e) {
            $logger->error($e->getTraceAsString());
            if ($request->isMobile()) {
                MemberUtil::logout();
                throw new AlertRedirectException($e->getMessage(), $e->getCode(), $e, '../../member/login.php', 'parent');
            } else {
                MemberUtil::logout();
                throw new AlertCloseException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }


    /* 임시 아이디 생성을 위한 랜덤 문자열생성 */
    public  function GenerateString($length)
    {
        $characters  = "0123456789";
        $characters .= "abcdefghijklmnopqrstuvwxyz";
        $characters .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

        $string_generated = "";

        $nmr_loops = $length;
        while ($nmr_loops--)
        {
            $string_generated .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $string_generated;
    }

    // IP 정보 가져오기
    public function get_client_ip() {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }


}
