<?php

namespace Controller\Admin\Order;

use App;
use Request;

/**
* 정기배송 신청 관리자 메모 
*
* @author webnmobile
*/
class SubscriptionApplyMemoController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
		
		$idx = Request::get()->get("idx");
		if (!$idx) {
			return $this->js("alert('잘못된 접근입니다.');self.close();");
		}
		
		$db = App::load(\DB::class);
		
		$sql = "SELECT * FROM wm_subApplyInfo WHERE idx = ?";
		$row = $db->query_fetch($sql, ["i", $idx], false);
		if (!$row) {
			throw $this->js("alert('신청정보가 존재하지 않습니다.');self.close();");
		}
		
		$row['adminMemo'] = str_replace("\\r\\n", PHP_EOL, $row['adminMemo']);
		$this->setData($row);
	}
}