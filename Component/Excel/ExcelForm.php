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

namespace Component\Excel;

use Component\Database\DBTableField;
use Component\Member\Manager;
use Component\Validator\Validator;
use Framework\Debug\Exception\AlertBackException;
use Framework\Utility\ArrayUtils;
use Framework\Utility\GodoUtils;
use Session;

/**
 * Class ExcelForm
 * @package Bundle\Component\Excel
 * @author  yjwee <yeongjong.wee@godo.co.kr>
 *          atomyang
 */
class ExcelForm extends \Bundle\Component\Excel\ExcelForm
{

    /** 회원 엑셀 다운로드시 SNS UUID 추가를 위한 함수 (2020.03.25) */
    public function setExcelFormMember($location)
    {
        $setData = [];
        //@formatter:off
        switch ($location) {
            case 'member_list':
                $setData = [
                    'memNo' => ['name'=>__('회원번호')],
                    'regDt' =>['name'=>__('등록일')],
                    'entryDt' =>['name'=>__('가입승인일')],
                    'appFl' =>['name'=>__('가입승인여부')],
                    'entryPath' =>['name'=>__('가입경로')],
                    'lastLoginDt' =>['name'=>__('최종로그인일')],
                    'sleepWakeDt' =>['name'=>__('휴면해제일')],
                    'memberFl' =>['name'=>__('회원구분')],
                    'groupSno' =>['name'=>__('등급')],
                    'memId' =>['name'=>__('아이디')],
                    'nickNm' =>['name'=>__('닉네임')],
                    'memPw' =>['name'=> sprintf('%s(%s)', __('비밀번호'), __('암호화문자'))],
                    'memNm' =>['name'=>__('이름')],
                    'email' =>['name'=>__('이메일')],
                    'cellPhone' =>['name'=>__('휴대폰번호')],
                    'phone' =>['name'=>__('전화번호')],
                    'address' =>['name'=>__('주소')],
                    'maillingFl' =>['name'=>__('메일수신여부')],
                    'smsFl' =>['name'=>__('SMS수신여부')],
                    'saleCnt' =>['name'=>__('상품주문건수')],
                    'saleAmt' =>['name'=>__('주문금액'),'type'=>'price'],
                    'mileage' =>['name'=>__('마일리지'),'type'=>'mileage'],
                    'deposit' =>['name'=>__('예치금'),'type'=>'deposit'],
                    'loginCnt' =>['name'=>__('방문횟수')],
                    'company' =>['name'=>__('상호')],
                    'busiNo' =>['name'=>__('사업자번호')],
                    'ceo' =>['name'=>__('대표자명')],
                    'service' =>['name'=>__('업태')],
                    'item' =>['name'=>__('종목')],
                    'comAddress' =>['name'=>__('사업자 주소')],
                    'fax' =>['name'=>__('팩스 번호')],
                    'recommId' =>['name'=>__('추천인아이디')],
                    'sexFl' =>['name'=>__('성별')],
                    'birthDt' =>['name'=>__('생일')],
                    'marriFl' =>['name'=>__('결혼여부')],
                    'marriDate' =>['name'=>__('결혼기념일')],
                    'job' =>['name'=>__('직업')],
                    'interest' =>['name'=>__('관심분야')],
                    'expirationFl' =>['name'=>__('개인정보유효기간')],
                    'memo' =>['name'=>__('남기는말씀')],
                    'ex1' =>['name'=>sprintf(__('추가정보%d'), 1)],
                    'ex2' =>['name'=>sprintf(__('추가정보%d'), 2)],
                    'ex3' =>['name'=>sprintf(__('추가정보%d'), 3)],
                    'ex4' =>['name'=>sprintf(__('추가정보%d'), 4)],
                    'ex5' =>['name'=>sprintf(__('추가정보%d'), 5)],
                    // 'ex6' => ['name'=>__('가입코드')],
                    'ex6' =>['name'=>sprintf(__('추가정보%d'), 6)],
                    'mallSno' => ['name'=>__('상점구분')],
                    'uuid' => ['name'=>__('AUID')], // 회원 엑셀 다운로드폼에서 SNS AUID 추가함(2020.03.25)
                ];

                break;
        }
        //@formatter:on
        return $setData;

    }

	public function setExcelFormOrder($location) {
		$setData = parent::setExcelFormOrder($location);

        //@formatter:off
        switch ($location) {
            case 'order_list_all':
				$setData['totalRealSettlePrice'] = ['name'=>__('총 실결제금액'), 'orderFl'=>'y']; // 2024-11-25 wg-eric
			break;
		}

		return $setData;
	}
}
