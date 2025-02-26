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

use Component\Facebook\Facebook;
use Component\Member\Member;
use Component\Member\Util\MemberUtil;
use Framework\Debug\Exception\AlertRedirectException;
use Session;
use Cookie;
use Component\Wowbio\Recommend;

/**
 * Class 회원가입완료
 * @package Bundle\Controller\Front\Member
 * @author  yjwee
 */
class JoinOkController extends \Bundle\Controller\Front\Member\JoinOkController
{
    public function post()
    {

        $session = \App::getInstance('session');
        $request = \App::getInstance('request');

        $memberNo = $session->get(Member::SESSION_NEW_MEMBER, $request->request()->get('memNo', ''));
        if(Cookie::has('wowbioRecommend')){
            $recommend = new Recommend();
            $recommend->update(Cookie::get('wowbioRecommend'),1,$memberNo);
        }
    }
}
