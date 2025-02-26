<?php

namespace Component\Subscription;

use App;
use Component\Sms\Sms;
use Component\Sms\SmsMessage;
use Component\Sms\LmsMessage;
use Framework\Security\Otp;
use Request;

/**
* 정기결제 일괄처리 관련 
*
* @package Component\SubscriptionNew
* @author webnmobile
*/
class SubscriptionBatch extends \Component\Subscription\Subscription
{
	/**
	* SMS 전송 리스트 
	*
	* @param String $date 전송일
	*
	* @return Array 전송 목록 
	*/
	/*
	public function getBatchSmsList($date = null, $isSent = false)
	{
		$conf = $this->getCfg();
		$stamp = $date?strtotime($date):strtotime(date("Ymd"));
		$deliveryStamp = $stamp + (60 * 60 * 24 * $conf['smsPayBeforeDay']);
		$sql = "SELECT b.*, a.idx as idxSchedule FROM wm_subSchedules AS a 
						INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx 
						INNER JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
					WHERE a.deliveryStamp = ? AND a.status = 'ready' AND a.smsSentStamp = 0 ORDER BY a.idx"; 
		$list = $this->db->query_fetch($sql, ["i", $deliveryStamp]);
		if ($list && $isSent) {
			foreach ($list as $li) {
				$mobile = str_replace("-", "", $li['orderCellPhone']);
				$li['deliveryDate'] = date("Y.m.d", $li['deliveryStamp']);
				$li['payDate'] = date("Y.m.d", $li['deliveryStamp'] - (60 * 60 * 24 * $conf['deliveryDays']));
				$this->sendSms($mobile, $conf['smsTemplate'], $li);
				//if ($result) {
					$param = [
						'smsSentStamp = ?',
					];
					
					$bind = [
						'ii', 
						time(),
						$li['idxSchedule'],
					];
					
					$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
				//}
			}
		}
		
		return gd_isset($list, []);
	}
	*/
	public function getBatchSmsList($date = null, $isSent = false)
   {
      $conf = $this->getCfg();
      $stamp = $date?strtotime($date):strtotime(date("Ymd"));
      $deliveryStamp = $stamp + (60 * 60 * 24 * $conf['smsPayBeforeDay']);
      //$sql = "SELECT b.*, a.deliveryStamp, a.orderName, a.receiverName, a.receiverCellPhone, a.receiverAddress, a.receiverAddressSub, a.orderCellPhone, a.idx as idxSchedule FROM wm_subSchedules AS a 
                  //INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx 
                  //INNER JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
               //WHERE a.deliveryStamp = ? AND a.status = 'ready' AND a.smsSentStamp = 0 ORDER BY a.idx"; 

	  // 2024-10-08 wg-eric "DATE(FROM_UNIXTIME(a.smsSentStamp)) < CURDATE()" 결제실패, 배송일 변경 시 재발송 위해 수정
      $sql = "SELECT b.*, a.deliveryStamp, a.orderName, a.receiverName, a.receiverCellPhone, a.receiverAddress, a.receiverAddressSub, a.orderCellPhone, a.idx as idxSchedule FROM wm_subSchedules AS a 
                  INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx 
                  INNER JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
               WHERE a.deliveryStamp = ? AND a.status = 'ready' AND DATE(FROM_UNIXTIME(a.smsSentStamp)) < CURDATE() ORDER BY a.idx"; 
      $list = $this->db->query_fetch($sql, ["i", $deliveryStamp]);
      if ($list && $isSent) {
         foreach ($list as $li) {
            $mobile = str_replace("-", "", $li['orderCellPhone']);
            $li['deliveryDate'] = date("Y.m.d", $li['deliveryStamp']);
            $li['payDate'] = date("Y.m.d", $li['deliveryStamp'] - (60 * 60 * 24 * $conf['payDayBeforeDelivery']));
            $this->sendSms($mobile, $conf['smsTemplate'], $li);
            //if ($result) {
               $param = [
                  'smsSentStamp = ?',
               ];
               
               $bind = [
                  'ii', 
                  time(),
                  $li['idxSchedule'],
               ];
               
               $this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
            //}
         }
      }
      
      return gd_isset($list, []);
   }
	
	
	/**
	* 결제 리스트 
	*
	* @param String $date 결제일
	*
	* @return Array 전송 목록 
	*/

