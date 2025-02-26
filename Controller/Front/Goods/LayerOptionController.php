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
namespace Controller\Front\Goods;

/**
 * Class LayerDeliveryAddress
 *
 * @package Bundle\Controller\Front\Order
 * @author  su
 */
class LayerOptionController extends \Bundle\Controller\Front\Goods\LayerOptionController
{
    public function index()
    {
		parent::index();

		$memId=\Session::get("member.memId");
		if(\Request::getRemoteAddress()=="182.216.219.50" || $memId=="mintweb"){

			$goodsView = $this->getData("goodsView");


			$goodsView = $this->getData("goodsView");
			$subSelect=[];
			$oneSelect=[];
			foreach($goodsView['addViewFl'] as $key =>$v){
				if($v==1)
					$subSelect[]=$key;
				else if($v==2)
					$oneSelect[]=$key;
			}
			$this->setData('subSelect',$subSelect);
			$this->setData('oneSelect',$oneSelect);
			$this->getView()->setPageName('goods/layer_option2.php');
		}
	}
}