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

use App;
use Component\Godo\GodoPaycoServerApi;
use Component\Member\Util\MemberUtil;
use Component\Validator\Validator;
use Exception;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Security\Token;
use Framework\Utility\ComponentUtils;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\StringUtils;
use Globals;
use Logger;
use Message;
use Session;

/**
 * Class 회원 검증 클래스
 * @package Component\Member
 * @author  yjwee
 */
class MemberValidation extends \Bundle\Component\Member\MemberValidation
{

    /**
     * 회원 등록/가입 검증 함수
     *
     * @param \Component\Member\MemberVo $vo
     *
     * @param null                       $require
     * @param bool                       $passValidation
     *
     * @return mixed
     * @throws Exception
     */
    public static function validateMemberByInsert(\Component\Member\MemberVo $vo, $require = null, $passValidation = false)
    {
        $logger = App::getInstance('logger');
        $session = App::getInstance('session');
        $logger->info('Start membership verification.');
        if (is_null($require)) {
            $require = MemberUtil::getRequireField();
        }

        $joinPolicy = ComponentUtils::getPolicy('member.join');
        StringUtils::strIsSet($joinPolicy['appUseFl'], 'n');
        StringUtils::strIsSet($joinPolicy['under14Fl'], 'n');
        StringUtils::strIsSet($joinPolicy['limitAge'], 19);
        $joinItemPolicy = ComponentUtils::getPolicy('member.joinitem');
        StringUtils::strIsSet($joinItemPolicy['passwordCombineFl'], '');
        StringUtils::strIsSet($joinItemPolicy['busiNo']['charlen'], 10); // 사업자번호 길이

        $isBusiness = $vo->getMemberFl() === 'business';    // 사업자 가입 체크
        $isApplyWait = $joinPolicy['appUseFl'] == 'y';    // 승인 여부 체크 n 일 경우 바로 가입
        $isCompanyWait = $isBusiness && $joinPolicy['appUseFl'] == 'company';
        $isRejectAge = $joinPolicy['under14Fl'] !== 'n';  // 가입 연령제한 n 일 경우 제한하지 않음
        $isIpinUse = ComponentUtils::useIpin();
        $isAuthCellPhone = ComponentUtils::useAuthCellphone();
        $logger->info(sprintf('Check whether you are approved for membership. memberFl[%s], appUseFl[%s], under14Fl[%s], useIpin[%s], useAuthCellphone[%s]', $vo->getMemberFl(), $joinPolicy['appUseFl'], $joinPolicy['under14Fl'], $isIpinUse, $isAuthCellPhone));

        if ((gd_is_admin() && $session->get('isFront') != 'y') || $session->get('simpleJoin') == 'y') {
            $vo->setAppFl($vo->getAppFl());
        } else {
            $vo->setAppFl('y');
            if ($isApplyWait || $isCompanyWait) {
                $logger->info('Wait for business member approval');
                $vo->setAppFl('n');
            }
            if ($isRejectAge && !($isIpinUse || $isAuthCellPhone)) {
                if (!Validator::required($vo->getBirthDt())) {
                    $logger->info('There is an age limit at the time of enrollment. Please enter your date of birth.');
                    throw new Exception(__('가입 시 연령제한이 있습니다. 생년월일을 입력해주세요.'));
                }

                $birthDt = $vo->getBirthDt(true)->format('Ymd');
                if($vo->getCalendarFl() == 'l') {
                    $birthDt = ComponentUtils::getSolarDate($vo->getBirthDt(true)->format('Y-m-d'));
                    $birthDt = str_replace('-', '', $birthDt);
                }
                $age = DateTimeUtils::age($birthDt, 1);
                $yearAge = DateTimeUtils::age($birthDt, ''); // 연 나이(현재연도 - 출생연도) 가져오기

                if ($joinPolicy['limitAge'] > $age && $yearAge != 19) {
                    if ($joinPolicy['under14Fl'] === 'no') {
                        $logger->info('It is an age that can not be registered.');
                        throw new Exception(__('회원가입이 불가능한 연령입니다.'));
                    }
                    $vo->setAppFl('n');
                }
            }
        }

        if ($passValidation == false) {
            \Component\Member\MemberValidation::validateMemberPassword($vo->getMemPw());
        }

        $length = MemberUtil::getMinMax();

        $v = new Validator();
        $v->init();
        $v->add('memId', 'userid', true, '{' . __('아이디') . '}'); // 아이디
        $v->add('memId', 'minlen', true, '{' . __('아이디 최소길이') . '}', $length['memId']['minlen']); // 아이디 최소길이
        $v->add('memId', 'maxlen', true, '{' . __('아이디 최대길이') . '}', $length['memId']['maxlen']); // 아이디 최대길이
        $v->add('appFl', 'yn', false, '{' . __('가입승인') . '}'); // 가입승인
        $v->add('entryBenefitOfferFl', 'yn', false, '{' . __('가입혜택지급') . '}'); // 가입혜택지급
        $v->add('entryDt', '', false, '{' . __('회원가입일') . '}'); // 회원가입일
        if ($vo->getmarriFl() == 'y') {
            $v->add('marriDate', 'date', $require['marriDate'], '{' . __('결혼기념일') . '}'); // 결혼기념일
        }
        \Component\Member\MemberValidation::addValidateMember($v, $require);
        \Component\Member\MemberValidation::addValidateMemberExtra($v, $require);
        if ($isBusiness) {
            \Component\Member\MemberValidation::addValidateMemberBusiness($v, $require);
        }
        if ($joinItemPolicy['pronounceName']['use'] == 'y') {
            $v->add('pronounceName', '', $joinItemPolicy['pronounceName']['require'], '{' . __('이름(발음)') . '}');
        }

        if ($v->act($vo->toArray(), true) === false && $session->get('simpleJoin') != 'y') {
            $logger->info(__METHOD__ . ', has session_user_profile=>' . $session->has(GodoPaycoServerApi::SESSION_USER_PROFILE), $v->errors);
            throw new Exception(implode("\n", $v->errors), 500);
        }

        // 거부 아이디 필터링
        if (StringUtils::findInDivision(strtoupper($vo->getMemId()), strtoupper($joinPolicy['unableid']))) {
            throw new Exception(sprintf(__('%s는 사용이 제한된 아이디입니다'), $vo->getMemId()));
        }

        // 아이디 중복여부 체크
        if (MemberUtil::overlapMemId($vo->getMemId())) {
            throw new Exception(sprintf(__('%s는 이미 등록된 아이디입니다'), $vo->getMemId()));
        }

        // 닉네임 중복여부 체크
        if ($vo->isset($vo->getNickNm())) {
            if (MemberUtil::overlapNickNm($vo->getMemId(), $vo->getNickNm())) {
                throw new Exception(sprintf(__('%s는 이미 사용중인 닉네임입니다'), $vo->getNickNm()));
            }
        }

        // 수정내용 (2020.03.20)
        // 이메일 중복여부 체크
        if ($vo->isset($vo->getEmail())) {
            if (MemberUtil::overlapEmail($vo->getMemId(), $vo->getEmail())) {
                // 카카오 회원가입시
                $kakaoLoginPolicy = gd_policy('member.kakaoLogin');
                if ((\Session::has(\Component\Godo\GodoKakaoServerApi::SESSION_USER_PROFILE) && $kakaoLoginPolicy['useFl'] === 'y')) {
                    throw new Exception(sprintf(__('%s는 이미 사용중인 이메일입니다. 해당 계정으로 로그인 후 회원정보 수정페이지에서 카카오 아이디로 로그인해주세요.'), $vo->getEmail()));
                } else {
                    throw new Exception(sprintf(__('%s는 이미 사용중인 이메일입니다.'), $vo->getEmail()));
                }
            }
        }

        // 추가내용 (2020.03.20)
        // 휴대폰 중복여부 체크
        if ($vo->isset($vo->getCellPhone())) {
            if (MemberUtil::overlapCellPhone($vo->getMemId(), $vo->getCellPhone())) {
                $kakaoLoginPolicy = gd_policy('member.kakaoLogin');
                if ((\Session::has(\Component\Godo\GodoKakaoServerApi::SESSION_USER_PROFILE) && $kakaoLoginPolicy['useFl'] === 'y')) {
                    throw new Exception(sprintf(__('%s는 이미 사용중인 휴대폰입니다. 해당 계정으로 로그인 후 회원정보 수정페이지에서 카카오 아이디로 로그인해주세요.'), $vo->getCellPhone()));
                } else {
                    throw new Exception(sprintf(__('%s는 이미 사용중인 휴대폰입니다.'), $vo->getCellPhone()));
                }
            }
        }

        // 추천아이디 실존인물인지 체크
        if ($vo->isset($vo->getRecommId())) {
            if (MemberUtil::checkRecommendId($vo->getRecommId(), $vo->getMemId()) === false) {
                throw new Exception(sprintf(__('등록된 회원 아이디가 아닙니다. 추천하실 아이디를 다시 확인해주세요.'), $vo->getRecommId()));
            }
        }

        // 사업자번호 중복여부 체크
        if ($isBusiness && $vo->isset($vo->getBusiNo())) {
            if (strlen(gd_remove_special_char($vo->getBusiNo())) != $joinItemPolicy['busiNo']['charlen']) {
                throw new Exception(sprintf(__('사업자번호는 %s자로 입력해야 합니다.'), $joinItemPolicy['busiNo']['charlen']));
            }
            if ($joinItemPolicy['busiNo']['overlapBusiNoFl'] == 'y' && MemberUtil::overlapBusiNo($vo->getMemId(), $vo->getBusiNo())) {
                throw new Exception(sprintf(__('이미 등록된 사업자번호입니다.'), $vo->getBusiNo()));
            }
        }

        /** @var \Component\Member\HackOut\HackOutService $hackOutService */
        $hackOutService = App::load('\\Component\\Member\\HackOut\\HackOutService');
        $hackOutService->checkRejoinByMemberId($vo->getMemId());
    }

}
