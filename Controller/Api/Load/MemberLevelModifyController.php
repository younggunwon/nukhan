<?php

namespace Controller\Api\Load;

class MemberLevelModifyController extends \Controller\Api\Controller
{
	public function index()
	{
		$this->db = \App::load(\DB::class);
		$subscription = \App::load('\\Component\\Subscription\\Subscription');
        $subCfg = $subscription->getCfg();

		exit;

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

		$sql = "
			select s.memNo, m.groupSno, sa.autoExtend, count(s.idx) as rowCnt from wm_subSchedules as s
			LEFT JOIN es_member as m ON m.memNo = s.memNo
			LEFT JOIN wm_subApplyInfo as sa ON sa.idx = s.idxApply
			where s.status='ready'
			AND sa.autoExtend = '1'
			AND m.groupSno = '1'
			AND s.deliveryStamp >  UNIX_TIMESTAMP()
			GROUP BY  s.memNo
			HAVING COUNT(s.idx) > 0;
		";
		$normalGroupData = $this->db->slave()->query_fetch($sql);

		if($normalGroupData) {
			foreach($normalGroupData as $key => $val) {
				$sql = "update ".DB_MEMBER." set groupSno = '{$subCfg['memberGroupSno']}' where memNo='".$val['memNo']."' AND groupSno = 1";
				$this->db->query($sql);

				$sql = "
					INSERT INTO wg_schedulesMemberGroupLog (memNo, groupSno, regDt)
					VALUES ('".$val['memNo']."', '{$subCfg['memberGroupSno']}', now())
				";
				$this->db->query($sql);
			}
		}

		exit;
	
	}

}