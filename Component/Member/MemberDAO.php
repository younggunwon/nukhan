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

// use App;
// use Component\Board\Board;
use Component\Database\DBTableField;
// use Component\Mail\MailMimeAuto;
// use Framework\Database\DBTool;
// use Framework\Object\SingletonTrait;
// use Framework\Utility\DateTimeUtils;
use Framework\Utility\StringUtils;
use Exception;

/**
 * 회원 테이블 데이터 처리 클래스
 * @package Bundle\Component\Member
 * @author  yjwee
 * @method static MemberDAO getInstance
 */
class MemberDAO extends \Bundle\Component\Member\MemberDAO
{

    /**
     * updateMemberKakaoAuid
     * 카카오회원이 회원탈퇴 후 재가입방지를 위한 중복코드를 auid로 대체함으로써 auid 업데이트
     *
     * @param array $params
     */
    public function updateMemberKakaoAuid(array $params)
    {
        $include = [
            'dupeinfo',
        ];
        $bind = $this->db->get_binding(DBTableField::tableMember(), $params, 'update', $include);
        $this->db->bind_param_push($bind['bind'], $this->fields['memNo'], $params['memNo']);
        $this->db->set_update_db(DB_MEMBER, $bind['param'], 'memNo = ?', $bind['bind']);
    }


