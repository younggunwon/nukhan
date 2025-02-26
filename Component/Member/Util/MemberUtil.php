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
namespace Component\Member\Util;

use App;
use Component\Apple\AppleLogin;
use Component\Payment\Payco\Payco;
use Component\Policy\KakaoLoginPolicy;
use Component\Policy\PaycoLoginPolicy;
use Component\Policy\SnsLoginPolicy;
use Component\Policy\NaverLoginPolicy;
use Component\Policy\WonderLoginPolicy;
use Component\Database\DBTableField;
use Component\Facebook\Facebook;
use Component\Godo\GodoPaycoServerApi;
use Component\Godo\GodoNaverServerApi;
use Component\Godo\GodoKakaoServerApi;
use Component\Godo\GodoWonderServerApi;
use Component\Member\Manager;
use Component\Member\Member;
use Component\Member\MemberVO;
use Component\Member\MyPage;
use Component\Validator\Validator;
use Component\Mall\Mall;
use Cookie;
use Encryptor;
use Exception;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Object\SimpleStorage;
use Framework\Object\SingletonTrait;
use Framework\Utility\ArrayUtils;
use Framework\Utility\ComponentUtils;
use Framework\Utility\SkinUtils;
use Framework\Utility\StringUtils;
use Framework\Utility\DateTimeUtils;
use Logger;
use Message;
use Request;
use Session;

/**
 * Class MemberUtil
 * @package Bundle\Component\Member\Util
 * @author  yjwee
 * @method static MemberUtil getInstance
 */

class MemberUtil extends \Bundle\Component\Member\Util\MemberUtil
{
    // 추가내용 (2020.03.25)
    /**
     * 휴대폰 중복 확인. 이미 해당 휴대폰을 사용 중인 아이디일 경우 중복되지 않은 것으로 판단한다.
     *
     * @static
     *
     * @param string $memId
     * @param string $cellPhone
     *
     * @return bool true 중복된 휴대폰, false 중복되지 않거나 해당 아이디가 사용 중인 휴대폰
     * @throws Exception
     */
    public static function overlapCellPhone($memId, $cellPhone)
    {
        if (Validator::required($memId) === false) {
            throw new Exception(sprintf(__('%s은(는) 필수 항목 입니다.'), __('아이디')));
        }

        $fields = DBTableField::getFieldTypes('tableMember');
        $strSQL = 'SELECT memId FROM ' . DB_MEMBER . ' where memId != ? and cellPhone = ?';
        $arrBind = [];
        $db = App::load('DB');
        $db->bind_param_push($arrBind, $fields['memId'], $memId);
        $db->bind_param_push($arrBind, $fields['cellPhone'], $cellPhone);

        return MemberUtil::isGreaterThanNumRows($strSQL, $arrBind, 0);
    }

}