	public function pauseUnLock(){
	
		$now_date=strtotime(gd_date_format("Y-m-d", "+1 days"));
		$strSQL="select * from wm_subSchedules where  deliveryStamp<='{$now_date}' and status='pause' and regDt >= '2024-09-27'";
		$rows=$this->db->query_fetch($strSQL);

		foreach($rows as $k =>$v){
			//if(!empty($rows['status_period'])){
				$this->db->query("update wm_subSchedules set status='ready' where idx='{$v['idx']}'");
			//}
		}
	}
	public function getBatchPayList($date = null, $isPay = false)
	{
		$conn_ip=\Request::getRemoteAddress();
	    $manager_so = \Session::get('manager.sno');
	    $this->schedule=1;
		$conf = $this->getCfg();
		$stamp = $date?strtotime($date):strtotime(date("Ymd"));
		$deliveryStamp = $stamp + (60 * 60 * 24 * $conf['payDayBeforeDelivery']);
		$deliveryStampEnd = strtotime(date('Y-m-d 23:59:59', $deliveryStamp));

		//$sql = "SELECT *, a.idx as idxSchedule FROM wm_subSchedules AS a INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx WHERE a.deliveryStamp = ? AND a.status = 'ready' ORDER BY a.idx"; 

		//로그시작
		if($isPay==true){
			$logSql = "SELECT a.idxApply, a.idx as idxSchedule FROM wm_subSchedules AS a INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx INNER JOIN ".DB_MEMBER."  m ON m.memNo=a.memNo WHERE a.deliveryStamp >= ? AND a.deliveryStamp <= ? AND a.status = 'ready' and (select count(log.idx) as cnt from wm_subscription_fail log where log.scheduleIdx=a.idx)<='0' ORDER BY a.idx"; 
			
			$arrBind = [];
			$this->db->bind_param_push($arrBind, 'i', $deliveryStamp);
			$this->db->bind_param_push($arrBind, 'i', $deliveryStampEnd);
			
			$logList = $this->db->query_fetch($logSql, $arrBind);
			$content = addslashes(json_encode($logList));
			$this->db->query("insert into wm_test set content='pay',content_data='{$content}',regDt=sysdate(),managerSno='{$manager_so}',conn_ip='{$conn_ip}'");
		}
		
		//로그종료
		
	    $sql = "SELECT *, a.idx as idxSchedule FROM wm_subSchedules AS a INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx INNER JOIN ".DB_MEMBER."  m ON m.memNo=a.memNo WHERE a.deliveryStamp >= ? AND a.deliveryStamp <= ? AND a.status = 'ready' and (select count(log.idx) as cnt from wm_subscription_fail log where log.scheduleIdx=a.idx)<='0' ORDER BY a.idx";
		
		$arrBind = [];
		$this->db->bind_param_push($arrBind, 'i', $deliveryStamp);
		$this->db->bind_param_push($arrBind, 'i', $deliveryStampEnd);

		$list = $this->db->query_fetch($sql, $arrBind);

		$kakaoPay = new \Component\Subscription\KakaoPay();
		
		if ($list && $isPay) {
			foreach ($list as $li) {
				
				$method=$this->orderType("",$li['idxSchedule']);

				if($method=="ini"){
					$this->schedule=1;
					$payReturn = $this->pay($li['idxSchedule']);

				}else if($method=="kakao"){
					try{				
						$kakaoPay->schedule=1;
						$payReturn = $kakaoPay->kpay($li['idxSchedule']);
					}catch(Exception $e){
					
					}

				}else if($method=="naver"){

				}

				if($payReturn == false || empty($payReturn)){
                    $payDate=[];
				    $mobile = str_replace("-", "", $li['orderCellPhone']);
				    $nextSQL = "select * from wm_subSchedules where idx > ? and idxApply=? and status='ready' order by idx ASC limit 0,1";
				    $nextROW = $this->db->query_fetch($nextSQL,['ii',$li['idxSchedule'],$li['idxApply']]);
				    $next['deliveryDate'] = date("Y-m-d", $nextROW[0]['deliveryStamp']);
				    $payDate['payDate']   = date("Y.m.d", strtotime($next['deliveryDate']) - (60 * 60 * 24 * $conf['payDayBeforeDelivery']));
				   
				    $this->sendSms($mobile,$conf['failSmsTemplate'],$payDate);
				}
			}
		}

		return gd_isset($list, []);
	}
	
