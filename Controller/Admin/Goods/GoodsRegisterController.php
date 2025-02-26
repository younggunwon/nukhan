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
namespace Controller\Admin\Goods;

/**
 * 상품 등록 / 수정 페이지
 */
class GoodsRegisterController extends \Bundle\Controller\Admin\Goods\GoodsRegisterController
{
	/**
     * index
     *
     * @throws Except
     */
    public function index()
    {
		parent::index();

		//루딕스-brown 추천인 마일리지 통합/개별
		$conf = $this->getData('conf');
		$conf['recomMileage'] = gd_policy('member.recomMileageGive'); // QR코드 설정
		$this->setData('conf', $conf);
		$data = $this->getData('data');
		$checked = $this->getData('checked');
		$checked['recomMileageFl'][$data['recomMileageFl']] = 'checked="checked"';
		$this->setData('checked', $checked);

        $selected = $this->getData('selected');
		$selected['recomNoMileageUnit'][$data['recomNoMileageUnit']] = 
		$selected['recomSubMileageUnit'][$data['recomSubMileageUnit']] = 'selected';
		$this->setData('selected', $selected);
	}
}