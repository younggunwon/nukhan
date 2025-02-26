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

use Bundle\Component\Apple\AppleLogin;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Component\Facebook\Facebook;
use Component\Godo\GodoPaycoServerApi;
use Component\Godo\GodoNaverServerApi;
use Component\Godo\GodoWonderServerApi;
use Component\Member\MemberSnsService;
use Component\Member\MemberValidation;
use Component\Member\Util\MemberUtil;
use Component\Coupon\Coupon;
use Component\Policy\SnsLoginPolicy;
use Component\SiteLink\SiteLink;
use Component\Storage\Storage;
use Framework\Debug\Exception\DatabaseException;
use Framework\Object\SimpleStorage;
use Exception;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\PlusShop\PlusShopWrapper;
use Framework\Utility\ComponentUtils;
use Framework\Utility\StringUtils;
use Validation;
use Framework\Debug\Exception\AlertReloadException;

/**
 * Class 프론트 회원 요청 처리 컨트롤러
 * @package Bundle\Controller\Front\Member
 * @author  yjwee
 */
class MemberPsController extends \Bundle\Controller\Front\Member\MemberPsController
{
    public function index()
    {
		$session = \App::getInstance('session');
        $request = \App::getInstance('request');
        $logger = \App::getInstance('logger');
		$member = \App::load('\\Component\\Member\\Member');
        try {
            $mode = $request->post()->get('mode', $request->get()->get('mode'));

            switch ($mode) {
				case 'recomRegister':
                    $result = $member->recomRegister($request->post()->all()['wgRecommId']);
					if($result) {
						throw new AlertReloadException(__('추천인 등록이 완료되었습니다.'), null, null, 'parent');
                    }else {
						throw new AlertReloadException(__('오류가 발생했습니다.'), null, null, 'parent');
					}
					break;
				case 'delWgJoinFl':
					// 2023-12-06 wg-eric 쿠키삭제
					\Cookie::del('wgJoinFl');
					\Cookie::del('wgGoogleJoinFl');
					\Cookie::del('wgEnterFromFacebookFl');

					break;

                default:
					parent::index();
				break;
            }
        } catch (AlertReloadException $e) {
            throw $e;
        }
	}
}