	/**
	* 자동연장목록 
	*
	* @return Array
	*/
	public function getBatchExtend()
	{
		$list = [];
		$conf = $this->getCfg();
		$stamp = $date?strtotime($date):strtotime(date("Ymd"));
		$deliveryStamp = $stamp + (60 * 60 * 24 * $conf['payDayBeforeDelivery']);
		$sql           = "SELECT a.* FROM wm_subApplyInfo AS a 
						INNER JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo 
					WHERE a.autoExtend='1' ORDER BY a.idx";
		$tmp = $this->db->query_fetch($sql);

		


		if ($tmp) {
			foreach ($tmp as $t) {

				//2023.04.28웹앤모바일  - 실패건 처리중인건이 있으면 연장제외함
				$Fsql = "select count(idx) as cnt from wm_subscription_fail where idxApply=? and status != ?";
				$Frow = $this->db->query_fetch($Fsql,['is',$t['idx'],'end']);
				
				if(empty($Frow[0]['cnt'])){

					$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules AS a INNER JOIN wm_subApplyInfo b ON a.idxApply = b.idx WHERE a.idxApply = ? AND a.deliveryStamp > ? AND a.status = 'ready' ORDER BY a.idx"; 
					$bind = [
						'ii', 
						$t['idx'],
						$deliveryStamp,
					];
					
					$row = $this->db->query_fetch($sql, $bind, false);
					if ($row['cnt'] > 0) continue;
					
					
					$this->addSchedule($t['idx'], 1);
				}
			}
		}
	}
	
	/**
	* SMS 전송 처리 
	*
	*/
   public function sendSms($mobile, $contents, $changeCode = array())
   {
       $cfg = $this->getCfg();
       $bool = false;
       $smsPoint = Sms::getPoint();
       if ($smsPoint >= 1) {
            foreach ($changeCode as $k => $v) {
                if (is_numeric($v))
                    $v = number_format($v);

                $contents = str_replace("{{$k}}", "{$v}", $contents);
            }

            $adminSecuritySmsAuthNumber = Otp::getOtp(8);
            $receiver[0]['cellPhone'] = $mobile;
            $smsSender = \App::load('Component\\Sms\\SmsSender');
            $smsSender->setSmsPoint($smsPoint);

            if(mb_strlen($contents, 'euc-kr')>90){
              $smsSender->setMessage(new LmsMessage($contents));
            }else{
              $smsSender->setMessage(new SmsMessage($contents));
            }

            $smsSender->validPassword(\App::load(\Component\Sms\SmsUtil::class)->getPassword());
            $smsSender->setSmsType('user');
            $smsSender->setReceiver($receiver);
            $smsSender->setLogData(['disableResend' => false]);
            $smsSender->setContentsMask([$adminSecuritySmsAuthNumber]);
            $smsResult = $smsSender->send();
            $smsResult['smsAuthNumber'] = $adminSecuritySmsAuthNumber;

            if ($smsResult['success'] === 1)
              $bool = true;
       }

       return $bool;
   }
}