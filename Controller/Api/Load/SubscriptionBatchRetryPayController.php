<?php 

//실패건 다시 스케줄링
namespace Controller\Api\Load;
use App;
use Request;
use Exception;
use Component\Subscription\Subscription;
//use Component\Subscription\KakaoPay;
use Component\Subscription\SubscriptionBatch;

class SubscriptionBatchRetryPayController extends \Controller\Api\Controller
{
    private $db="";
    
    public function index()
    {
        set_time_limit(0);
        $remoteIP= Request::getRemoteAddress();
        
        //if($remoteIP != "118.219.233.182")
        //	exit;
        
        $now_date = date("Y-m-d");
        
        $stop_date = date("Y-m-d",strtotime("+ 1day"));
                
        $this->db = App::load(\DB::class);
        $subObj = new Subscription();
        $subBatch  = new SubscriptionBatch();
        
        $subCfg = $subObj->getCfg();
        
        if(empty($subCfg['order_fail_retry']))
            exit;
        
        
        //$kakaoPay = new \Component\Subscription\KakaoPay();
		//$kakaoPay->schedule=2;
        //$iniApi = new \Component\Subscription\IniApiPay();
        
        $table="wm_subscription_fail";
        
        $sql  = "select * from ".$table." a where status=?  or status=? order by idx ASC";
        $rows = $this->db->query_fetch($sql,['ss','first','middle']);

gd_debug($this->db->getBindingQueryString($sql, ['ss','first','middle']));
exit;
        $order_fail_retry = explode(",",$subCfg['order_fail_retry']);
        
        foreach($rows as $key =>$val){
            
            $subSQL = "select * from wm_subSchedules where idx = ?";
            $subROW = $this->db->query_fetch($subSQL,['i',$val['scheduleIdx']]);
            
            //$infoSQL = "select * from wm_subApplyInfo where idx = ?";
            //$infoROW = $this->db ->query_fetch($infoSQL,['i',$val['idxApply']]);
            
            //if($subRow['status']!='ready')
            //    continue;
            
            //결제대기중인 스케줄링 건만 실행
            if(empty($subROW[0]['orderNo']) && $subROW[0]['status']=='ready'){
            
                
                $chkDay=[];
                
                foreach($order_fail_retry as $ckey =>$t){
                    
                    $TmpRegDt = substr($val['fail_regdt'],0,10);
                    $chkDay[] = date("Y-m-d",strtotime($TmpRegDt." +".$t." day"));

                }
                
                               
                $retry_count = count($order_fail_retry);
                
                if($val['status']=="first"){
                
				
					
                    if(in_array($now_date,$chkDay)===false){
                        continue;
                    }
                   
				   
                    $rcount=$val['retry_count']+1;
					                    
                    if($rcount<=$retry_count){
                    
                        $upSQL="update ".$table." set retry_count='$rcount' where idx=?";
                        $this->db->bind_query($upSQL,['i',$val['idx']]);
                        
                        $method=$subObj->orderType("",$val['scheduleIdx']);
                        
						
						if($method=="ini"){
							try{

								$content = json_encode($val);
								$this->db->query("insert into wm_retery_log set ptype='ini',scheduleIdx='{$val['scheduleIdx']}',fail_idx='{$val['idx']}',content='{$content}',regDt=sysdate()");

								//$payReturn = $iniApi->INIPay($val['scheduleIdx']);
								$payReturn = $subObj->pay($val['scheduleIdx']);
							}catch(Exception $e){
					
							}
							
						}else if($method=="kakao"){

							$kakaoPay = new \Component\Subscription\KakaoPay();
							$kakaoPay->schedule=2;
							try{

								$content = json_encode($val);
								$this->db->query("insert into wm_retery_log set ptype='kakao',scheduleIdx='{$val['scheduleIdx']}',fail_idx='{$val['idx']}',content='{$content}',regDt=sysdate()");

								$payReturn = $kakaoPay->kpay($val['scheduleIdx']);
							}catch(Exception $e){
					
							}
						}
						
                        
                        //결제결과 처리시작
                        $orderSQL = "select * from wm_subSchedules where idx = ?";
                        $orderROW = $this->db ->query_fetch($orderSQL,['i',$val['scheduleIdx']]);
                        
                        //결제가 제대로 되었는지 체크한다.
                        $bool=0;
                        if($orderROW[0]['status']=="paid" && !empty($orderROW[0]['orderNo'])){
                            
                            $bool=1;
                            $upSQL="update ".$table." set status='end' where idx=?";
                            $this->db->bind_query($upSQL,['i',$val['idx']]);
                            
							
							

                            //남은 스케줄링 날짜연장처리시작//
                            //$subObj->schedule_set_date($val['scheduleIdx'],$val['idxApply'],$now_date);

							// 2024-10-29 wg-eric 1일씩 차이나서 수정함
							$subObj->schedule_set_date($val['scheduleIdx'],$val['idxApply'], strtotime($now_date) + (60 * 60 * 24 * gd_isset($subCfg['payDayBeforeDelivery'], 1)), 'scheduler');
                            //남은 스케줄링 날짜연장처리종료//

                       }

                        
                        //설정 재시도횟수만큼 처리한경우는 연장처리를 위해 상태값을 변경한다.
                        if($rcount==$retry_count){
                            
                            if($bool!=1){
                                $upSQL="update ".$table." set status='middle',stop_regDt=? where idx=?";
                                $this->db->bind_query($upSQL,['si',$stop_date,$val['idx']]);
                            }
                        }
						if($rcount<$retry_count){
							if($bool!=1){
								if($subCfg['rFailSmsUse']=='y'){
									$payDate=[];
									$mobile = str_replace("-", "", $subROW[0]['orderCellPhone']);
									$nextSQL = "select * from wm_subSchedules where idx > ? and idxApply=? and status='ready' order by idx ASC limit 0,1";
									$nextROW = $this->db->query_fetch($nextSQL,['ii',$subROW[0]['idxSchedule'],$subROW[0]['idxApply']]);
									$next['deliveryDate'] = date("Y-m-d", $nextROW[0]['deliveryStamp']);
									$payDate['payDate']   = date("Y.m.d", strtotime($next['deliveryDate']) - (60 * 60 * 24 * $subCfg['payDayBeforeDelivery']));     

									$subBatch->sendSms($mobile,$subCfg['rFailSmsTemplate'],$payDate);
								}
							}
						}

                    }else{
                        $upSQL="update ".$table." set status='middle',stop_regDt=? where idx=?";
                        $this->db->bind_query($upSQL,['si',$stop_date,$val['idx']]);
                        
                    }
                       

                }else if($val['status']=="middle" && $now_date>=$val['stop_regDt']){
                    $upSQL="update ".$table." set status='end' where idx=?";
                    $this->db->bind_query($upSQL,['i',$val['idx']]);
                    
					$csql ="select count(idx) as cnt from wm_subSchedules where idxApply=? and status='paid' and idx>=?";
					$crow = $this->db->query_fetch($csql,['ii',$val['idxApply'],$val['scheduleIdx']]);
					
					if(empty($crow[0]['cnt']) || $crow[0]['cnt'] == 0) {
						$sql="update wm_subSchedules set status='stop' where idxApply=? and status='ready' and idx>=?";
						$this->db->bind_query($sql,['ii',$val['idxApply'],$val['scheduleIdx']]);
                    }
                    $sql="update wm_subApplyInfo set autoExtend='0' where idx=?";
                    $this->db->bind_query($sql,['i',$val['idxApply']]);
                }
            }else{
                $upSQL="update ".$table." set status='end' where idx=?";
                $this->db->bind_query($upSQL,['i',$val['idx']]);
            }
            
        }

		// 2024-09-05 wg-eric 정기결제 신청건이 없는 회원은 기본회원등급으로 변경함
		$sql = "
			SELECT apply.* FROM wm_subApplyInfo AS apply
			LEFT JOIN es_member AS m ON m.memNo = apply.memNo
			LEFT JOIN wm_subSchedules AS schedule ON schedule.memNo = apply.memNo 
				AND schedule.status = 'ready'
				AND schedule.deliveryStamp > UNIX_TIMESTAMP()
			WHERE NOT EXISTS (
					SELECT 1 
					FROM wm_subApplyInfo AS subApply
					WHERE subApply.memNo = apply.memNo
					  AND subApply.autoExtend = 1
				)
				AND m.groupSno = '{$subCfg['memberGroupSno']}'
			GROUP BY m.memNo
			HAVING COUNT(schedule.idx) < 1;
		";
		$result = $this->db->slave()->query_fetch($sql);
		if($result) {
			foreach($result as $key => $val) {
				$sql = "update ".DB_MEMBER." set groupSno ='1' where memNo='".$val['memNo']."' AND groupSno = '{$subCfg['memberGroupSno']}'";
				$this->db->query($sql);

				$sql = "
					INSERT INTO wg_schedulesMemberGroupLog (memNo, groupSno, regDt)
					VALUES ('".$val['memNo']."', 1, now())
				";
				$this->db->query($sql);
			}
		}
            
        exit();
    }
    
}



?>