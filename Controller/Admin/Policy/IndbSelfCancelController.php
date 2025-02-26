<?php

namespace Controller\Admin\Policy; 

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;

/** 
* 셀프취소 관련 DB 처리 
* 
* @author webnmobile
*/
class IndbSelfCancelController extends \Controller\Admin\Controller 
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			$db = App::load(\DB::class);
			
			switch ($in['mode']) {
				/* 셀프취소 설정 */
				case "update_set" : 
					$orderStatus = $in['orderStatus']?implode("||", $in['orderStatus']):"";
					$param = [
						'isUse = ?', 
						'orderStatus = ?',
					];
					
					$bind = [
						'is',
						$in['isUse']?1:0,
						$orderStatus,
					];
					
					$affectedRows = $db->set_update_db("wm_selfCancelSet", $param, "1", $bind);
					if ($affectedRows < 0) 
						throw new AlertOnlyException("저장에 실패하였습니다.");
					
					$this->layer("저장되었습니다.");
					break;
			}
		} catch (AlertOnlyException $e) {
			throw $e;
		}
		exit;
	}
}