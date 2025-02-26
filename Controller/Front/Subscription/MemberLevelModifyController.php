<?php
namespace Controller\Front\Subscription;



class MemberLevelModifyController extends \Controller\Front\Controller
{

	public function index()
	{
		$db = \App::load(\DB::class);

		//정기결제 신청건이 없는 회원은 기본회원등급으로 변경함-스케줄러이용해야함

		$sql="select * from ".DB_MEMBER." where 1=1";
		$rows = $db->query_fetch($sql);

		foreach($rows as $k =>$t){
			$memNo=$t['sno'];

			$applySQL="select * from wm_subApplyInfo where memNo=?";
			$applyInfo = $db->query_fetch($applySQL,['i',$memNo],false);

			$ChkSQL="select count(idx) as cnt from wm_subSchedules where memNo=? and status='ready'";
			$ChkROW = $this->db->query_fetch($ChkSQL,['i',$memNo],false);

			if($applyInfo['autoExtend']!=1 && $ChkROW['cnt']<1){
				$db->query("update ".DB_MEMBER." set groupSno ='1' where memNo='$memNo'");
			}			
		
		}

		exit;
	
	}

}