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

use Request;
class GoodsViewController extends \Bundle\Controller\Mobile\Goods\GoodsViewController
{
    public function index(){

		$getValue = Request::get()->toArray();
		$postValue = Request::post()->toArray();
		$in=array_merge($getValue,$postValue);

		$db=\App::load(\DB::class);
		$row=$db->fetch("select useSubscription from ".DB_GOODS." where goodsNo='{$in['goodsNo']}'");

		$memId=\Session::get("member.memId");

		$memNo=\Session::get("member.memNo");
		if($memNo) {
			$db->query("delete from ".DB_CART." where memNo='$memNo' and directCart='y'");
		}
       // if($memId=="mintweb" && $row['useSubscription']==1){
        if($row['useSubscription']==1){

			$memNo=\Session::get("member.memNo");
			$sql="select * from wm_subCart where memNo=?";
			$row = $db->query_fetch($sql,['i',$memNo],false);

			$cart = new \Component\Cart\WmCart();

			$cart->setMemberCouponDelete($row['sno']);

			//gd_debug($row);
			//$sql="delete from wm_subCart where memNo=?";
			//$db->bind_query($sql,['i',$memNo]);

            $getValue = Request::get()->toArray();
			
			$subObj=new \Component\Subscription\Schedule;
			
			$result=$subObj->goodsRatio($in['goodsNo']);

			$this->setData("subInfo",$result);

		

			$this->getView()->setPageName('goods/goods_view_sub.php');

			/*if(\Request::getRemoteAddress()=="182.216.219.50"){

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
				
				$this->getView()->setPageName('goods/goods_view_sub2.php');
			}
			*/

        }
		parent::index();
		
		//루딕스-brown 상품별로 추천인 마일리지 지급
		//통합 추천인 마일리지 설정
		$recomMileageConfig = gd_policy('member.recomMileageGive');
		$this->setData('recomMileageConfig', $recomMileageConfig);
		//통합 추천인 마일리지 설정

		//친구추천시 받는 마일리지
		$goodsView = $this->getData('goodsView');
		$recomMileage = 0;
		if($goodsView['recomMileageFl'] == 'c'){//통합
			if($recomMileageConfig['singleUnit'] == 'percent'){
				$singleText = '결제금액의 '.gd_isset($recomMileageConfig['goods'], 0).'%';
			}else {
				$singleText = number_format(gd_isset($recomMileageConfig['goods'],0)).'원';
			}
			
			if($recomMileageConfig['subUnit'] == 'percent'){
				$subText = '결제금액의 '.gd_isset($recomMileageConfig['subGoods'], 0).'%';
			}else {
				$subText = number_format(gd_isset($recomMileageConfig['subGoods'], 0)).'원';
			}
		}else {//개별
			if($goodsView['recomNoMileageUnit'] == 'percent'){
				$singleText = '결제금액의 '.gd_isset($goodsView['recomNoMileage'], 0).'%';
			}else {
				$singleText = number_format(gd_isset($goodsView['recomNoMileage'], 0)).'원';
			}
		
			if($goodsView['recomSubMileageUnit'] == 'percent'){
				$subText = '결제금액의 '.gd_isset($goodsView['recomSubMileage'], 0).'%';
			}else {
				$subText = number_format(gd_isset($goodsView['recomSubMileage'], 0)).'원';
			}
		}
		$this->setData('subText', $subText);
		$this->setData('singleText', $singleText);
		//루딕스-brown 상품별로 추천인 마일리지 지급
		
    }

}