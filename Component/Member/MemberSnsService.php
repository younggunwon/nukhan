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

namespace Component\Member;


use Component\Facebook\Facebook;
use Component\Godo\GodoPaycoServerApi;
use Component\Member\Util\MemberUtil;
use Component\Validator\Validator;
use Exception;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Debug\Exception\AlertRedirectCloseException;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\StringUtils;
use Request;
use Session;
use App;
/**
 * Class MemberSnsService
 * @package Bundle\Component\Member
 * @author  yjwee
 */
class MemberSnsService extends \Bundle\Component\Member\MemberSnsService
{

    /**
     * sns 회원가입 정보를 통한 회원 로그인 처리
     *
     * @param $uuid
     *
     * @throws AlertRedirectException
     * @throws Exception
     */
    public function loginBySns($uuid)
    {
		parent::loginBySns($uuid);
		
		#2024-10-04 wg-brown subCart 업데이트
		$memNo = Session::get("member.memNo");
		$cartSub = App::load(\Component\Subscription\CartSub::class);
		$cartSub->setMergeGuestCart($memNo);
		
		//$memberService = new Member();
		//$memberService->refreshSubBasket($memNo);
    }

}
