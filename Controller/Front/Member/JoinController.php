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
namespace Controller\Front\Member;

use Framework\Utility\ArrayUtils;
use Bundle\Component\Apple\AppleLogin;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Bundle\Component\Policy\KakaoLoginPolicy;
use Component\Agreement\BuyerInform;
use Component\Agreement\BuyerInformCode;
use Component\Godo\GodoPaycoServerApi;
use Component\Godo\GodoNaverServerApi;
use Component\Godo\GodoWonderServerApi;
use Component\Mall\Mall;
use Component\Policy\PaycoLoginPolicy;
use Component\Policy\NaverLoginPolicy;
use Component\Policy\WonderLoginPolicy;
use Component\Policy\SnsLoginPolicy;
use Framework\Security\Token;
use Request;
use Session;
use Cookie;
use Framework\Utility\StringUtils;

/**
 * Class 회원가입 정보입력
 * @package Bundle\Controller\Front\Member
 * @author  yjwee
 */
class JoinController extends \Bundle\Controller\Front\Member\JoinController
{
    public function pre(){        
        Request::post()->set('token',Token::generate('token'));

        Request::post()->set('agreementInfoFl','y');
        Request::post()->set('privateApprovalFl','y');
        //추천인 아이디 쿠키값 
        if(Cookie::has($cookieNm)){
            $this->setData('wrId', Cookie::get("wowbioRecommend"));        
            
        }
        $snsMemberAuthFl='y';
        $chkSNSMemberAuthFl = \Component\Member\MemberValidation::checkSNSMemberAuth();

        
        if(!Session::has(GodoPaycoServerApi::SESSION_USER_PROFILE)){
            if (Session::has(GodoPaycoServerApi::SESSION_ACCESS_TOKEN)) {
                $paycoToken = Session::get(GodoPaycoServerApi::SESSION_ACCESS_TOKEN);
                $paycoApi = new GodoPaycoServerApi();
                $userProfile = $paycoApi->getUserProfile($paycoToken['access_token']);
                Session::set(GodoPaycoServerApi::SESSION_USER_PROFILE, $userProfile);
                $snsMemberAuthFl = $chkSNSMemberAuthFl;
            }
        }

        $naverLoginPolicy = gd_policy('member.naverLogin');
        if(!Session::has(GodoNaverServerApi::SESSION_USER_PROFILE)){
            if (Session::has(GodoNaverServerApi::SESSION_ACCESS_TOKEN) && $naverLoginPolicy['useFl'] === 'y') {
                $naverToken = Session::get(GodoNaverServerApi::SESSION_ACCESS_TOKEN);
                $naverApi = new GodoNaverServerApi();
                $userProfile = $naverApi->getUserProfile($naverToken);
                Session::set(GodoNaverServerApi::SESSION_USER_PROFILE, $userProfile);
                $snsMemberAuthFl = $chkSNSMemberAuthFl;
            }       
        }
        if(!Session::has(GodoKakaoServerApi::SESSION_USER_PROFILE)){
            $kakaoLoginPolicy = gd_policy('member.kakaoLogin');
            if (empty(Session::has(GodoKakaoServerApi::SESSION_ACCESS_TOKEN)) === false && $kakaoLoginPolicy['useFl'] === 'y') {
                $kakaoToken = Session::get(GodoKakaoServerApi::SESSION_ACCESS_TOKEN);
                $kakaoApi = new GodoKakaoServerApi();
                $kakaoApi->appLink($kakaoToken['access_token']);
                $userInfo = $kakaoApi->getUserInfo($kakaoToken['access_token']);
                Session::set(GodoKakaoServerApi::SESSION_USER_PROFILE, $userInfo);
                $snsMemberAuthFl = $chkSNSMemberAuthFl;
            }
        }
        if(!Session::has(GodoWonderServerApi::SESSION_USER_PROFILE)){
            $wonderLoginPolicy = gd_policy('member.wonderLogin');
            if (empty(Session::has(GodoWonderServerApi::SESSION_ACCESS_TOKEN)) === false && $wonderLoginPolicy['useFl'] === 'y' && Request::get()->get('joinType') === 'wonder') {
                $snsMemberAuthFl = $chkSNSMemberAuthFl;
                $siteLink = new \Component\SiteLink\SiteLink();
                $this->setData('joinActionUrl', $siteLink->link('../member/member_ps.php', 'ssl'));
                $wonderToken = Session::get(GodoWonderServerApi::SESSION_ACCESS_TOKEN);
                $wonderApi = new GodoWonderServerApi();
                $userProfile = $wonderApi->getUserProfile($wonderToken);
                $userProfile['name'] = str_replace(' ', '', $userProfile['name']);
                $userProfile['sexFl'] = $userProfile['gender'] == '2' ? 'w' : 'm';
                Session::set(GodoWonderServerApi::SESSION_USER_PROFILE, $userProfile);
                $this->setData('terms', $wonderApi->getTerms());
                $this->setData('memberInfo', $userProfile);
                $this->setData('emailAsterisk', StringUtils::mask($userProfile['email'], 3, strpos($userProfile['email'], '@') -3));
                $this->getView()->setPageName('member/join_agreement_sns');
            }
        }

        $authCellPhoneConfig = gd_get_auth_cellphone_info();
         //SNS 회원 가입을 진행중이고
        //본인 인증을 노출하지 않을 경우 아이핀/휴대폰 본인인증의 상태값을 미사용(n)으로 변경함.       
        
        if ($snsMemberAuthFl === 'n') {
            $ipinConfig['useFl']          = 'n';
            $authCellPhoneConfig['useFl'] = 'n';
        }       

        $this->setData('authDataCpCode', $authCellPhoneConfig['cpCode']);
        $this->setData('domainUrl', Request::getDomainUrl());
        $this->setData('authCellPhoneConfig', $authCellPhoneConfig);        
        
        $session = \App::getInstance('session');  

        /*print_r("<pre>"); 
        print_r(Session::get(GodoNaverServerApi::SESSION_USER_PROFILE));
        print_r("</pre>");*/


        $inform = new BuyerInform();
        $mall = new Mall();

        $serviceInfo = $mall->getServiceInfo();
        $agreementInfo = $inform->getAgreementWithReplaceCode(BuyerInformCode::AGREEMENT);
        $privateApproval = $inform->getInformData(BuyerInformCode::PRIVATE_APPROVAL);
        $privateApprovalOption = $inform->getInformDataArray(BuyerInformCode::PRIVATE_APPROVAL_OPTION);
        $privateConsign = $inform->getInformDataArray(BuyerInformCode::PRIVATE_CONSIGN);
        $privateOffer = $inform->getInformDataArray(BuyerInformCode::PRIVATE_OFFER);

        $this->setData('serviceInfo', $serviceInfo);
        $this->setData('agreementInfo', $agreementInfo);
        $this->setData('privateApproval', $privateApproval);
        $this->setData('privateApprovalOption', $privateApprovalOption);
        $this->setData('privateConsign', $privateConsign);
        $this->setData('privateOffer', $privateOffer);
    }   
	public function index() {
		
		parent::index();

		# 2023-08-29 루딕스-brown 추천인초대를 받아서 들어왔을때
		$member = \App::load('\\Component\\Member\\Member');
		if(Session::get('recommendMemNo')) {
			$rcm = Session::get('recommendMemNo');
			$memData =$member->getMember($rcm, 'memNo');
			$data = $this->getData('data');
			$data['recommId'] = $memData['memId'];
			$this->setData('data', $data);
		}
	
	
	}
}


