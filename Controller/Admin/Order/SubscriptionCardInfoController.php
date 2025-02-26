<?php
namespace Controller\Admin\Order;

use App;
use Request;

class SubscriptionCardInfoController extends \Controller\Admin\Controller
{
    public function index()
    {
        $this->getView()->setDefine("layout", "layout_blank");
        $subscription = App::load(\Component\Subscription\Subscription::class);
        if (!$idx = Request::get()->get("idx"))
            return $this->js("alert('잘못된 접근입니다.');self.close();");
       
		$db = App::load(\DB::class);
		$info = $subscription->getCard($idx);
        if (!$info)
            return $this->js("alert('카드정보가 존재하지 않습니다.');self.close();");
       
		$sql = "SELECT memNm, memId FROM " . DB_MEMBER . " WHERE memNo = ?";
		$row = $db->query_fetch($sql, ["i", $info['memNo']], false);
		$info = array_merge($row, $info);
        $this->setData($info);
    }
}