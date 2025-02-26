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
 * @link http://www.godo.co.kr
 */

namespace Controller\Mobile\Member\Authcellphone;

use Component\Member\Member;
use Component\Member\Util\MemberUtil;
use Exception;
use Framework\Application\Bootstrap\Log;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\AlertCloseException;
use Framework\Debug\Exception\AlertRedirectCloseException;
use Framework\Utility\NumberUtils;
use Framework\Security\Token;
use Framework\Utility\DateTimeUtils;
use Framework\Security\HpAuthCryptor;
use Component\Mobile\HpAuthSecurity;
use Bundle\Component\Apple\AppleLogin;
use Component\Facebook\Facebook;
use Component\Godo\GodoPaycoServerApi;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Bundle\Component\Godo\GodoNaverServerApi;
use Component\Godo\GodoWonderServerApi;
use Session;
/**
 * Class DreamsecurityResultController 드림시큐리티 휴대폰 본인확인 모듈 사용자 인증 정보 결과 페이지
 * @package Controller\Mobile\Member\Ipin
 * @author  yjwee
 */
class DreamsecurityResultNewController extends \Bundle\Controller\Mobile\Member\Authcellphone\DreamsecurityResultController
{
    /**
     * index
     *
     */
    public function index()
    {
        parent::index();
    }
}
