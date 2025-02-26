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

namespace Controller\Admin\Member;

use Component\Member\Group\Util as GroupUtil;
use Exception;
use Request;
use Component\Sms\Code;
use Framework\Utility\ComponentUtils;

/**
 * 회원의 마일리지 지급 설정 관리 페이지
 *
 * @author Ahn Jong-tae <qnibus@godo.co.kr>
 * @author Shin Donggyu <artherot@godo.co.kr>
 */
class RecomMileageGiveController extends \Controller\Admin\Controller
{
    public function index()
    {
        /** @var \Bundle\Controller\Admin\Controller $this */
        try {
            $this->callMenu('member', 'point', 'recomMileageGive');

            $data = gd_policy('member.recomMileageGive');
            gd_isset($data['giveFl'], 'y'); // 지급 여부
            gd_isset($data['giveType'], 'price'); // 지급 기준
            gd_isset($data['goods'], 0); // 단품지급 금액 
			gd_isset($data['singleUnit'], 'percent'); //단품 지급 단위
		
			gd_isset($data['subGiveType'], 'price'); // 정기구독 방법
			gd_isset($data['subGoods'], 0); // 정기구독 지급 금액
			gd_isset($data['subUnit'], 'percent'); //정기구독 지급 단위

            gd_isset($data['excludeFl'], 'y'); // 마일리지 사용시 지급여부

            $checked = [];
			$checked['blackListFl'][$data['blackListFl']] =
			$checked['expiryFl'][$data['expiryFl']] =
            $checked['giveFl'][$data['giveFl']] =
            $checked['giveType'][$data['giveType']] =
            $checked['subGiveType'][$data['subGiveType']] =
			$checked['excludeFl'][$data['excludeFl']] = 'checked="checked"';

            $selected['singleUnit'][$data['singleUnit']] = 
			$selected['subUnit'][$data['subUnit']] = 'selected="selected"';

            $mileageBasic = gd_policy('member.mileageBasic');
            $displayMileageBasic[] = '판매가';
            if ($mileageBasic['optionPrice'] == 1) {
                $displayMileageBasic[] = '옵션가';
            }
            if ($mileageBasic['addGoodsPrice'] == 1) {
                $displayMileageBasic[] = '추가상품가';
            }
            if ($mileageBasic['textOptionPrice'] == 1) {
                $displayMileageBasic[] = '텍스트옵션가';
            }
            if ($mileageBasic['goodsDcPrice'] == 1) {
                $displayMileageBasic[] = '상품할인가';
            }
            if ($mileageBasic['memberDcPrice'] == 1) {
                $displayMileageBasic[] = '회원할인가';
            }
            if ($mileageBasic['couponDcPrice'] == 1) {
                $displayMileageBasic[] = '쿠폰할인가';
            }
            $data['mileageBasic'] = implode(' + ', $displayMileageBasic);

			if($data['blackList']) {
				$data['blackList'] = explode(',', $data['blackList']);
				
				if(!is_array($data['blackList'])) {
					$data['blackList'][0] = $data['blackList'];
				}
			}
			
			
			 /** @var string $smsMemberSend SMS 자동발송 정책 가져오기 (마일리지 소멸) */
            $smsMemberSend = function () {
                $config = ComponentUtils::getPolicy('sms.smsAuto');

                return $config['member'][Code::MILEAGE_EXPIRE]['memberSend'];
            };

            // 이메일 자동발송 정책 가져오기 (마일리지 소멸)
            $typeConfig = gd_policy('mail.configAuto');
            $typeConfig = $typeConfig['point']['deletemileage'];
            $typeAutoSendRadio = empty($typeConfig['autoSendFl']) ? 'y' : $typeConfig['autoSendFl'];

			$this->setData('smsMemberSend', $smsMemberSend());
            $this->setData('mailMemberSend', $typeAutoSendRadio);
            $this->setData('mileageBasic', $mileageBasic);
            $this->setData('data', $data);
            $this->setData('checked', $checked);
            $this->setData('selected', $selected);
            $this->setData('groupList', $groupList['data']);
        } catch (Exception $e) {
            throw $e;
        }
    }
}