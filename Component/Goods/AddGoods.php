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

namespace Component\Goods;

use Component\Database\DBTableField;
use Component\Mall\Mall;
use Globals;
use LogHandler;
use Request;
use SESSION;

/**
 * 추가 상품 관련 클래스
 * @author Jung Youngeun <atomyang@godo.co.kr>
 */
class AddGoods extends \Bundle\Component\Goods\AddGoods
{
    public function getInfoAddGoodsGoods($addGoodsNo,$arrBind = null,$strOrder ='ag.regDt desc',$addWhere = null)
    {
		$data = parent::getInfoAddGoodsGoods($addGoodsNo, $arrBind, $strOrder, $addWhere);
        
		// 2024-09-30 wg-eric 특정 상품번호에서 추가상품 1개만 주문가능하게
		$goodsNo = \request::get()->toarray()['goodsNo'];
		if(!$goodsNo) $goodsNo = \request::post()->all()['goodsNo'];

		if($goodsNo == '1000000089' || $goodsNo == '1000000094') {
			foreach($data as $key => $val) {
				$data[$key]['stockUseFl'] = 1;
				$data[$key]['stockCnt'] = 1;
			}
		}

        return $data;
    }
}
