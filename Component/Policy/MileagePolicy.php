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
namespace Component\Policy;

use Globals;
use Message;

/**
 * Class MileagePolicy
 * @package Bundle\Component\Policy
 * @author  yjwee
 */
class MileagePolicy extends \Bundle\Component\Policy\MileagePolicy
{
	 public function saveRecomMileageGive(array $getValue)
    {
		if(array_filter($getValue['blackList'])) {
			$getValue['blackList'] = implode(',', array_filter($getValue['blackList']));
		}

        gd_isset($getValue['updateGiveFl'], 'self'); // 지급 설정의 주체(self: 지급설정, basic: 기본설정)
        gd_isset($getValue['giveFl'], 'y'); // 지급 여부
        gd_isset($getValue['giveType'], 'price'); // 단품 지급 방법
		gd_isset($getValue['goods'], 0); // 단품 지급 금액
		gd_isset($getValue['singleUnit'], 'percent'); //단품 지급 단위
		
		gd_isset($getValue['subGiveType'], 'price'); // 정기구독 방법
		gd_isset($getValue['subGoods'], 0); // 정기구독 지급 금액
		gd_isset($getValue['subUnit'], 'percent'); //정기구독 지급 단위

        gd_isset($getValue['excludeFl'], 'y'); // 마일리지 사용시 지급예외 여부
		gd_isset($getValue['blackListFl'], 'n'); //단품 지급 단위

		gd_isset($getValue['expiryFl'], 'n'); //유효기간

		
		//if($getValue['blackListFl'] == 'n') {
			//unset($getValue['blackList']);
		//}
		unset($getValue['mode']);

        //$basic = gd_policy('member.mileageBasic');
        //if ($getValue['updateGiveFl'] == 'self' && $getValue['giveFl'] == 'y' && $basic['payUsableFl'] == 'n') {
            //throw new \Exception(__('마일리지 기본 설정이 사용안함 상태입니다. 지급 설정 사용함으로 변경을 할 수 없습니다.'), 500);
        //}

        if ($this->setValue('member.recomMileageGive', $getValue) != true) {
            throw new \Exception(__('처리중에 오류가 발생하여 실패되었습니다.'), 500);
        }
    }

	public function addRecomBlackList($arrData){
		$recomMileageGive = gd_policy('member.recomMileageGive');
		$member = \App::load('\\Component\\Member\\Member');
		$addBlackList = [];
		$oldBlackList = explode(',',$recomMileageGive['blackList']);
		foreach($arrData['chk'] as $key => $memNo) {
			$memId = $member->getMemberId($memNo)['memId'];
			if(! in_array($memId, $oldBlackList)){
				$addBlackList[] = $memId;
			} 
		}
		
		if(count($addBlackList) > 0) {
			if($recomMileageGive['blackList']) {
				$recomMileageGive['blackList'] = $recomMileageGive['blackList'].','.implode(',',$addBlackList);
			}else {
				$recomMileageGive['blackList'] = implode(',',$addBlackList);
			}
			
			$this->setValue('member.recomMileageGive', $recomMileageGive);
		}
	}
}