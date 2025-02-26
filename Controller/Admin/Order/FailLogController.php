<?php 

namespace Controller\Admin\Order;

use Request;

class FailLogController extends \Controller\Admin\Controller
{
    public function index()
    {
        $this->getView()->setDefine("layout", "layout_blank");
        if (!$schedule_idx = Request::get()->get("schedule_idx"))
            return $this->js("alert('잘못된 접근입니다.');self.close();");
        
        $db = \App::load(\DB::class);
        $FailLogSQL = "select * from wm_subscription_fail where scheduleIdx=?";
        $FailLogROW = $db->query_fetch($FailLogSQL,['i',$schedule_idx]);
        
        $this->setData('fail_log',$FailLogROW[0]['fail_log']);
       
    }
}


?>