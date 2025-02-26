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
namespace Controller\Mobile\Member;

use Bundle\Component\Apple\AppleLogin;
use Bundle\Component\Godo\GodoNaverServerApi;
use Component\Facebook\Facebook;
use Component\Godo\GodoPaycoServerApi;
use Component\Member\MemberValidation;
use Component\Member\Util\MemberUtil;
use Component\SiteLink\SiteLink;
use Framework\Utility\ArrayUtils;
use Framework\Utility\SkinUtils;
use App;
use Component\Godo\GodoWonderServerApi;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Bundle\Component\Policy\KakaoLoginPolicy;
use Component\Agreement\BuyerInform;
use Component\Agreement\BuyerInformCode;
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
 * @package Bundle\Controller\Mobile\Member
 * @author  yjwee
 */
class JoinController extends \Bundle\Controller\Mobile\Member\JoinController
{
    public function pre()
    {
        Request::post()->set('token',Token::generate('token'));
        Request::post()->set('agreementInfoFl','y');
        Request::post()->set('privateApprovalFl','y');
    }

    public function post(){

        $snsMemberAuthFl='y';
        $chkSNSMemberAuthFl = \Component\Member\MemberValidation::checkSNSMemberAuth();       
        
        //추천인 아이디 쿠키값 
        if(Cookie::has($cookieNm)){
            $this->setData('wrId', Cookie::get("wowbioRecommend"));        
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
/*
        print_r("<pre>"); 
        print_r(Session::get(GodoNaverServerApi::SESSION_USER_PROFILE));
        print_r("</pre>");*/

    }
}
