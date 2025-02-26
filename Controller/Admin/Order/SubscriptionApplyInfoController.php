<?php

namespace Controller\Admin\Order;

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;
use Component\Goods\Goods;

/**
* 정기배송 신청내용 
*
* @author webnmobile
*/
class SubscriptionApplyInfoController extends \Controller\Admin\Controller
{
	public function index()
	{
		try {
			$this->getView()->setDefine("layout", "layout_blank");
			$idx = Request::get()->get("idx");
			if (empty($idx))
				throw new AlertOnlyException("잘못된 접근입니다.");
			
			$subscription = App::load(\Component\Subscription\Subscription::class);
			$db = App::load(\DB::class);


			$info = $subscription->getApplyInfo($idx);
			if (!$info)
				throw new AlertOnlyException("신청 정보가 존재하지 않습니다.");

				
			$info['deliveryPeriod2'] = implode("_", $info['deliveryPeriod']);
			if ($info['schedules']) {
				foreach ($info['schedules'] as $key => $s) {
					foreach ($s['goods'] as $k => $v) {
						$sql  = "SELECT goodsNm FROM " . DB_GOODS . " WHERE goodsNo = ?";
						$row = $db->query_fetch($sql, ["i", $v['goodsNo']], false);
						$v['goodsNm'] = $row['goodsNm'];
						$info['schedules'][$key]['goods'][$k] = $v;

						$addGoodsNoData = json_decode(stripslashes($v['addGoodsNo']));
						$addGoodsNo=[];
						
						foreach($addGoodsNoData as $addKey=>$addVal){
							$addGoodsNo[]=$addVal;
							
							
						}
						$addSQL="select goodsNm from ".DB_ADD_GOODS." where addGoodsNo IN(".implode(",",$addGoodsNo).")";
						$addRows=$db->query_fetch($addSQL);
						$addGoodsNm=[];
						foreach($addRows as $ak =>$at){
							$addGoodsNm[]=$at['goodsNm'];
						}
						$info['schedules'][$key]['goods'][$k]['addGoodsNm']=implode(",",$addGoodsNm);
						

					}
					
					$FailLogSQL = "select * from wm_subscription_fail where scheduleIdx=?";
					$FailLogROW = $db->query_fetch($FailLogSQL,['i',$s['idx']]);
					
					$info['schedules'][$key]['fail_log']=$FailLogROW[0]['fail_log'];
				}
			}
		
			
			//gd_debug($info);
			$this->setData($info);
			$sql = "SELECT g.goodsNo, g.goodsNm,a.goodsCnt,a.optionSno FROM wm_subApplyGoods AS a 
								INNER JOIN " . DB_GOODS . " AS g ON a.goodsNo = g.goodsNo WHERE a.idxApply = ? LIMIT 0, 1";
								
			$row = $db->query_fetch($sql, ["i", $idx], false);
			

			$goodsNo = $row['goodsNo'];
			$this->setData("goodsNo",$goodsNo);
			$goodsNm = $row['goodsNm'];
			$this->setData("goodsNm",$goodsNm);
			$goodsCnt = $row['goodsCnt'];
			$optionSno = $row['optionSno'];
			$this->setData("goodsCnt", $goodsCnt);
			$conf = $subscription->getCfg([$goodsNo]);
			$this->setData("conf", $conf);

						
			$strSQL="select sno,optionValue1,optionValue2,optionValue3,optionValue4,optionValue5 from ".DB_GOODS_OPTION." b where b.goodsNo='{$goodsNo}'";
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

				if($optionSno==$t['sno'])
					$option_result[$key]['checked']="selected";
			}
			

			$result['option']=$option_result;

			$this->setData($result);
			
			$sql = "SELECT a.*, m.memNm, m.memId, m2.managerId, m2.managerNm FROM wm_subAddressChangeLog AS a 
							LEFT JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
							LEFT JOIN " . DB_MANAGER . " AS m2 ON m2.managerId = a.managerId 
						WHERE a.idxApply = ? ORDER BY a.regDt";
			$list = $db->query_fetch($sql, ["i", $idx]);
			$this->setData("addrLogs", $list);

			$cfg=$subscription->getCfg();
			$pause_period=explode(",",$cfg['pause']);
			$this->setData("pause_period",$pause_period);




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
			}

			$this->setData("AddGoodsList",$AddGoodsList);
			$this->setData("AddGoodsNmList",$AddGoodsNmList);

			$this->setData("AddGoodsChecked",$AddGoodsChecked);
			$this->setData("AddGoodsCnt",$AddGoodsCnt);

			$this->setData("OrderAddGoodsList",$OrderAddGoodsList);
			$this->setData("OrderAddGoodsCnt",$OrderAddGoodsCnt);

			$this->setData("pause_period",$pause_period);
			$this->setData("pause_period",$pause_period);



			//신청 변경로그시작
			//$logSQL="select * from wm_user_change_log where idxApply=? orderby idx DESC";
			$logSQL="select a.*,(select mm.memNm from ".DB_MEMBER." mm where mm.memNo=a.memNo)as memNm,if(a.changeUser='user',(select memNm from ".DB_MEMBER." m where m.memNo=a.memNo and changeUser='user') , a.changeUser) as modMemNm from wm_user_change_log a where 1=1 and idxApply=?  order by a.idx DESC";

			$rows= $db->query_fetch($logSQL,['i',$idx]);
			foreach($rows as $key =>$t){

				$after_content=str_replace("=||=","</br>",$t['after_content']);

				$rows[$key]['after_content']=$after_content;
				
				switch($t['mode']){
					case "update_change_apply":
						$rows[$key]['mode']="배송지 및 결제카드,추가옵션 ,결제주기,옵션 변경";
						break;
					case "change_date":
						$rows[$key]['mode']="배송일 수령일 변경";
						break;	
					case "stop_subscription":
						$rows[$key]['mode']="정기배송 중단";
						break;	
						
					case "pause_subscription":
						$rows[$key]['mode']="정기배송 일시정지";
						break;	
					case "pause_subscription":
						$rows[$key]['mode']="정기배송 일시정지";
						break;	

					case "update_apply_list":
						$rows[$key]['mode']="신청목록 수정";
						break;	
					case "update_apply_info":
						$rows[$key]['mode']="정기배송 신청 변경";
						break;
					case "delete_apply_list":
						$rows[$key]['mode']="신청목록 삭제";
						break;
					case "change_card":
						$rows[$key]['mode']="카드 변경";
						break;
					case "change_delivery_date":
						$rows[$key]['mode']="배송일 변경";
						break;
					case "change_address":
						$rows[$key]['mode']="배송 주기 / 배송 주소 변경";
						break;
					case "update_schedule_list":
						$rows[$key]['mode']="스케줄 변경";
						break;
					case "delete_schedule_list":
						$rows[$key]['mode']="스케줄 삭제";
						break;
					case "add_schedule":
						$rows[$key]['mode']="스케줄 추가";
						break;
				}
				
			}
			$this->setData("data",$rows);
			//신청 변경로그 종료

			
            $this->getView()->setPageName('order/subscription_apply_info_test.php');

		} catch (AlertOnlyException $e) {
			throw $e;
		}
		
	}
}