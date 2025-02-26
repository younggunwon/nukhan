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
namespace Controller\Mobile\Goods;

/**
 * Class LayerDeliveryAddress
 *
 * @package Bundle\Controller\Front\Order
 * @author  su
 */
use Request;
class LayerOptionController extends \Bundle\Controller\Mobile\Goods\LayerOptionController
{

    public function index(){

		$getValue = Request::get()->toArray();
		$postValue = Request::post()->toArray();
		$in=array_merge($getValue,$postValue);

		$db=\App::load(\DB::class);
		$row=$db->fetch("select useSubscription from ".DB_GOODS." where goodsNo='{$in['goodsNo']}'");

		$memId=\Session::get("member.memId");
	    //if($memId=="mintweb" && $row['useSubscription']==1){

		
	    if($row['useSubscription']==1){
			$this->getView()->setPageName('goods/layer_option3.php');

			$subObj=new \Component\Subscription\Schedule;
			
			$result=$subObj->goodsRatio($in['goodsNo']);

			$this->setData("subInfo",$result);

			$cfg=$subObj->getCfg($in['goodsNo']);

			$this->setData('unitPrecision',$cfg['unitPrecision']);
			$this->setData('unitRound',$cfg['unitRound']);

			$this->setData('dc_ratio',$result['delivery_dc_ratio'][0]);

			$this->setData("subInfo",$result);

            parent::index();

			$memId=\Session::get("member.memId");

			//정기결제카드정보
			$list = $subObj->getCards();
			if(empty($list[0]['idx']))
				$this->setData("cardEmpty",1);


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

			
			$this->getView()->setPageName('goods/layer_option3.php');

			/*if(\Request::getRemoteAddress()=="182.216.219.50" || $memId=="mintweb"){
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
				$this->getView()->setPageName('goods/layer_option4.php');

			}*/

        } else parent::index();
    }
}