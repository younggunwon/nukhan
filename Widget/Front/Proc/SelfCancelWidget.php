<?php

namespace Widget\Front\Proc;

use App;

/**
* 셀프취소 위젯
*
* @author webnmobile
*/
class SelfCancelWidget extends \Widget\Front\Widget
{
	public function index()
	{
		$db = App::load(\DB::class);
		$selfCancel = App::load(\Component\SelfCancel\SelfCancel::class);
		$conf = $selfCancel->getCfg();
		if ($conf['isUse']) {
			$orderNo = $this->getData("orderNo");
			
			$orderStatus = [];
			$sql = "SELECT og.orderStatus, o.settleKind, o.pgName FROM " . DB_ORDER_GOODS . " AS og INNER JOIN " . DB_ORDER . " AS o ON og.orderNo = o.orderNo WHERE og.orderNo = ?";
			$list = $db->query_fetch($sql, ["i", $orderNo]);
			if ($list && $list[0]['pgName'] != 'sub') {
				foreach ($list as $li) {
					$orderStatus[$li['orderStatus']]++;
				} // endforeach 
				
				if (count($orderStatus)) {
					$status = key($orderStatus);
					$status1 = substr($status, 0, 1);
					
					$settleKinds = [
						'pc', 
						'pb',
						'pv',
						'pk',
						'ec',
						'eb',
						'ev',
						'fc',
						'fu',
					];
					
					if ((in_array($status1, $conf['orderStatus']) || in_array($status, $conf['orderStatus'])) && in_array($list[0]['settleKind'], $settleKinds)) {
						$this->setData("selfCancelPossible", true);
					} // endif 
				} // endif 
			} // endif 
		}
	}
}