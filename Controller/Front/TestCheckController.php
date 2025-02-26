<?php
namespace Controller\Front;

class TestCheckController extends \Controller\Front\Controller
{
	public function index()
	{
	
		$db = \App::load(\DB::class);
		$sql="select * from wm_subApplyInfo";
		$rows = $db->query_fetch($sql);

		foreach($rows as $k =>$t){
			
			$strSQL = "select * from wm_subSchedules where idxApply='{$t['idx']}' and status<>'stop' order by idx ASC limit 0,2";
			$row= $db->query_fetch($strSQL);

			$first=0;



			foreach($row as $kk =>$tt){
			
				if($kk==0)
					$first = $tt['deliveryStamp'];
				else{
					$da = explode("_",$tt['deliveryPeriod']);
					$c = strtotime(date('Y-m-d',$first)." +".$da['0'].$da['1']);


					if($c>$tt['deliveryStamp']){
						
						gd_debug($t['idx']);
						gd_debug(date("Y-m-d",$c));
						gd_debug(date("Y-m-d",$tt['deliveryStamp']));
					}

				}


			}
		}
	
		exit;
	}

}