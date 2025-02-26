<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Exception;
use Session;

use Component\Goods\Goods;
/**
* 배송지 및 결제카드 변경 
*
* @author webnmobile
*/
class ChangeApplyController extends \Controller\Front\Controller
{
	public function index()
	{
		$this->getView()->setDefine("header", "outline/_share_header.html");
		$this->getView()->setDefine("footer", "outline/_share_footer.html");
		try {
			$idxApply = Request::get()->get("idxApply");
			if (!$idxApply)
				throw new Exception("잘못된 접근입니다.");		
		
			if (!gd_is_login())
				throw new Exception("로그인이 필요한 페이지 입니다.");
			
			$memNo = Session::get("member.memNo");
			$subscription = App::load(\Component\Subscription\Subscription::class);
			
			/* 정기결제 신청 정보 추출 */
			$info = $subscription->getApplyInfo($idxApply);
			if (!$info)
				throw new Exception("신청정보가 존재하지 않습니다.");
			
			if ($info['memNo'] != $memNo)
				throw new Exception("본인이 신청건만 변경하실 수 있습니다.");

						
			/* 설정 추출 */
			$conf = $subscription->getCfg();
			$this->setData("conf", $conf);
			
			/* 배송지 목록 추출 */
			$order = App::load(\Component\Order\Order::class);
			$shippingList = $order->getShippingAddressList($pageNo, 1000);
			$this->setData('shippingList', $shippingList);
			 
			/* 결제 카드 */
			$this->setData("cardList", $subscription->getCards());
			
			$this->setData("idxApply", $idxApply);
			$this->addScript(["wm/change_apply.js"]);

			
			$db=\App::load("DB");
		
			
			$strSQL="select a.idx,a.optionSno,a.goodsNo,a.addGoodsNo,a.addGoodsCnt from wm_subApplyGoods a  where a.idxApply='{$info['idx']}'";
			

			$result=$db->query_fetch($strSQL);

			$goods = new Goods();

			

			$OrderAddGoodsList=[];
			$OrderAddGoodsCnt=[];

			$AddGoodsList=[];
			$AddGoodsNmList=[];
			$AddGoodsChecked=[];
			$AddGoodsCnt=[];

			foreach($result as $op_key =>$op_d){

				$addGoodsNo=json_decode(stripslashes($op_d['addGoodsNo']));
				$addGoodsCnt=json_decode(stripslashes($op_d['addGoodsCnt']));

				foreach($addGoodsNo as $k =>$t){
					$OrderAddGoodsList[]=$t;
					$OrderAddGoodsCnt[]=$addGoodsCnt[$k];
					
				}

			
				$goodsInfo = $goods->getGoodsView($op_d['goodsNo']);
				foreach($goodsInfo['addGoods'] as $TopAddKey=>$TopAddVal){
					
					foreach($TopAddVal['addGoodsList'] as $SubAddKey =>$addList){

						$AddGoodsList['goodsNo'][]=$addList['addGoodsNo'];
						$AddGoodsNmList['goodsNm'][]=$addList['goodsNm'];

						foreach($OrderAddGoodsList as $adKey =>$adVal){
						
							if($adVal==$addList['addGoodsNo']){
								$AddGoodsChecked['checked'][$adVal]="checked";
								$AddGoodsCnt['goodsCnt'][$adVal]=$OrderAddGoodsCnt[$adKey];

							}else{
								//$AddGoodsChecked['checked'][]="";
								//$AddGoodsCnt['goodsCnt'][]=0;
							}
						}

						

					}
				
				}
					
				$strSQL="select sno,optionValue1,optionValue2,optionValue3,optionValue4,optionValue5 from ".DB_GOODS_OPTION." b where b.goodsNo='{$op_d['goodsNo']}'";

				$option_result = $db->query_fetch($strSQL);

				foreach($option_result as $key =>$t){
					if(!empty($t['optionValue1']))
						$option=$t['optionValue1'];
					if(!empty($t['optionValue2']))
						$option.="/".$t['optionValue2'];
					if(!empty($t['optionValue3']))
						$option.="/".$t['optionValue3'];
					if(!empty($t['optionValue4']))
						$option.="/".$t['optionValue4'];
					if(!empty($t['optionValue5']))
						$option.="/".$t['optionValue5'];

					$option_result[$key]['option']=$option;

					if($op_d['optionSno']==$t['sno'])
						$option_result[$key]['checked']="checked";
				}
				

				$result[$op_key]['option']=$option_result;

			}

			
			$this->setData("option", $result);
			$this->setData("info", $info);	

			$this->setData("AddGoodsList",$AddGoodsList);
			$this->setData("AddGoodsNmList",$AddGoodsNmList);

			$this->setData("AddGoodsChecked",$AddGoodsChecked);
			$this->setData("AddGoodsCnt",$AddGoodsCnt);

			$this->setData("OrderAddGoodsList",$OrderAddGoodsList);
			$this->setData("OrderAddGoodsCnt",$OrderAddGoodsCnt);

			$this->setData('goodsNo', $goodsInfo['goodsNo']);

		} catch (Exception $e) {
			$this->js("alert('".$e->getMessage()."');parent.wmLayer.close();");
		}
	}
}