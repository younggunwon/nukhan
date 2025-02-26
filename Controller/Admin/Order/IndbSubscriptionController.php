<?php

namespace Controller\Admin\Order;

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;
use Exception;

/**
* 정기결제 관련 DB 처리 
*
* @author webnmobile
*/
class IndbSubscriptionController extends \Controller\Admin\Controller 
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			
			$subscription = App::load(\Component\Subscription\Subscription::class);
			$db = App::load(\DB::class);

			$manager = \Session::get("manager");
			$managerId = $manager['managerId'];

			switch ($in['mode']) {
				/* 신청 목록 수정 */
				case "update_apply_list" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("수정할 신청건을 선택하세요.");

					$in_idx="idx IN('".implode("','",$in['idx'])."')";	
					$sql="select * from wm_subSchedules where".$in_idx;
					$rows=$db->query_fetch($sql);
					
					$pre_content=addslashes(json_encode($rows,JSON_UNESCAPED_UNICODE));

					foreach ($in['idx'] as $idx) {


						if ($in['status'][$idx]) {
							foreach ($in['status'][$idx] as $idxSchedule => $status) {
								$param = [
									'status =?',
								];
								
								$bind = [
									'si', 
									$status,
									$idxSchedule,
								];

								$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
							}
						}
					}
					$sql="select * from wm_subSchedules where".$in_idx;
					$rows=$db->query_fetch($sql);
					$next_content=addslashes(json_encode($rows,JSON_UNESCAPED_UNICODE));

					$db->query("insert into wm_user_change_log set before_content='{$pre_content}',after_content='{$next_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");


					return $this->layer("수정되었습니다.");


					break;
				/* 정기배송 신청/배송 변경 S */
				case "update_apply_info" : 
					if (empty($in['idxApply']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (empty($in['receiverName']))
						throw new AlertOnlyException("받는분 이름을 입력하세요.");
					
					if (empty($in['receiverCellPhone']))
						throw new AlertOnlyException("휴대전화번호를 입력하세요.");
					
					if (empty($in['receiverZonecode']) || empty($in['receiverAddress']) || empty($in['receiverAddressSub']))
						throw new AlertOnlyException("배송주소를 입력하세요.");
					
					/* 정기결제 신청 정보 추출 */
					$list = $subscription->getSchedules($in['idxApply']);

					if (!$list)
						throw new AlertOnlyException("신청정보가 존재하지 않습니다.");

					$pre_list=$list;


					$sql = "SELECT * FROM wm_subDeliveryInfo WHERE idxApply = ?";
					$prevAddress = $db->query_fetch($sql, ["i", $in['idxApply']], false);
					//$manager = \Session::get("manager");
					//$managerId = $manager['managerId'];


					$sql="select * from wm_subApplyInfo where idx=?";
					$row=$db->query_fetch($sql,['i',$in['idxApply']],false);
					$pre_wm_subApplyInfo =$row;
					
					// 2024-06-27 wg-eric payMethodFl, deliveryDayPeriod 추가
					$param = [
						'deliveryPeriod = ?',
						'autoExtend = ?',
						'payMethodFl = ?',
						'deliveryDayPeriod = ?',
					];

					$bind = [
						'sisii', 
						$in['period'],
						$in['autoExtend']?1:0,
						$in['payMethodFl'],
						$in['dayPeriod'],

						$in['idxApply'],
					];
					
					$db->set_update_db("wm_subApplyInfo", $param, "idx = ?", $bind);
					
					$sql="select * from wm_subApplyInfo where idx=?";
					$row=$db->query_fetch($sql,['i',$in['idxApply']],false);
					$new_wm_subApplyInfo =$row;

					$different_data=[];

					foreach($pre_wm_subApplyInfo as $wk =>$wt){
						
						if($new_wm_subApplyInfo[$wk]!=$wt){
							if($wk!="modDt" && $wk!="idx")
								$different_data[]="정기결제신청번호:".$in['idxApply']."의 ".$wk."관련값".$wt."에서".$new_wm_subApplyInfo[$wk]."로 변경되었습니다.";
						}
					}

					//if(\Request::getRemoteAddress()=="112.146.205.124"){
						
						if(count($different_data)>0){

							$after_content=implode("=||=",$different_data);
							
							$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
						}
						
					//}

					$sql="select b.idx from wm_subSchedules a INNER JOIN wm_subSchedulesGoods b ON a.idx=b.idxSchedule where a.idxApply='{$in['idxApply']}' and a.status='ready'";
					$rows = $db->query_fetch($sql);

					$wm_subSchedulesGoods_idx=[];
					foreach($rows as $sk =>$sv){
						$wm_subSchedulesGoods_idx[]=$sv['idx'];
					}

					if(count($wm_subSchedulesGoods_idx)>0){
						$in_idxSchedule=" idx IN ('".implode("','",$wm_subSchedulesGoods_idx)."')";

						$sql="select * from wm_subSchedulesGoods where".$in_idxSchedule;
						$row=$db->query_fetch($sql);
						$pre_wm_subSchedulesGoods =$row;
					}


					$sql="select * from wm_subApplyGoods where idxApply=?";
					$row=$db->query_fetch($sql,['i',$in['idxApply']]);
					$pre_wm_subApplyGoods =$row;


					$param = [
						'goodsCnt = ?',
						'optionSno =?'
					];
					
					$bind = [
						'iii',
						gd_isset($in['goodsCnt'], 1),
						gd_isset($in['optionSno'], 1),
						$in['idxApply'],
					];
					
					$db->set_update_db("wm_subApplyGoods", $param, "idxApply = ?", $bind);
					


					if ($list) {
						$param = [
							'orderName = ?',
							'orderCellPhone = ?',
							'orderZonecode = ?',
							'orderZipcode = ?',
							'orderAddress = ?',
							'orderAddressSub = ?',
							'receiverName = ?',
							'receiverPhone = ?',
							'receiverCellPhone = ?',
							'receiverZonecode = ?',
							'receiverZipcode = ?',
							'receiverAddress = ?',
							'receiverAddressSub = ?',
							'orderMemo = ?',
						];
						
						foreach ($list as $li) {
							if ($li['status'] == 'paid') continue;
							
							$bind = [
								'ssssssssssssssi',
								$in['orderName'],
								$in['orderCellPhone'],
								$in['orderZonecode'],
								$in['orderZipcode'],
								$in['orderAddress'],
								$in['orderAddressSub'],
								$in['receiverName'],
								$in['receiverPhone'],
								$in['receiverCellPhone'],
								$in['receiverZonecode'],
								$in['receiverZipcode'],
								$in['receiverAddress'],
								$in['receiverAddressSub'],
								$in['orderMemo'],
								$li['idx'],
							];	
				
							$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
							
							$db->set_update_db("wm_subSchedulesGoods", ["goodsCnt = ?,optionSno=?"], "idxSchedule = ?", ["iii", gd_isset($in['goodsCnt'], 1),gd_isset($in['optionSno'],1), $li['idx']]);
						} // endforeach 
						
						
						$bind = [
							'ssssssssssssssi',
							$in['orderName'],
							$in['orderCellPhone'],
							$in['orderZonecode'],
							$in['orderZipcode'],
							$in['orderAddress'],
							$in['orderAddressSub'],
							$in['receiverName'],
							$in['receiverPhone'],
							$in['receiverCellPhone'],
							$in['receiverZonecode'],
							$in['receiverZipcode'],
							$in['receiverAddress'],
							$in['receiverAddressSub'],
							$in['orderMemo'],
							$in['idxApply'],
						];
						
						$affectedRows = $db->set_update_db("wm_subDeliveryInfo", $param, "idxApply = ?", $bind);
					} // endif 
					

					$sql="select * from wm_subSchedules where idxApply=?";
					$row=$db->query_fetch($sql,['i',$in['idxApply']]);
					$new_list =$row;

					//if(\Request::getRemoteAddress()=="112.146.205.124"){
						$different_data=[];
						foreach($pre_list as $wkk=>$wtt){
							foreach($wtt as $wk =>$wt){
						
								if($wt!=$new_list[$wkk][$wk] && $wk!="regDt" && $wk!="modDt" && $wk!="deliveryYoilStr"){
									$different_data[]="정기결제신청번호:".$in['idxApply']."의 ".$wk."관련값 '".$wt."' 에서 '".$new_list[$wkk][$wk]."' 로 변경되었습니다.";
								}
							}
						}

						if(count($different_data)){
							$after_content=implode("=||=",$different_data);
							$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");	
						}

					//}


					if ($affectedRows < 0) {
						throw new AlertOnlyException("변경에 실패하였습니다.");
					}
					
					$prevAddr = "[".$prevAddress['receiverZonecode']."]".$prevAddress['receiverAddress'] . " " .$prevAddress['receiverAddressSub'] . " / ".$prevAddress['receiverName'] . "/".$prevAddress['receiverCellPhone'];
					$newAddr = "[".$in['receiverZonecode']."]".$in['receiverAddress'] . " " .$in['receiverAddressSub'] . " / ".$in['receiverName'] . "/".$in['receiverCellPhone'];
					$prevOrderInfo = "[".$prevAddress['orderZonecode']."]".$prevAddress['orderAddress'] . " " .$prevAddress['orderAddressSub'] . " / ".$prevAddress['orderName'] . "/".$prevAddress['orderCellPhone'];
					$newOrderInfo = "[".$in['orderZonecode']."]".$in['orderAddress'] . " " .$in['orderAddressSub'] . " / ".$in['orderName'] . "/".$in['orderCellPhone'];
					


					$param = [
						'managerId',
						'prevAddress',
						'newAddress', 
						'prevOrderInfo',
						'newOrderInfo', 
						'idxApply',
					];
					
					$bind = [
						'sssssi',
						$managerId, 
						$prevAddr,
						$newAddr,
						$prevOrderInfo,
						$newOrderInfo,
						$in['idxApply'],
					];
					
					$db->set_insert_db("wm_subAddressChangeLog", $param, $bind, "y");

					$this->Chk($in['idxApply']);//2022.05.30민트웹 추가
					

					/*추가상품추가,변경 시작*/
					$addGoodsNo=[];
					$addGoodsCnt=[];
					foreach($in['addGoodsNo'] as $k=>$t){
						$addGoodsNo[]=$t;
						if(empty($in['addGoodsCnt'][$t]))
							$in['addGoodsCnt'][$t]=1;

						$addGoodsCnt[]=$in['addGoodsCnt'][$t];
					
					}
					
					//=>$wm_subSchedulesGoods_idx=[];
					if(count($addGoodsNo)>0){
						$add_goodsNo=addslashes(json_encode($addGoodsNo,JSON_UNESCAPED_UNICODE));
						$add_goodsCnt=addslashes(json_encode($addGoodsCnt,JSON_UNESCAPED_UNICODE));
						
						$db->query("update wm_subApplyGoods set addGoodsNo='$add_goodsNo',addGoodsCnt='$add_goodsCnt' where idxApply='{$in['idxApply']}'");

						$sql="select b.idx from wm_subSchedules a INNER JOIN wm_subSchedulesGoods b ON a.idx=b.idxSchedule where a.idxApply='{$in['idxApply']}' and a.status='ready'";
						
						$rows = $db->query_fetch($sql);

						

						foreach($rows as $rk =>$rt){
							$upSQL="update wm_subSchedulesGoods set addGoodsNo='{$add_goodsNo}' ,addGoodsCnt='{$add_goodsCnt}' where idx='{$rt['idx']}'";
							
							$db->query($upSQL);

							$wm_subSchedulesGoods_idx[]=$rt['idx'];
						}


					}else{
						$add_goodsNo='';
						$add_goodsCnt='';


						$db->query("update wm_subApplyGoods set addGoodsNo='$add_goodsNo',addGoodsCnt='$add_goodsCnt' where idxApply='{$in['idxApply']}'");

						$sql="select b.idx from wm_subSchedules a INNER JOIN wm_subSchedulesGoods b ON a.idx=b.idxSchedule where a.idxApply='{$in['idxApply']}' and a.status='ready'";
						
						$rows = $db->query_fetch($sql);

						foreach($rows as $rk =>$rt){
							$upSQL="update wm_subSchedulesGoods set addGoodsNo='{$add_goodsNo}' ,addGoodsCnt='{$add_goodsCnt}' where idx='{$rt['idx']}'";
							$db->query($upSQL);
							$wm_subSchedulesGoods_idx[]=$rt['idx'];
						}
						
					}
					/*추가상품추가,변경 종료*/
					

					$sql="select * from wm_subApplyGoods where idxApply=?";
					$row=$db->query_fetch($sql,['i',$in['idxApply']]);
					$new_wm_subApplyGoods =$row;

					//if(\Request::getRemoteAddress()=="112.146.205.124"){
						$different_data=[];
						foreach($pre_wm_subApplyGoods as $wkk =>$wtt){

							
							foreach($wtt as $wk=>$wt){
								
								if($wt!=$new_wm_subApplyGoods[$wkk][$wk] && $wk!='modDt' && $wk!="regDt" && $wk!='idx'){
										$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".$wt."에서".$new_wm_subApplyGoods[$wkk][$wk]."로 변경되었습니다.";
								}
							}
						}

						if(count($different_data)>0){

							$after_content=implode("=||=",$different_data);
							
							$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
						}
						
					//}


					if(count($wm_subSchedulesGoods_idx)>0){

						$in_idxSchedule=" idx IN ('".implode("','",$wm_subSchedulesGoods_idx)."')";
						$sql="select * from wm_subSchedulesGoods where ".$in_idxSchedule;
						$row=$db->query_fetch($sql);
						$new_wm_subSchedulesGoods =$row;

						//if(\Request::getRemoteAddress()=="112.146.205.124"){
							$different_data=[];
							foreach($pre_wm_subSchedulesGoods as $wkk=>$wtt){
								
								foreach($wtt as $wk =>$wt){

									//$wt=str_replace(",","",str_replace('"]','',str_replace('["','',$wt)));
									//$new_wm_subSchedulesGoods[$wkk][$wk]=str_replace(",","",str_replace('"]','',str_replace('["','',$new_wm_subSchedulesGoods[$wkk][$wk])));

									$wt=str_replace(']','',str_replace('[','',str_replace('"','',stripslashes($wt))));
									$new_wm_subSchedulesGoods[$wkk][$wk]=str_replace(']','',str_replace('[','',str_replace('"','',stripslashes($new_wm_subSchedulesGoods[$wkk][$wk]))));

									if(!empty($new_wm_subSchedulesGoods[$wkk][$wk])){
									if($wt != $new_wm_subSchedulesGoods[$wkk][$wk] && $wk!="modDt" && $wk!="regDt" && $wk != "idx"){
										$different_data[]="정기결제스케줄번호:".$wtt['idxSchedule']."의 ".$wk."관련값".$wt."에서".$new_wm_subSchedulesGoods[$wkk][$wk]."로 변경되었습니다.";
									}
									}
								}
							}

							if(count($different_data)>0){

								$after_content=addslashes(implode("=||=",$different_data));
								
								$db->query("insert into wm_user_change_log set  idxApply='{$in['idxApply']}',before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
							}
						//}

						
					}


					//if(\Request::getRemoteAddress()=="182.216.219.157"){
					$change_addGoodsNo = [];
					if(count($in['change_chk'])>0){
						
						$strSQL			= "select * from wm_subSchedules where idxApply=? and (status='ready' or status='pause')";
						$schedule_rows  = $db->query_fetch($strSQL,['i',$in['idxApply']]);
					
						
						foreach($in['change_chk'] as $key => $t){
							
							$goodsNo			= $t;
							$goodsCnt			= $in['change_goodsCnt'][$t];
							if(empty($goodsCnt))
								$goodsCnt=1;
							
							$optionSno			= $in['change_optionSno'][$t];
							$change_addGoodsNo	= "";
							$change_addGoodsCnt	= "";
							
							if(count($in['change_addGoodsNo'][$t])>0){
								$change_addGoodsNo = json_encode($in['change_addGoodsNo'][$t],JSON_UNESCAPED_UNICODE);
								
								foreach($in['change_addGoodsNo'][$t] as $skey => $st){
								
									if(empty($in['change_addGoodsCnt'][$t][$skey]))
										$in['change_addGoodsCnt'][$t][$skey]=1;
										
										
								}
								$change_addGoodsCnt = json_encode($in['change_addGoodsCnt'][$t],JSON_UNESCAPED_UNICODE);
							}
							
							
							if($key == 0){
								$strSQL				= "select * from wm_subApplyGoods where idxApply=?";
								$applyGoodsRow		= $db->query_fetch($strSQL,['i',$in['idxApply']],false);
								
								$apply_log_data = "정기결제번호".$in['idxApply']."신청건의 결제 상품정보가 상품번호:".$applyGoodsRow['goodsNo'].",수량:".$applyGoodsRow['goodsCnt'].",옵션번호:".$applyGoodsRow['optionSno'].",추가상품번호".$applyGoodsRow['addGoocsNo'].",추가상품수량:".$applyGoodsRow['addGoodsCnt']." 에서 ";

								
								$strSQL				= "update wm_subApplyGoods set goodsNo=?,optionSno=?,goodsCnt=?,addGoodsNo=?,addGoodsCnt=?,modDt=sysdate() where idxApply=?";
								$db->bind_query($strSQL,['iiissi',$goodsNo,$optionSno,$goodsCnt,$change_addGoodsNo,$change_addGoodsCnt,$in['idxApply']]);
								
								$apply_log_data.= " 변경 상품번호:".$goodsNo.",수량:".$goodsCnt.",옵션번호:".$optionSno.",추가상품번호".$change_addGoodsNo.",추가상품수량:".$change_addGoodsCnt."로 변경되었습니다.";
								
								$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$apply_log_data}',memNo='{$scheduleGoodsRow['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
								

							}

							foreach($schedule_rows as $schedule_key => $schedule_val){
								
								$qry				= "select * from wm_subSchedulesGoods where idxSchedule=?";
								$scheduleGoodsRow	= $db->query_fetch($qry,['i',$schedule_val['idx']],false);
								
								
								
								$log_data = "정기결제번호".$in['idxApply']."신청건의 스케줄번호 ".$schedule_val['idx']." 에 해당하는 결제 상품정보가 상품번호:".$scheduleGoodsRow['goodsNo'].",수량:".$scheduleGoodsRow['goodsCnt'].",옵션번호:".$scheduleGoodsRow['optionSno'].",추가상품번호".$scheduleGoodsRow['addGoocsNo'].",추가상품수량:".$scheduleGoodsRow['addGoodsCnt']." 에서 ";
								
								$qry = "update wm_subSchedulesGoods set goodsNo=?,optionSno=?,goodsCnt=?,addGoodsNo='{$change_addGoodsNo}',addGoodsCnt='{$change_addGoodsCnt}' where idxSchedule=?";
								
								//echo $qry;
								$db->bind_query($qry,['iiii',$goodsNo,$optionSno,$goodsCnt,$schedule_val['idx']]);
								
								$log_data.= " 변경 상품번호:".$goodsNo.",수량:".$goodsCnt.",옵션번호:".$optionSno.",추가상품번호".$change_addGoodsNo.",추가상품수량:".$change_addGoodsCnt."로 변경되었습니다.";
								//gd_debug($log_data);
								$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$log_data}',memNo='{$scheduleGoodsRow['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
								
								
								
							}
						}
					}

					// 2024-09-25 wg-eric 배송주기 바꾸면 발송예정일도 변경
					if(($new_wm_subApplyInfo['payMethodFl'] != $pre_wm_subApplyInfo['payMethodFl']) || ($pre_wm_subApplyInfo['deliveryDayPeriod'] && $new_wm_subApplyInfo['deliveryDayPeriod'] != $pre_wm_subApplyInfo['deliveryDayPeriod']) || ($pre_wm_subApplyInfo['deliveryPeriod'] && $new_wm_subApplyInfo['deliveryPeriod'] != $pre_wm_subApplyInfo['deliveryPeriod'])) {
						$subCfg = $subscription->getCfg();
						$strSQL			= "
							select * from wm_subSchedules 
							where idxApply=?
							AND status = 'paid'
							ORDER BY deliveryStamp DESC
							LIMIT 1
						";
						$scheduleResult  = $db->query_fetch($strSQL,['i',$in['idxApply']], false);
						if($scheduleResult) {
							$scheduleInfo = $subscription->getApplyInfo($in['idxApply']);

							if($scheduleInfo['payMethodFl'] == 'dayPeriod') {
								$subscription->schedule_set_date($scheduleResult['idx'], $in['idxApply'], null, 'scheduler');
							} else {
								$filteredSchedules = array_filter($scheduleInfo['schedules'], function($schedule) use ($scheduleResult) {
									return $schedule['idx'] === $scheduleResult['idx'];
								});
								if($filteredSchedules) {
									$filteredSchedules = reset($filteredSchedules);
									$realPayStamp = strtotime(date('Y-m-d 00:00:00', $filteredSchedules['settleInfo']['realPayStamp']));
									$realDeliveryStamp = $realPayStamp + (60 * 60 * 24 * $subCfg['payDayBeforeDelivery']);
								}
								if($realDeliveryStamp) {
									$period = $scheduleInfo['deliveryPeriod'][0];
									$periodUnit = $scheduleInfo['deliveryPeriod'][1]?$scheduleInfo['deliveryPeriod'][1]:"week";

									for($i = 1; $i <= 4; $i++) {
										$priod = $period * $i;
										$prevPriod = $period * ($i - 1);
										$str = "+{$priod} {$periodUnit}";
										$prevStr = "+{$prevPriod} {$periodUnit}";

										$tmpStamp = strtotime($str, $realDeliveryStamp);

										if(strtotime(date('Y-m-d')) + (60 * 60 * 24 * $subCfg['payDayBeforeDelivery']) < $tmpStamp) {
											if($i <= 1) {
												$realDeliveryStamp2 = $realDeliveryStamp;
											} else {
												$realDeliveryStamp2 = strtotime($prevStr, $realDeliveryStamp);
											}

											break;
										}
									}
									
									if($realDeliveryStamp2) {
										$subscription->schedule_set_date($scheduleResult['idx'], $in['idxApply'], $realDeliveryStamp2, 'scheduler');
									} else {
										$subscription->schedule_set_date($scheduleResult['idx'], $in['idxApply'], null, 'scheduler');
									}
								} else {
									$subscription->schedule_set_date($scheduleResult['idx'], $in['idxApply'], null, 'scheduler');
								}
							}
						}
					}


					return $this->layer("변경되었습니다.");
					break;
				/* 정기배송 신청/배송 변경 E */
				/* 신청목록 삭제 */
				case "delete_apply_list" : 

					$in_idx="idxApply IN('".implode("','",$in['idx'])."')";
					$sql="select * from wm_subSchedules where ".$in_idx;
						
					$row=$db->query_fetch($sql);

					$memNo=[];
					foreach($row as $k =>$t){
						$memNo[]=$t['memNo'];
					}

					if (empty($in['idx']))
						throw new AlertOnlyException("삭제할 신청건을 선택하세요.");
					foreach ($in['idx'] as $idx) {
						$subscription->deleteScheduleAll($idx);
					}
					
					$new_wm_subSchedules ="회원번호:'".implode(",",$memNo)."' 건의 정기결제 신청번호:'".implode("','",$in['idx'])."' 건이 삭제되었습니다.";
					$db->query("insert into wm_user_change_log set before_content='',after_content='{$new_wm_subSchedules}',memNo='',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");

					return $this->layer("삭제되었습니다.");
					break;
				/* 수동 결제 */
				case "manual_pay" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("잘못된 접근입니다.");

					$kakaoPay = new \Component\Subscription\KakaoPay();
					
					$method=$subscription->orderType("",$in['idx']);


					if($method=="ini"){
						$subscription->schedule=1;
						$subscription->pay($in['idx'], null, true);

					}else if($method=="kakao"){
					    $kakaoPay->schedule=1;
						$kakaoPay->kpay($in['idx']);

					}else if($method=="naver"){

					}					
					
					$sql="select * from wm_subSchedules where idx=?";
					$pre_wm_subSchedules=$db->query_fetch($sql,['i',$in['idx']]);

					// 2024-07-16 wg-eric 결제예정일이 지난 경우에 회차 주기맞춰 자동으로 변경 - start
					if($pre_wm_subSchedules[0]['deliveryStamp'] < strtotime(date('Y-m-d', strtotime('+1 days')))) {
						$deliveryStamp = strtotime(date('Y-m-d', strtotime('+1 days')));
					
						$param = [
							'deliveryStamp = ?',
						];
						$bind = [
							'ii', 
							$deliveryStamp,
							$in['idx'],
						];
						$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);

						$sql="select * from wm_subSchedules where idx=?";
						$schduleRow = $db->query_fetch($sql,['i',$in['idx']]);
						$subscription->schedule_set_date($in['idx'], $schduleRow[0]['idxApply'],$schduleRow[0]['deliveryStamp'],"change_date");

						$sql="
							select * from wm_subSchedules 
							where idxApply = ".$schduleRow[0]['idxApply']."
							AND idx > ".$in['idx']."
						";
						$new_wm_subSchedules = $db->slave()->query_fetch($sql);

						$different_data=[];

						$idxApply=0;
						foreach($pre_wm_subSchedules as $wkk =>$wtt){
							$idxApply=$wtt['idxApply'];
							foreach($wtt as $wk =>$wt){
								if($wt!=$new_wm_subSchedules[$wkk][$wk] && $wk=='deliveryStamp'){
										$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".date("Y-m-d",$wt)."에서".date("Y-m-d",$new_wm_subSchedules[$wkk][$wk])."로 변경되었습니다.";
								}
							}
						}

						if(count($different_data)>0){
							$after_content=implode("=||=",$different_data);
							
							$db->query("insert into wm_user_change_log set idxApply='{$idxApply}', before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
						}
					}
					// 2024-07-16 wg-eric 결제예정일이 지난 경우에 회차 주기맞춰 자동으로 변경 - end

					return $this->layer("수동 결제 되었습니다.");
					break;
				/* 주문취소 */
				case "cancel" : 
					if (empty($in['orderNo']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					$method = $subscription->orderType($in['orderNo']);	
					
					if($method=="ini"){
						$subscription->cancel($in['orderNo'], true, null, true);
					}else if($method=="kakao"){
						$kakaoPay = new \Component\Subscription\KakaoPay();
						$kakaoPay->kakao_cancel($in['orderNo'],true,null,true);

					}else if($method=="naver"){
					
					}
					return $this->layer("결제 취소 되었습니다.");
					break;
				/* 카드 변경 */
				case "change_card" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (empty($in['idx_card']))
						throw new AlertOnlyException("변경할 카드를 선택하세요.");
					

					$sql="select a.*,b.cardNm from wm_subApplyInfo a LEFT JOIN wm_subCards b ON a.idxCard=b.idx where a.idx=?";
					$row=$db->query_fetch($sql,['i',$in['idx']],false);
					$memNo=$row['memNo'];

					$pre_wm_subSchedules =$row;


					$param = [
						'idxCard = ?',
					];
					
					$bind = [
						'ii',
						$in['idx_card'],
						$in['idx'],
					];
					
					$affectedRows = $db->set_update_db("wm_subApplyInfo", $param, "idx = ?", $bind);
					if ($affectedRows < 0) {
						throw new AlertOnlyException("변경에 실패하였습니다.");
					}
					
					$sql="select a.*,b.cardNm from wm_subApplyInfo a LEFT JOIN wm_subCards b ON a.idxCard=b.idx where a.idx=?";
					$rows=$db->query_fetch($sql,['i',$in['idx']],false);
					$new_wm_subSchedules =$rows;

					$different_data=[];

					
					foreach($pre_wm_subSchedules as $wk=>$wt){

						if($wt!=$new_wm_subSchedules[$wk] && $wk!='modDt' && $wk!="regDt"){
							$different_data[]="정기결제신청번호:".$in['idx']."건의 정기결제 카드가 ".$wt." 에서 ".$new_wm_subSchedules[$wk]."로 변경되었습니다.";
						}
					}
					
					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);						
						$db->query("insert into wm_user_change_log set idxApply='{$in['idx']}',before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					return $this->layer("변경되었습니다.");
					break;
				/* 스케줄 추가 */
				case "add_schedule" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (empty($in['deliveryEa']))
						throw new AlertOnlyException("추가할 회차를 선택하세요.");
					
					$sql="select count(idx) as cnt from wm_subSchedules where idxApply=?";
					$rows=$db->query_fetch($sql,['i',$in['idx']],false);
					//$memNo=$rows[0]['memNo'];
					$pre_wm_subSchedules =$rows;


					$subscription->addSchedule($in['idx'], $in['deliveryEa'], true);
					

					$sql="select count(idx) as cnt from wm_subSchedules where idxApply=?";
					$rows=$db->query_fetch($sql,['i',$in['idx']],false);
					$new_wm_subSchedules =$rows;
					
					$after_content="정기결제 신청번호:".$in['idx']."건의 회차가".$pre_wm_subSchedules['cnt']."회 에서".$new_wm_subSchedules['cnt']."회로 변경되었습니다.";
					$db->query("insert into wm_user_change_log set idxApply='{$in['idx']}',before_content='',after_content='{$after_content}',memNo='{$memNo}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");

					return $this->js("alert('추가되었습니다.');parent.opener.location.reload();parent.close();");
					break;
				/* 배송일 변경 */
				case "change_delivery_date" :
					if (empty($in['idx']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (empty($in['deliveryDate']))
						throw new AlertOnlyException("배송일을 선택하세요.");
					
					$sql="select * from wm_subSchedules where idx=?";
					$rows=$db->query_fetch($sql,['i',$in['idx']]);
					$memNo=$rows[0]['memNo'];
					$pre_wm_subSchedules =$rows;


					$deliveryStamp = $in['deliveryDate']?strtotime($in['deliveryDate']):0;
					
					$param = [
						'deliveryStamp = ?',
					];
					
					$bind = [
						'ii', 
						$deliveryStamp,
						$in['idx'],
					];
				
					$affectedRows = $db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
					if ($affectedRows < 0) {
						throw new AlertOnlyException("변경에 실패하였습니다.");
					}
					
					$sql="select * from wm_subSchedules where idx=?";
					$rows=$db->query_fetch($sql,['i',$in['idx']]);
					$new_wm_subSchedules =$rows;
					
					$different_data=[];
					$idxApply=0;
					foreach($pre_wm_subSchedules as $wkk =>$wtt){
						$idxApply=$wtt['idxApply'];
						foreach($wtt as $wk =>$wt){
							if($wt!=$new_wm_subSchedules[$wkk][$wk] && $wk=='deliveryStamp'){
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".date("Y-m-d",$wt)."에서".date("Y-m-d",$new_wm_subSchedules[$wkk][$wk])."로 변경되었습니다.";
							}
						}
					}

					// 2024-07-15 wg-eric 재정기 결제일 변경 이후 회차 주기맞춰 자동으로 변경 - start
					$subscription->schedule_set_date($in['idx'], $idxApply, $deliveryStamp, 'scheduler');
					/*
					$sql = "SELECT goodsNo FROM wm_subApplyGoods WHERE idxApply = ? ORDER BY idx";
					$goodsList = $db->query_fetch($sql, ["i", $idxApply]);
					$goods = array_column($goodsList, "goodsNo");
					// 정기정보 가져오기
					$Subscription = \App::load('\\Component\\Subscription\\Subscription');
					$info = $Subscription->getApplyInfo($idxApply);
					if($info['payMethodFl'] == 'dayPeriod') {
					} else {
						$cfg = $Subscription->getCfg($goods);

						$sql="
							select * from wm_subSchedules 
							where idxApply = ".$idxApply."
							AND idx > ".$in['idx']."
						";
						$afterList = $db->slave()->query_fetch($sql);

						$schedule = new \Component\Subscription\Schedule();
						$firstStamp = $schedule->getFirstDay();

						$no = 1;
						foreach($afterList as $key => $val) {
							// 결제완료면 넘어가기
							if($val['status'] == 'paid') {
								continue;
							}

							$str = "+".$info['deliveryPeriod'][0] * $no. " " .$info['deliveryPeriod'][1];
							$newStamp = strtotime($str, $new_wm_subSchedules[0]['deliveryStamp']);

							$yoil = date("w", $newStamp);
				
							$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
							$row = $db->query_fetch($sql, ["i", $newStamp], false);
							if ($newStamp < $firstStamp || $row['isHoliday']){

								while(1){
									$newStamp = strtotime("+1 day", $newStamp);
									$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
									$row = $db->query_fetch($sql, ["i", $newStamp], false);
									if (!$row['isHoliday']){
										break;
									}
								}
								//continue;
							}
							
							$yoil = date("w", $newStamp);
							if ($cfg['deliveryYoils'] && !in_array($yoil, $cfg['deliveryYoils'])) {
								while(1) {
									$newStamp = strtotime("+1 day", $newStamp);
									$yoil = date("w", $newStamp);

									if(in_array($yoil, $cfg['deliveryYoils'])){
										break;
									}
								}
								//continue;
							}

							$param = [
								'deliveryStamp = ?',
							];
							$bind = [
								'ii', 
								$newStamp,
								$val['idx'],
							];
							$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);

							$no++;
						}
					}
					*/
					// 2024-07-15 wg-eric 재정기 결제일 변경 이후 회차 주기맞춰 자동으로 변경 - end

					if(count($different_data)>0){
						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='{$idxApply}', before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					return $this->layer("변경되었습니다.");
					break;
				/* 배송 주기 / 배송 주소 변경 */
				case "change_address" : 
					if (empty($in['idx']) || empty($in['idxApply']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (empty($in['receiverName']))
						throw new AlertOnlyException("받는분 이름을 입력하세요.");
					
					if (empty($in['receiverCellPhone']))
						throw new AlertOnlyException("휴대전화번호를 입력하세요.");
					
					if (empty($in['receiverZonecode']) || empty($in['receiverAddress']) || empty($in['receiverAddressSub']))
						throw new AlertOnlyException("배송주소를 입력하세요.");
					
					/* 정기결제 신청 정보 추출 */
					$list = $subscription->getSchedules($in['idxApply']);
					if (!$list)
						throw new AlertOnlyException("신청정보가 존재하지 않습니다.");
					
					
					$sql="select * from wm_subApplyInfo where idx=?";
					$rows=$db->query_fetch($sql,['i',$in['idxApply']],false);
					$memNo=$rows[0]['memNo'];
					$pre_wm_subSchedules =$rows;

					$param = [
						'deliveryPeriod = ?',
						'autoExtend = ?',
					];
					
					$bind = [
						'sii', 
						$in['period'],
						$in['autoExtend']?1:0,
						$in['idxApply'],
					];
					
					$db->set_update_db("wm_subApplyInfo", $param, "idx = ?", $bind);
					
					$sql="select * from wm_subApplyInfo where idx=?";
					$rows=$db->query_fetch($sql,['i',$in['idxApply']],false);
					$new_wm_subSchedules =$rows;
					
					$different_data=[];
					foreach($pre_wm_subSchedules as $wk =>$wt){
						
						if($wt!=$new_wm_subSchedules[$wk] && $wk!='modDt' && $wk!="regDt"){
								$different_data[]="정기결제신청번호:".$in['idxApply']."의 ".$wk."관련값".$wt."에서".$new_wm_subSchedules[$wk]."로 변경되었습니다.";
						}
					}					
					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$after_content}',memNo='{$list[0]['memNo']}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					if ($list) {
						$param = [
							'receiverName = ?',
							'receiverPhone = ?',
							'receiverCellPhone = ?',
							'receiverZonecode = ?',
							'receiverZipcode = ?',
							'receiverAddress = ?',
							'receiverAddressSub = ?',
						];
			
						foreach ($list as $li) {
							if (!$li['orderNo'])
								continue;
							
							$bind = [
								'sssssssi',
								$in['receiverName'],
								$in['receiverPhone'],
								$in['receiverCellPhone'],
								$in['receiverZonecode'],
								$in['receiverZipcode'],
								$in['receiverAddress'],
								$in['receiverAddressSub'],
								$li['idx'],
							];
							
							$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
						} // endforeach 
						
						
						$bind = [
							'sssssssi',
							$in['receiverName'],
							$in['receiverPhone'],
							$in['receiverCellPhone'],
							$in['receiverZonecode'],
							$in['receiverZipcode'],
							$in['receiverAddress'],
							$in['receiverAddressSub'],
							$in['idxApply'],
						];
						$affectedRows = $db->set_update_db("wm_subDeliveryInfo", $param, "idxApply = ?", $bind);
					} // endif 
					
					if ($affectedRows < 0) {
						throw new AlertOnlyException("변경에 실패하였습니다.");
					}
					
					return $this->layer("변경되었습니다.");
					break;
				/* 일괄 처리 */
				case "batch" : 
					
					$subscriptionBatch = App::load(\Component\Subscription\SubscriptionBatch::class);
					if ($in['sub'] == 'sms') {
						$subscriptionBatch->getBatchSmsList($in['date'], true);
					} else {
						$subscriptionBatch->getBatchPayList($in['date'], true);
					}
					
					return $this->layer("처리되었습니다.");
					break;
				/* 결제 카드 정보 변경 S */
				case "update_card_info" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					
					$param = [
						'memo = ?',
					];
					$bind = [
						's',
						$in['memo'],
					];
					if ($in['password']) {
						if (!is_numeric($in['password']))
							throw new AlertOnlyException("비밀번호는 숫자로 입력하세요.");
                    
						if (strlen($in['password']) != 6)
							throw new AlertOnlyException("비밀번호는 6자 숫자로만 입력하세요.");
                    
						$hash = $subscription->getPasswordHash($in['password']);
						$param[] = "password = ?";
						$db->bind_param_push($bind, "s", $hash);
					}
					$db->bind_param_push($bind, "i", $in['idx']);
					
					$affectedRows = $db->set_update_db("wm_subCards", $param, "idx = ?", $bind);
					if ($affectedRows <= 0)
						throw new AlertOnlyException("수정에 실패하였습니다.");
					
					return $this->layer("수정되었습니다.");
					/* 결제 카드 정보 변경 E */
					break;
				/* 결제카드 목록 수정 S */
				case "update_card_list" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("수정할 결제카드를 선택하세요.");
					
					foreach ($in['idx'] as $idx) {
						$memo = $in['memo'][$idx];
						$password = $in['password'][$idx];
						
						$param = [
							'memo = ?',
						];
						$bind = [
							's',
							$memo,
						];
						
						if ($password) {
							$hash = $subscription->getPasswordHash($password);
							$param[] = "password = ?";
							$db->bind_param_push($bind, "s", $hash);
						}
						
						$db->bind_param_push($bind, "i", $idx);
						$db->set_update_db("wm_subCards", $param, "idx = ?", $bind);
					}
					
					return $this->layer("수정되었습니다.");
					break;
				/* 결제카드 목록 수정 E */
				/* 결제카드 목록 삭제 S */
				case "delete_card_list" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("삭제할 결제카드를 선택하세요.");
					
					foreach ($in['idx'] as $idx) {
						$db->set_delete_db("wm_subCards", "idx = ?", ["i", $idx]);
					}
					
					return $this->layer("삭제되었습니다.");
					break;
				/* 결제카드 목록 삭제 E */
				/* 정기배송 스케줄 수정 S */
				case "update_schedule_list" : 
					if (empty($in['idx'])) {
						throw new AlertOnlyException("수정할 결제 신청건을 선택하세요.");
					}
					
					$in_idx="idx IN('".implode("','",$in['idx'])."')";
					$sql="select * from wm_subSchedules where ".$in_idx;
					$rows=$db->query_fetch($sql);
					$memNo=$rows[0]['memNo'];
					$pre_wm_subSchedules =$rows;

					foreach ($in['idx'] as $idx) {
						$param = [
							'status = ?',
						];
						
						$bind = [
							'si',
							$in['status'][$idx],
							$idx,
						];
						
						$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);

					}
					


					$chk_key=0;
					
					foreach ($in['idx'] as $key=>$idx) {
						unset($arrBind);

						$sql = "SELECT idxApply FROM wm_subSchedules WHERE idx = ?";
						$applyInfo = $db->query_fetch($sql,['i',$idx],false);
						
						if($in['status'][$idx]=="pause"){


							$subscription->pauseAll($applyInfo['idxApply'],false,$in['pause_period'],$idx);

						
						}else if($key>$chk_key){
							$subscription->pauseAll($applyInfo['idxApply'],false,$in['pause_period'],$idx);
						}
					}
					
					$sql="select * from wm_subSchedules where ".$in_idx;
					$rows=$db->query_fetch($sql);
					$new_wm_subSchedules =$rows;


					$different_data=[];
					$idxApply=0;
					foreach($pre_wm_subSchedules as $wkk =>$wtt){
						$idxApply=$wtt['idxApply'];
						foreach($wtt as $wk =>$wt){
							if($wt!=$new_wm_subSchedules[$wkk][$wk] && $wk!='modDt' && $wk!="regDt"){
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".$wt."에서".$new_wm_subSchedules[$wkk][$wk]."로 변경되었습니다.";
							}
						}
					}
					
					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='{$idxApply}',before_content='',after_content='{$after_content}',memNo='{$memNo}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");
					}
					return $this->layer("수정되었습니다.");
					break;
				/* 정기배송 스케줄 수정 E */
				/* 정기배송 스케줄 삭제 S */
				case "delete_schedule_list" : 
					if (empty($in['idx'])) {
						throw new AlertOnlyException("삭제할 결제 신청건을 선택하세요.");
					}
					
					$idxApply=0;//2022.05.30민트웹 추가
					
					//$In_idx="idx IN('".implode("','",$in['idx'])."')";
					//$sql="select * from wm_subSchedules where ".$in_idx;
					//$rows=$db->query_fetch($sql);
					//$memNo=$rows[0]['memNo'];

					
					$memNo=0;
					foreach ($in['idx'] as $idx) {

						//2022.05.30민트웹 추가 시작
						$row = $db->fetch("select idxApply from wm_subSchedules where idx='{$idx}'");

						$idxApply=$row['idxApply'];
						$memNo=$row['memNo'];
						//2022.05.30민트웹 추가 종료

						$db->set_delete_db("wm_subSchedules", "idx = ?", ["i", $idx]);
						$db->set_delete_db("wm_subSchedulesGoods", "idxSchedule = ?", ["i", $idx]);

					}


					//2022.05.30민트웹 추가 시작
					$this->Chk($idxApply);
					//2022.05.30민트웹 추가 종료
					
					$new_wm_subSchedules ="신청번호 ".$idxApply." 건의 스케줄 번호".implode("','",$in['idx'])."건이 삭제되었습니다. ";

					$db->query("insert into wm_user_change_log set idxApply='{$idxApply}',before_content='',after_content='{$new_wm_subSchedules}',memNo='{$memNo}',changeUser='{$managerId}',regDt=sysdate(),mode='{$in['mode']}'");


					return $this->layer("삭제되었습니다.");
					break;
				/* 정기배송 스케줄 삭제 E */
				/* 관리자 메모  S */
				case "update_admin_memo" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					$param = [
						'adminMemo = ?',
					];
					
					$bind = [
						'si',
						$in['adminMemo'],
						$in['idx'],
					];
					
					$affectedRows = $db->set_update_db("wm_subApplyInfo", $param, "idx = ?", $bind);
					if ($affectedRows <= 0)
						throw new AlertOnlyException("저장에 실패하였습니다.");
					
					return $this->layer("저장되었습니다.");
					break;
				/* 관리자 메모 E */
				/* 정기배송 신청 목록 엑실 다운로드 S */
				case "dnXls_apply_list" : 
					$get = $in;
					/* 배송일 검색 START */
					if ($get['deliveryDate']) {
						$stamp = strtotime($get['deliveryDate']);
						$sql = "SELECT idxApply as idx FROM wm_subSchedules WHERE deliveryStamp = ? GROUP BY idxApply";
						$list = $db->query_fetch($sql, ["i", $stamp]);
						if ($list) {
							$idxApplies = array_column($list, "idx");
							$subscription->searchIdxes = $idxApplies;
						} else {
							$result = [];
						}
					}
					/* 배송일 검색 START */
					
					/* 상품명 검색 S */
					if ($get['sopt'] == 'goodsNm' && $get['skey']) {
						$sql = "SELECT b.idxApply as idx FROM wm_subSchedulesGoods AS a 
									INNER JOIN wm_subSchedules AS b ON a.idxSchedule = b.idx
									INNER JOIN " . DB_GOODS . " AS g ON a.goodsNo = g.goodsNo 
								WHERE goodsNm LIKE ? GROUP BY b.idxApply";
						$list = $db->query_fetch($sql, ["s", "%".$get['skey']."%"]);
						if ($list) {
							$idxApplies = array_column($list, "idx");
							if ($subscription->searchIdxes) {
								$subscription->searchIdxes = array_merge($subscription->searchIdxes, $idxApplies);
							} else {
								$subscription->searchIdxes = $idxApplies;
							}
						}
						
						unset($get['sopt']);
						unset($get['skey']);
					}
					/* 상품명 검색 E */
					$subscription->adminList = true;
					$result = $subscription->setAdmin(true)->getApplyList(null, [], true, $get['page'], 10000000);

					$now=time();
					
					if ($result && $result['list']) {
						foreach ($result['list'] as $k => $v) {
							$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules AS a 
											INNER JOIN " . DB_ORDER . " AS o ON a.orderNo = o.orderNo 
										WHERE a.idxApply = ? AND a.status='paid' AND SUBSTR(o.orderStatus, 1, 1) IN ('o','p','g','d','s')";
							$row = $db->query_fetch($sql, ["i", $v['idx']], false);
							$v['orderCnt'] = gd_isset($row['cnt'], 0);
							
							$sql = "SELECT g.goodsNo, g.goodsNm, a.goodsCnt FROM wm_subApplyGoods AS a 
											INNER JOIN " . DB_GOODS . " AS g ON a.goodsNo = g.goodsNo WHERE a.idxApply = ? LIMIT 0, 1";
											
							$row = $db->query_fetch($sql, ["i", $v['idx']], false);
							$v = $row?array_merge($v, $row):$v;
							
							$sql = "SELECT deliveryStamp FROM wm_subSchedules WHERE idxApply = ? AND deliveryStamp > 0 ORDER BY deliveryStamp LIMIT 0, 1";
							$row = $db->query_fetch($sql, ["i", $v['idx']], false);
							$v['firstDeliveryStamp'] = gd_isset($row['deliveryStamp'], 0);
								$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='ready' AND idxApply = ?";
							
							if ($v['autoExtend'])  {
								$v['isAvailable'] = 1;
							} else {
								$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='ready' AND idxApply = ? and deliveryStamp>'$now'";
								$row = $db->query_fetch($sql, ["i", $v['idx']], false);
								$v['isAvailable'] = $row['cnt']?1:0;


								$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='pause' AND idxApply = ? and deliveryStamp>'$now'";
								$row = $db->query_fetch($sql, ["i", $v['idx']], false);
								$v['PauseisAvailable'] = $row['cnt']?1:0;
							}
							
							$msql = "SELECT ws.*,(select m.sexFl from ".DB_MEMBER." m where m.memNo=ws.memNo) as sexFl FROM wm_subDeliveryInfo ws WHERE ws.idxApply = ?"; 
							$mrow = $db->query_fetch($msql, ["i", $v['idx']], false);
							if ($mrow) {
								$v['memNm'] = gd_isset($v['memNm'], $mrow['orderName']);
								$v['cellPhone'] = gd_isset($v['cellPhone'], $mrow['orderCellPhone']);
								$v['sexFl'] = $mrow['sexFl'];
							}


							//총횟수 시작 2022.06.21 민트웹-중지 또는 환불건 제외
							$tcountOrderStatus=array('p','s','d','o','g');
							$sql = "SELECT ws.*,o.orderNo,substr(o.orderStatus,1,1)as orderStatus FROM wm_subSchedules as ws LEFT JOIN ".DB_ORDER." o ON ws.orderNo=o.orderNo WHERE ws.status<>'stop'AND ws.idxApply = ?";
							$trows = $db->query_fetch($sql, ["i", $v['idx']]);
							$tcount=0;

							
							foreach($trows as $kk =>$tt){
								
								if(!empty($tt['orderNo']) && in_array($tt['orderStatus'],$tcountOrderStatus)!==false){
									$tcount++;
								}else if(empty($tt['orderNo']) && $tt['status']!="stop"){
									$tcount++;
								}
							}
							
							$v['totalSchedule']=$tcount;
							//총횟수 종료 2022.06.21 민트웹 
							
							
							$result['list'][$k] = $v;
						}
					}
						
					$list = gd_isset($result['list'], []);
					$page = $result['page'];
					header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                    header('Content-Disposition: attachment; filename='.date('YmdHi').'.xls');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0,pre-check=0');
                    header('Pragma: public');
                    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
                    echo '<style>td {mso-number-format:"@"}</style>';
					?>
					<table border='1'>
					<thead>
					<tr bgcolor='f8f8f8'>
						<th width='60'>NO</th>
						<th width='80'>신청번호</th>
						<th width='70'>신청일</th>
						<th width='70'>첫 발송일</th>
						<th>상품명</th>
						<th width='40'>수량</th>
						<th width='60'>배송주기</th>
						<th width='90'>아이디</th>
						<th width='90'>주문자명</th>
						<th width='110'>연락처</th>
						<th width='110'>성별</th>
						<th width='80'>신청횟수<br>(초기 신청시)</th>
						<th width='80'>총횟수</th>
						<th width='100'>총배송횟수<br>(배송완료 주문번호 수)</th>
						<th width='70'>자동연장</th>
						<th width='70'>이용현황</th>
					</tr>
					</thead>
					<tbody>
					<?php if (gd_isset($list)) : ?>
					<?php foreach ($list as $li) : 
							$deliveryPeriod = explode("_", $li['deliveryPeriod']);
							
					?>
					<tr>
						<td align='center' nowrap><?=$page->idx--?></td>
						<td align='center' nowrap><?=$li['idx']?></td>
						<td align='center' nowrap><?=date("Y.m.d", strtotime($li['regDt']))?></td>
						<td><?=$li['firstDeliveryStamp']?date("Y.m.d", $li['firstDeliveryStamp']):""?></td>
						<td><?=$li['goodsNm']?></td>
						<td align='center' nowrap><?=number_format($li['goodsCnt'])?></td>
						<td align='center' nowrap>
							<?php if($li['payMethodFl'] == 'period') { ?>
							<?=$deliveryPeriod[0]?>
							<?php
								if($deliveryPeriod[1]=="month")
									echo"달 마다";
								if($deliveryPeriod[1]=="week")
									echo"주 마다";								
								if($deliveryPeriod[1]=="day")
									echo"일 마다";	
							?>
							<?php } else if($li['payMethodFl'] == 'dayPeriod') { ?>
								<?= $li['deliveryDayPeriod'] ?>일에
							<?php } ?>
						</td>
						<td align='center' nowrap>
							<?=$li['memId']?>
						</td>
						<td align='center' nowrap>
							<?=$li['memNm']?>
						</td>
						<td align='center' nowrap><?=$li['cellPhone']?></td>
						<td align='center' nowrap>
							<?php
						
								if($li['sexFl']=='m')
									echo"남자";
								else if($li['sexFl']=='w')
									echo"여자";
							?>
							
						</td>
						
						<td align='center' nowrap><?=$li['deliveryEa']?>회</td>
						<td align='center' nowrap><?=number_format($li['totalSchedule'])?>회</td>
						<td align='center' nowrap><?=$li['orderCnt']?>회</td>
						<td align='center' nowrap>
								
						<?=$li['autoExtend']?"사용":"미사용"?>
						</td>
						<td align='center' nowrap>
								<?//=$li['isAvailable']?"이용중":"종료"?>
							<?php 
							
								if($li['isAvailable']==1)
									echo "이용중";
								else if($li['isAvailable']==0 && $li['PauseisAvailable']==1)
									echo"일시정지";
								else
									echo"종료";
							?>						
						</td>
					</tr>
					<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
				
					<?php
					break;
				/* 정기배송 신청 목록 엑실 다운로드 E */
			}
		} catch (AlertOnlyException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new AlertOnlyException($e->getMessage());
		}
		exit;
	}
	
	//2022.05.30민트웹 추가
	public function Chk($idxApply)
	{
		$subscription = \App::load('\\Component\\Subscription\\Subscription');
        $subCfg = $subscription->getCfg();
		$db = \App::load(\DB::class);

		$applySQL="select * from wm_subApplyInfo where idx=?";
		$applyInfo = $db->query_fetch($applySQL,['i',$idxApply],false);

		$memNo=$applyInfo['memNo'];

		$ChkSQL="select count(idx) as cnt from wm_subSchedules where memNo=? and status='ready'";
		$ChkROW = $db->query_fetch($ChkSQL,['i',$memNo],false);

		if($applyInfo['autoExtend']!=1 && $ChkROW['cnt']<1){
			$db->query("update ".DB_MEMBER." set groupSno ='1' where memNo='$memNo'");
		} else {
			// 2024-07-22 wg-eric 정기결제 회원으로 변경
			$db->query("update ".DB_MEMBER." set groupSno ='{$subCfg['memberGroupSno']}' where memNo='$memNo'");
		}
		
	}
}