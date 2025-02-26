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
namespace Controller\Front\Mypage;

use Bundle\Component\Apple\AppleLogin;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Bundle\Component\Policy\AppleLoginPolicy;
use Bundle\Component\Policy\KakaoLoginPolicy;
use Bundle\Component\Godo\GodoWonderServerApi;
use Component\Agreement\BuyerInform;
use Component\Agreement\BuyerInformCode;
use Component\Facebook\Facebook;
use Component\Member\Member;
use Component\Member\MyPage;
use Component\Member\Util\MemberUtil;
use Component\Policy\PaycoLoginPolicy;
use Component\Policy\NaverLoginPolicy;
use Component\Policy\WonderLoginPolicy;
use Component\Policy\SnsLoginPolicy;
use Component\SiteLink\SiteLink;
use Exception;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Utility\ArrayUtils;
use Framework\Utility\DateTimeUtils;
use Request;
use Session;


/**
 * Class MyPageController
 * @package Bundle\Controller\Front\Mypage
 * @author  yjwee
 */
class MyPageController extends \Bundle\Controller\Front\Mypage\MyPageController
{
    public function index()
    {
		parent::index();
		//2023-11-07 루딕스-borwn 실서버 이후에 가입한 회원들중에 추천인 없을때 추천인 등록가능하게
        $session = \App::getInstance('session');
		//실서버 적용날짜 
		$applyDate = '2024-01-10';
		if($session->get('member.mRegDt') > $applyDate) {
			$showRecomFl = 'y';
		}
		$this->setData('showRecomFl', $showRecomFl);
    }
}
