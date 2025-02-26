<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;
use Exception;
use Session;
use Component\Subscription\Subscription;

/**
* 정기배송 마이페이지 관련 DB 처리 
* 
* @author webnmobile
*/
class IndbMypageController extends \Controller\Front\Controller
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			$db = App::load(\DB::class);
			//$subscription = App::load(\Component\Subscription\Subscription::class);
			$subscription = new Subscription();
			$subCfg = $subscription->getCfg();

			//$memNo=\Session::get("member.memNo");
			$memNo = Session::get("member.memNo");
			$memId = Session::get("member.memId");
			
			switch ($in['mode']) {
				/* 결제 카드 삭제 */
				case "delete_card" : 
					if (empty($in['idx_card']))
						throw new AlertOnlyException("삭제할 카드를 선택하세요.");
					
					$cardName=[];
					foreach ($in['idx_card'] as $idx) {

						$cardSQL="select cardNm from wm_subCards where idx='$idx'";
						$cardROW=$db->fetch($cardSQL);
						$cardName[]=$cardROW['cardNm'];
					
						$subscription->deleteCard($idx, true);
					}


					if(count($cardName)>0){
						$after_content=implode(",",$cardName)."을/를 삭제하였습니다.";
						$db->query("insert into wm_user_change_log set before_content='',after_content='{$after_content}',memNo='$memNo',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");

					}
					
					return $this->js("alert('삭제 되었습니다.');parent.location.reload();");
					break;
				/* 배송지 및 결제카드 변경 */
				case "update_change_apply" : 


					$strSQL="select * from wm_subApplyInfo where idx='{$in['idxApply']}'";
					$preApplyInfo=$db->fetch($strSQL);

					$strSQL="select * from wm_subApplyGoods where idxApply='{$in['idxApply']}'";
					$Rows=$db->query_fetch($strSQL);
					$preApplyGoods = $Rows;

					$strSQL="select count(idx) as cnt from wm_subSchedules where  idxApply='{$in['idxApply']}'";
					$Rows=$db->fetch($strSQL);
					$preSchedule = $Rows['cnt'];

					if (empty($in['idxApply']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (!gd_is_login())
						throw new Exception("로그인이 필요합니다.");
					
						
					/* 정기결제 신청 정보 추출 */
					$info = $subscription->getApplyInfo($in['idxApply']);

					//$before_content = addslashes(json_encode($info,JSON_UNESCAPED_UNICODE));//log용
					
					if (!$info)
						throw new Exception("신청정보가 존재하지 않습니다.");
					
					if ($info['memNo'] != $memNo)
						throw new Exception("본인 신청건만 변경하실 수 있습니다.");
					
					
					if (empty($in['idxCard']))
						throw new Exception("결제카드를 선택하세요.");
					
					/* 결제 카드 변경 START */
					if ($in['idxCard']) {
						$param = [
							'idxCard = ?',
						];
						
						$bind = [
							'ii',
							$in['idxCard'],
							$in['idxApply'],
						];

						$db->set_update_db("wm_subApplyInfo", $param, "idx = ?", $bind);
					}
					/* 결제 카드 변경 END */
					$param = [
						'autoExtend = ?',
					];
					
					$bind = [
						'ii',
						$in['autoExtend']?1:0,
						$in['idxApply'],
					];
					
					$db->set_update_db("wm_subApplyInfo", $param, "idx = ?", $bind);
					/* 자동연장 변경 S */
					
					/* 자동연장 변경 E */
					
					/* 배송지 변경 START */
					/*202308.28업체요청에의해 변경함
					if ($in['shippingSno']) {
					    
					    $deliverySql="select count(sno) as cnt from ".DB_ORDER_SHIPPING_ADDRESS." where sno='{$in['shippingSno']}'";

					    $deliveryRow = $db->query_fetch($deliverySql);
					    
					    if(!empty($deliveryRow[0]['cnt']))
					        $subscription->changeAddressBySno($in['idxApply'], $in['shippingSno']);
						
					}
					*/
					/* 배송지 변경 END */
					
					
					/* 배송 횟수 추가 START */
					if ($in['deliveryEa'] > 0) {
						$subscription->addSchedule($in['idxApply'], $in['deliveryEa'], true);
					} // endif 
					
					/* 배송 횟수 추가 END */

					/*결제주기 변경시작*/
					if(!empty($in['period'])){
						$db->query("update wm_subApplyInfo set deliveryPeriod='{$in['period']}' where idx='{$in['idxApply']}'");
					}

					/*결제주기 변경종료*/


					/*옵션변경 시작*/
					foreach($in['optionSno'] as $k =>$v){
						
						$db->query("update wm_subApplyGoods set optionSno='$v' where idx='{$k}'");
					}
					/*옵션변경 종료*/


					/*추가상품추가,변경 시작*/
					$addGoodsNo=[];
					$addGoodsCnt=[];
					foreach($in['addGoodsNo'] as $k=>$t){
						$addGoodsNo[]=$t;

						if(empty($in['addGoodsCnt'][$t]))
							$in['addGoodsCnt'][$t]=1;

						$addGoodsCnt[]=$in['addGoodsCnt'][$t];
					
					}

					//gd_debug($addGoodsNo);

					if(count($addGoodsNo)>0){
						$add_goodsNo=addslashes(json_encode($addGoodsNo,JSON_UNESCAPED_UNICODE));
						$add_goodsCnt=addslashes(json_encode($addGoodsCnt,JSON_UNESCAPED_UNICODE));
						$db->query("update wm_subApplyGoods set addGoodsNo='$add_goodsNo',addGoodsCnt='$add_goodsCnt' where idxApply='{$in['idxApply']}'");

						$sql="select b.idx from wm_subSchedules a INNER JOIN wm_subSchedulesGoods b ON a.idx=b.idxSchedule where a.idxApply='{$in['idxApply']}' and a.status='ready'";
						
						$rows = $db->query_fetch($sql);

						foreach($rows as $rk =>$rt){
							$upSQL="update wm_subSchedulesGoods set addGoodsNo='{$add_goodsNo}' ,addGoodsCnt='{$add_goodsCnt}' where idx='{$rt['idx']}'";
							$db->query($upSQL);
						}

						//echo "update wm_subApplyGoods set addGoodsNo='$add_goodsNo',addGoodsCnt='$add_goodsCnt' where idxApply='{$in['idxApply']}'";

					}else{
						$db->query("update wm_subApplyGoods set addGoodsNo='',addGoodsCnt='' where idxApply='{$in['idxApply']}'");

						$sql="select b.idx from wm_subSchedules a INNER JOIN wm_subSchedulesGoods b ON a.idx=b.idxSchedule where a.idxApply='{$in['idxApply']}' and a.status='ready'";
						
						$rows = $db->query_fetch($sql);

						foreach($rows as $rk =>$rt){
							$upSQL="update wm_subSchedulesGoods set addGoodsNo='' ,addGoodsCnt='' where idx='{$rt['idx']}'";
							$db->query($upSQL);
						}
					}
					/*추가상품추가,변경 종료*/

					//$after_content = $subscription->getApplyInfo($in['idxApply']);
					//$after_content = addslashes(json_encode($after_content,JSON_UNESCAPED_UNICODE));
					

					//$db->query("insert into wm_user_change_log set before_content='{$before_content}',after_content='{$after_content}',memNo='$memNo',regDt=sysdate(),mode='{$in['mode']}'");
				
					$strSQL="select * from wm_subApplyInfo where idx='{$in['idxApply']}'";
					$newApplyInfo=$db->fetch($strSQL);
					
					$different_data=[];
					foreach($preApplyInfo as $wk =>$wt){
						
						if($newApplyInfo[$wk]!=$wt){
							if($wk!="modDt" && $wk!="idx")
								$different_data[]="정기결제신청번호:".$in['idxApply']."의 ".$wk."관련값".$wt."에서".$newApplyInfo[$wk]."로 변경되었습니다.";
						}
					}

					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}',before_content='',after_content='{$after_content}',memNo='$memNo',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					$strSQL="select * from wm_subApplyGoods where idxApply='{$in['idxApply']}'";
					$Rows=$db->query_fetch($strSQL);
					$newApplyGoods = $Rows;

					$different_data=[];
					foreach($preApplyGoods as $wkk =>$wtt){

						
						foreach($wtt as $wk=>$wt){
							
							if($wt!=$newApplyGoods[$wkk][$wk] && $wk!='modDt' && $wk!="regDt" && $wk!='idx'){
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".$wt."에서".$newApplyGoods[$wkk][$wk]."로 변경되었습니다.";
							}
						}
					}

					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}', before_content='',after_content='{$after_content}',memNo='$memNo',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					$strSQL="select count(idx) as cnt from wm_subSchedules where  idxApply='{$in['idxApply']}'";
					$Rows=$db->fetch($strSQL);
					$newSchedule = $Rows['cnt'];
					
					//if($preSchedule['cnt']!=$newSchedule['cnt']){//20230526 배송횟수값이 있으면 무조건 로그남기는것으로 변경함-웹앤모바일
					if ($in['deliveryEa'] > 0) {
						$after_content="정기결제 신청번호:".$in['idxApply']."건의 회차가".$preSchedule['cnt']."회 에서".$newSchedule['cnt']."회로 변경되었습니다.";
						$db->query("insert into wm_user_change_log set idxApply='{$in['idxApply']}', before_content='',after_content='{$after_content}',memNo='{$memNo}',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					// 2024-09-25 wg-eric 배송주기 바꾸면 발송예정일도 변경
					if(($preApplyInfo['payMethodFl'] != $newApplyInfo['payMethodFl']) || 
						($preApplyInfo['deliveryDayPeriod'] && $preApplyInfo['deliveryDayPeriod'] != $newApplyInfo['deliveryDayPeriod']) || 
						($preApplyInfo['deliveryPeriod'] && $preApplyInfo['deliveryPeriod'] != $newApplyInfo['deliveryPeriod'])) {
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

					
					return $this->js("alert('변경되었습니다.');parent.parent.location.reload();");
					break;
				/* 배송일 수령일 변경 */
				case "change_date" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("변경 가능 신청건이 없습니다.");

					$in_idx="idx IN('".implode("','",$in['idx'])."')";
					$sql="select * from wm_subSchedules where ".$in_idx;

					$rows=$db->query_fetch($sql);
					$pre_wm_subSchedules =$rows;
					
					$idxApply = $rows[0]['idxApply'];

					//2023.10.24배송일 중복변경 안되게 시작
					
					$deliveryDoubleChk = 0;
					$deliveryStampTmp=[];
					
					foreach ($in['idx'] as $idx) {
						$deliveryStamp = $in['deliveryStamp'][$idx];
						$deliveryStampTmp[]=$deliveryStamp;
						
						$strSQL="select count(idx) as cnt from wm_subSchedules where idx<>? and deliveryStamp=? and idxApply=";
						$strROW=$db->query_fetch($strSQL,['iii',$idx,$deliveryStamp,$idxApply]);
						
						if ($deliveryStamp > 0 && !empty($strROW['cnt'])) {		
							$deliveryDoubleChk=1;
							break;
						}
					}	
					
					if ($deliveryDoubleChk==1){
						throw new AlertOnlyException("배송일 중복은 허용되지 않습니다.");
						return false;
					}
					
					//if(\Request::getRemoteAddress()=="112.148.62.170"){
						$in_deliveryStamp = array_filter($in['deliveryStamp'], function ($value) {
							
							if(!empty($value)){
								return $value;
							}
							
						});
						
					
						if(count($in_deliveryStamp) != count(array_unique($in_deliveryStamp)) && count($in_deliveryStamp)>0){
							throw new AlertOnlyException("배송일 중복은 허용되지 않습니다.");
							return false;
						}
					//}
					
					
					//2023.10.24배송일 중복변경 안되게 종료
					
					
					foreach ($in['idx'] as $idx) {
					    
					 					    
						$deliveryStamp = $in['deliveryStamp'][$idx];
						
						$strSQL="select count(idx) as cnt from wm_subSchedules where idx=? and deliveryStamp=?";
						$strROW=$db->query_fetch($strSQL,['ii',$idx,$deliveryStamp]);
						
						
						if ($deliveryStamp > 0 ) {
							$param = [
								'deliveryStamp = ?',
							];
							
							$bind = [
								'ii', 
								$deliveryStamp,
								$idx,
							];
							
							$db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);

							//해당회차 이후 스케줄건은 해당회차의 배송일자에 맞게 배송일 재설정 시작 20230629-웹앤모바일
							//if(\Request::getRemoteAddress()=="182.216.219.157"){
						    $sql="select * from wm_subSchedules where idx=?";
						    $schduleRow = $db->query_fetch($sql,['i',$idx]);
						    $subscription->schedule_set_date($idx, $schduleRow[0]['idxApply'],$schduleRow[0]['deliveryStamp'],"change_date");
							//}
						    //해당회차 이후 스케줄건은 해당회차의 배송일자에 맞게 배송일 재설정 종료 20230629-웹앤모바일

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
							if($wt!=$new_wm_subSchedules[$wkk][$wk] && $wk=='deliveryStamp'){
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".date("Y-m-d",$wt)."에서".date("Y-m-d",$new_wm_subSchedules[$wkk][$wk])."로 변경되었습니다.";
							}
						}
					}

					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='{$idxApply}', before_content='',after_content='{$after_content}',memNo='{$memNo}',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					if(\Request::getRemoteAddress()=="112.146.205.124"){
						//gd_debug($pre_wm_subSchedules);
						//gd_debug($new_wm_subSchedules);
						//exit;
					}

					return $this->js("alert('변경되었습니다.');parent.location.reload();");
					break;
				/* 정기배송 중단 */
				case "stop_subscription" : 
					if (empty($in['idx'])) {
						throw new AlertOnlyException("잘못된 접근입니다.");
					}
					
					if(is_array($in['idx']))
						$in_idx="idxApply IN('".implode("','",$in['idx'])."')";
					else
						$in_idx="idxApply IN('".$in['idx']."')";

					$sql="select * from wm_subSchedules where ".$in_idx;
					$rows=$db->query_fetch($sql);
					$pre_wm_subSchedules =$rows;



					$subscription->stopAll($in['idx'],false);


					
					$sql="select * from wm_subSchedules where ".$in_idx;
					$rows=$db->query_fetch($sql);
					$new_wm_subSchedules =$rows;
					
					$different_data=[];
					$idxApply=0;
					foreach($pre_wm_subSchedules as $wkk =>$wtt){
						$idxApply = $wtt['idxApply'];
						foreach($wtt as $wk =>$wt){
							if($wt!=$new_wm_subSchedules[$wkk][$wk] && ($wk=='status' || $wk=="autoExtend")){
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".$wt."에서".$new_wm_subSchedules[$wkk][$wk]."로 변경되었습니다.";
							}
						}
					}
					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='$idxApply',before_content='',after_content='{$after_content}',memNo='{$memNo}',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					// 2024-09-05 wg-eric 정기결제 신청건이 없는 회원은 기본회원등급으로 변경함
					$now=time();
					$applySQL="select * from wm_subApplyInfo where memNo=?";
					$applyInfo = $db->query_fetch($applySQL,['i',$memNo]);

					$autoExtendFl = false;
					foreach ($applyInfo as $item) {
						if (isset($item['autoExtend']) && $item['autoExtend'] == 1) {
							$autoExtendFl = true;
							break;
						}
					}

					$ChkSQL="select count(idx) as cnt from wm_subSchedules where memNo=? and status='ready' AND deliveryStamp>'$now'";
					$ChkROW = $db->query_fetch($ChkSQL,['i',$memNo],false);

					if(!$autoExtendFl && $ChkROW['cnt']<1){
						$sql = "update ".DB_MEMBER." set groupSno ='1' where memNo='".$memNo."' AND groupSno = '{$subCfg['memberGroupSno']}'";
						$db->query($sql);

						$sql = "
							INSERT INTO wg_schedulesMemberGroupLog (memNo, groupSno, regDt)
							VALUES ('".$memNo."', 1, now())
						";
						$db->query($sql);

						if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
							\Session::set('member.groupSno', 1);
						}
					}	

					return $this->js("top.document.location.href='../service/poll_register.php?code=4222314219 '");
					
					break;

				case"pause_subscription":
					if (empty($in['idx'])) {
						throw new AlertOnlyException("잘못된 접근입니다.");
					}
					if(is_array($in['idx']))
						$in_idx="idxApply IN('".implode("','",$in['idx'])."')";
					else
						$in_idx="idxApply IN('".$in['idx']."')";
					$sql="select * from wm_subSchedules where ".$in_idx;
					$rows=$db->query_fetch($sql);
					$pre_wm_subSchedules =$rows;

					$subscription->pauseAll($in['idx'],false,$in['pause_period']);


					$sql="select * from wm_subSchedules where ".$in_idx;
					$rows=$db->query_fetch($sql);
					$new_wm_subSchedules =$rows;

										
					$different_data=[];
					$idxApply=0;
					foreach($pre_wm_subSchedules as $wkk =>$wtt){
						$idxApply=$wtt['idxApply'];
						foreach($wtt as $wk =>$wt){
							if($wt!=$new_wm_subSchedules[$wkk][$wk] && ($wk=='status' || $wk=="deliveryStamp")){

								if($wk=="deliveryStamp")
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".date("Y-m-d",$wt)."에서".date("Y-m-d",$new_wm_subSchedules[$wkk][$wk])."로 변경되었습니다.";
								else
									$different_data[]="정기결제신청번호:".$wtt['idxApply']."의 ".$wk."관련값".$wt."에서".$new_wm_subSchedules[$wkk][$wk]."로 변경되었습니다.";
							}
						}
					}
					if(count($different_data)>0){

						$after_content=implode("=||=",$different_data);
						
						$db->query("insert into wm_user_change_log set idxApply='$idxApply',before_content='',after_content='{$after_content}',memNo='{$memNo}',changeUser='{$memId}',regDt=sysdate(),mode='{$in['mode']}'");
					}

					// 2024-09-05 wg-eric 정기결제 신청건이 없는 회원은 기본회원등급으로 변경함
					$now=time();
					$applySQL="select * from wm_subApplyInfo where memNo=?";
					$applyInfo = $db->query_fetch($applySQL,['i',$memNo]);

					$autoExtendFl = false;
					foreach ($applyInfo as $item) {
						if (isset($item['autoExtend']) && $item['autoExtend'] == 1) {
							$autoExtendFl = true;
							break;
						}
					}

					$ChkSQL="select count(idx) as cnt from wm_subSchedules where memNo=? and status='ready' AND deliveryStamp>'$now'";
					$ChkROW = $db->query_fetch($ChkSQL,['i',$memNo],false);

					if(!$autoExtendFl && $ChkROW['cnt']<1){
						$sql = "update ".DB_MEMBER." set groupSno ='1' where memNo='".$memNo."' AND groupSno = '{$subCfg['memberGroupSno']}'";
						$db->query($sql);

						$sql = "
							INSERT INTO wg_schedulesMemberGroupLog (memNo, groupSno, regDt)
							VALUES ('".$memNo."', 1, now())
						";
						$db->query($sql);

						if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
							\Session::set('member.groupSno', 1);
						}
					}	

					return $this->js("top.document.location.reload()");
					break;


					break;
			}
		} catch (AlertOnlyException $e) {
			throw $e;
		} catch (Exception $e) {
			throw new AlertOnlyException($e->getMessage());
		}
		exit;
	}
}