    /** 회원 리스트에서 SNS UUID 추가를 위한 함수(2020.03.25) **/
    /**
     * setQuerySearch
     *
     * @param $params
     * @param $arrBind
     */
    protected function setQuerySearch($params, &$arrBind)
    {
        $arrBind = $arrWhere = [];
        // 체크박스를 선택하여 조회하는 경우
        if ($params['chk'] && \is_array($params['chk'])) {
            $arrWhere[] = 'm.memNo IN (' . implode(',', $params['chk']) . ')';
        }
        // 검색제한(회원 일괄 관리)
        if (StringUtils::strIsSet($params['indicate']) === false) {
            $arrWhere[] = '0';
        }
        // 검색제한(회원등급평가(수동))
        if (StringUtils::strIsSet($params['groupValidDt']) === true) {
            $arrWhere[] = 'm.groupValidDt < now()';
        }
        // 메일이나 SMS 보내기에 따른 검색 설정
        if (isset($params['sendMode']) === true) {
            if ($params['sendMode'] === 'mail') {
                $arrWhere[] = '(m.email != \'\' AND m.email IS NOT NULL)';
            }
            if ($params['sendMode'] === 'sms') {
                $arrWhere[] = '(m.cellPhone != \'\' AND m.cellPhone IS NOT NULL)';
            }
        }
        //수기주문에서의 회원검색은 승인회원만 노출
        if ($params['loadPageType'] === 'order_write') {
            $arrWhere[] = "m.appFl = 'y'";
        }
        // 통합검색 처리
        if ((StringUtils::strIsSet($params['key'], null) !== null)
            && (StringUtils::strIsSet($params['keyword'], '') !== '')) {
            $hyphenKeys = 'phone,cellPhone,fax,busiNo';
            if ($params['key'] === 'all') {
                $tmpWhere = [];
                foreach (\Component\Member\Member::COMBINE_SEARCH as $mKey => $mVal) {
                    $type = $this->fields[$mKey];
                    if ($mKey === 'all' || $type === null) {
                        continue;
                    }
                    // 2016-11-17 yjwee 하이픈 없이도 검색되게 처리
                    if ((strpos($params['keyword'], '-') === false) &&
                        \in_array($mKey, explode(',', $hyphenKeys), true)) {
                        $tmpWhere[] = '(REPLACE(m.' . $mKey . ', \'-\', \'\') LIKE concat(\'%\',?,\'%\'))';
                        $this->db->bind_param_push($arrBind, $type, $params['keyword']);
                    } else {
                        $tmpWhere[] = '(m.' . $mKey . ' LIKE concat(\'%\',?,\'%\'))';
                        $this->db->bind_param_push($arrBind, $type, $params['keyword']);
                    }
                }
                $arrWhere[] = '(' . implode(' OR ', $tmpWhere) . ')';
            } elseif ($this->fields[$params['key']] !== null) {
                // 2016-11-17 yjwee 하이픈 없이도 검색되게 처리
                if ((strpos($params['keyword'], '-') === false) &&
                    \in_array($params['key'], explode(',', $hyphenKeys), true)) {
                    $arrWhere[] = 'REPLACE(m.' . $params['key'] . ', \'-\', \'\') LIKE concat(\'%\',?,\'%\')';
                    $this->db->bind_param_push($arrBind, $this->fields[$params['key']], $params['keyword']);
                } else {
                    $arrWhere[] = 'm.' . $params['key'] . ' LIKE concat(\'%\',?,\'%\')';
                    $this->db->bind_param_push($arrBind, $this->fields[$params['key']], $params['keyword']);
                }
            }
        }

        $this->db->bindParameter('memberFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('entryPath', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('appFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('groupSno', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('sexFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('maillingFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('smsFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('calendarFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('marriFl', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameter('connectSns', $params, $arrBind, $arrWhere, 'tableMemberSns', 'ms', 'snsTypeFl');
        $this->db->bindParameter('expirationFl', $params, $arrBind, $arrWhere, 'tableMember', 'm'); //개인정보유효기간 검색

        $this->db->bindParameterByRange('saleCnt', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByRange('saleAmt', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByRange('mileage', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByRange('deposit', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByRange('loginCnt', $params, $arrBind, $arrWhere, 'tableMember', 'm');

        $this->db->bindParameterByDateTimeRange('entryDt', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByDateTimeRange('lastLoginDt', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByDateTimeRange('marriDate', $params, $arrBind, $arrWhere, 'tableMember', 'm');
        $this->db->bindParameterByDateTimeRange('sleepWakeDt', $params, $arrBind, $arrWhere, 'tableMember', 'm');

        // 생일 검색 조건 추가
        if (StringUtils::strIsSet($params['birthFl']) === 'y') { //  특정일 검색
            if (\strlen($params['birthDt'][0]) === 5) {   //  MM-DD
                $arrWhere[] = 'substr(m.birthDt, 6, 5) = ?';
                $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $params['birthDt'][0]);
            } else {    //  YYYY-MM-DD
                $arrWhere[] = 'm.birthDt = ?';
                $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $params['birthDt'][0]);
            }
        } else {    //  범위 검색
            if (\strlen($params['birthDt'][0]) === 5 || \strlen($params['birthDt'][1]) === 5) {   //  날짜를 한개만 입력한 경우
                if (empty($params['birthDt'][0])) {
                    $arrWhere[] = 'substr(m.birthDt, 6, 5) <= ?';
                    $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $params['birthDt'][1]);
                } elseif (empty($params['birthDt'][1])) {
                    $arrWhere[] = 'substr(m.birthDt, 6, 5) >= ?';
                    $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $params['birthDt'][0]);
                } else {
                    $arrWhere[] = 'substr(m.birthDt, 6, 5) BETWEEN ? AND ?';
                    $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $params['birthDt'][0]);
                    $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $params['birthDt'][1]);
                }
            } else {
                $this->db->bindParameterByDateTimeRange('birthDt', $params, $arrBind, $arrWhere, 'tableMember', 'm');
            }
        }

        // 만14세 미만회원만 보기가 체크된 경우 연령층 검색은 전체로 설정된다.
        if (StringUtils::strIsSet($params['under14'], 'n') === 'y') {
            $under14Date = DateTimeUtils::getDateByUnderAge(14);
            $arrWhere[] = 'm.birthDt > ?';
            $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $under14Date);
        } else {
            // 연령층
            $params['age'] = StringUtils::strIsSet($params['age']);
            if ($params['age'] > 0) {
                $ageTerms = DateTimeUtils::getDateByAge($params['age']);
                $arrWhere[] = 'm.birthDt BETWEEN ? AND ?';
                $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $ageTerms[1]);
                $this->db->bind_param_push($arrBind, $this->fields['birthDt'], $ageTerms[0]);
            }
        }

        // 장기 미로그인
        $novisit = (int) $params['novisit'];
        if ($novisit >= 0 && is_numeric($params['novisit'])) {
            $arrWhere[] = 'IF(m.lastLoginDt IS NULL, DATE_FORMAT(m.entryDt,\'%Y%m%d\') <= ?, DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') <= ?)';
            $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $novisit . ' day')));
            $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $novisit . ' day')));
        }

        //휴면전환예정회원 검색.
        if ($params['dormantMemberExpected'] === 'y') {
            $expirationDay = $params['expirationDay'];// 휴면 전환 예정 7일, 30일, 60일
            $expirationFl = $params['expirationFl']; // 개인정보 유효기간 전체,1년,3년,5년 선택값

            //개인정보유효기간 전체 선택 시
            if (!$expirationFl) {
                $arrWhere[] = 'm.expirationFl != \'999\' AND CASE m.expirationFl WHEN \'1\' THEN IF(lastLoginDt = \'0000-00-00 00:00:00\' OR m.lastLoginDt IS NULL, DATE_FORMAT(m.entryDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.entryDt,\'%Y%m%d\') >= ?, DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') >= ?) WHEN \'3\' THEN IF(lastLoginDt = \'0000-00-00 00:00:00\' OR m.lastLoginDt IS NULL, DATE_FORMAT(m.entryDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.entryDt,\'%Y%m%d\') >= ?, DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') >= ?) WHEN \'5\' THEN IF(lastLoginDt = \'0000-00-00 00:00:00\' OR m.lastLoginDt IS NULL, DATE_FORMAT(m.entryDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.entryDt,\'%Y%m%d\') >= ?, DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') >= ?) END';
                $dormantMemberTerms = [
                    365 - $expirationDay,
                    365,
                    1095 - $expirationDay,
                    1095,
                    1825 - $expirationDay,
                    1825,
                ];
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[0] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[1] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[0] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[1] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[2] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[3] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[2] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[3] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[4] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[5] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[4] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[5] . ' day')));
            } else {
                $arrWhere[] = 'IF(lastLoginDt = \'0000-00-00 00:00:00\' OR m.lastLoginDt IS NULL, DATE_FORMAT(m.entryDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.entryDt,\'%Y%m%d\') >= ?, DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') <= ? AND DATE_FORMAT(m.lastLoginDt,\'%Y%m%d\') >= ?)';
                if ((int) $expirationFl === 1) {
                    $dormantMemberTerms = [
                        365 - $expirationDay,
                        365,
                    ];
                } elseif ((int) $expirationFl === 3) {
                    $dormantMemberTerms = [
                        1095 - $expirationDay,
                        1095,
                    ];
                } elseif ((int) $expirationFl === 5) {
                    $dormantMemberTerms = [
                        1825 - $expirationDay,
                        1825,
                    ];
                }
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[0] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[1] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[0] . ' day')));
                $this->db->bind_param_push($arrBind, $this->fields['lastLoginDt'], date('Ymd', strtotime('-' . $dormantMemberTerms[1] . ' day')));
            }
        }

        // 휴면회원 여부
        $arrWhere[] = 'm.sleepFl != \'y\'';
        if (StringUtils::strIsSet($params['mallSno'], '') !== '') {
            $arrWhere[] = 'm.mallSno=?';
            $this->db->bind_param_push($arrBind, $this->fields['mallSno'], $params['mallSno']);
        }

        /* 2020.03.25 */
        /* 회원 리스트에서 SNS AUID 추가함 -> ms.uuid */
        /* 회원 리스트에서 회원가입 경로 추가 -> m.ex6 */
        $this->db->strField = 'm.memNo, m.memId, m.mallSno, m.groupSno, m.memNm, m.nickNm, m.appFl';
        $this->db->strField .= ', m.memberFl, m.smsFl, m.mileage, m.deposit, m.maillingFl';
        $this->db->strField .= ', m.saleAmt, m.saleCnt, m.entryDt, m.lastLoginDt, m.loginCnt, m.sexFl, m.sleepWakeDt';
        $this->db->strField .= ', m.email, m.phone, m.cellPhone, COUNT(mc.memberCouponState) as couponCount';
        $this->db->strField .= ', m.zipcode, m.zonecode, m.address, m.addressSub, m.ex6';
        // $this->db->strField .= ', ms.uuid, IF(ms.connectFl=\'y\', ms.snsTypeFl, \'\') AS snsTypeFl';
        $this->db->strField .= ', IF(ms.snsTypeFl=\'kakao\', ms.uuid, \'\') AS uuid, IF(ms.connectFl=\'y\', ms.snsTypeFl, \'\') AS snsTypeFl';
        $this->db->strWhere = implode(' AND ', $arrWhere);
        $this->db->strOrder = StringUtils::strIsSet($params['sort'], 'm.entryDt desc');
        $this->db->strGroup = 'm.memNo';
        $this->db->strJoin = ' LEFT JOIN ' . DB_MEMBER_COUPON . ' AS mc ON m.memNo = mc.memNo';
        $this->db->strJoin .= ' LEFT JOIN ' . DB_MEMBER_SNS . ' AS ms ON m.memNo = ms.memNo';
    }

}
