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

use Request;

class GoodsViewController extends \Bundle\Controller\Front\Goods\GoodsViewController
{
    public function index(){

		parent::index();

		$getValue = Request::get()->toArray();
		$postValue = Request::post()->toArray();
		$in=array_merge($getValue,$postValue);

		$db=\App::load(\DB::class);
		$row=$db->fetch("select useSubscription from ".DB_GOODS." where goodsNo='{$in['goodsNo']}'");

		$memNo=\Session::get("member.memNo");
		if($memNo) {
			$db->query("delete from ".DB_CART." where memNo='$memNo' and directCart='y'");
		}
		
		if($row['useSubscription']==1){
			
			
			$sql="select * from wm_subCart where memNo=?";
			$row = $db->query_fetch($sql,['i',$memNo],false);

			$cart = new \Component\Cart\WmCart();

			$cart->setMemberCouponDelete($row['sno']);


			$this->getView()->setPageName('goods/goods_view2.php');

			$subObj=new \Component\Subscription\Schedule;
			
			$result=$subObj->goodsRatio($in['goodsNo']);
			$goodsView = $this->getData("goodsView");

			$cfg=$subObj->getCfg($in['goodsNo']);

			$this->setData('unitPrecision',$cfg['unitPrecision']);
			$this->setData('unitRound',$cfg['unitRound']);

					
			$this->setData("subInfo",$result);

			//정기결제카드정보
			$list = $subObj->getCards();
			if(empty($list[0]['idx']))
				$this->setData("cardEmpty",1);

			$memId=\Session::get("member.memId");

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
			
			
			$this->getView()->setPageName('goods/goods_view2.php');
			

			if(\Request::getRemoteAddress()=="182.216.219.50" || $memId=="mintweb"){
				//$this->setData("cardEmpty",1);
				/*

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
				*/
				
				//$this->getView()->setPageName('goods/goods_view3.php');
			}

        } 


		
    }
}