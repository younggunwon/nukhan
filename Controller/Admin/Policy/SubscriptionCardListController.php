<?php

namespace Controller\Admin\Policy;

use App;
use Request;

class SubscriptionCardListController extends \Controller\Admin\Controller 
{
    public function index()
    {
        $this->callMenu("policy", "subscription", "card");
        $db = App::load('DB');
        $obj = App::load("\Component\Subscription\Subscription");
        
        $list = [];
        $get = Request::get()->toArray();
        $page = gd_isset($get['page'], 1);
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $conds = "";
        $q = [];

		$q[]="payKey<>''";
        if ($get['searchDate'][0]) {
            //$sstamp = strtotime($get['searchDate'][0]);
			//gd_debug(date("Y-m-d",$sstamp));
			$sstamp=$get['searchDate'][0];
            $q[] = "DATE_FORMAT(a.regDt,'%Y-%m-%d') >= '{$sstamp}'";
        }
        
        if ($get['searchDate'][1]) {
            //$estamp = strtotime($get['searchDate'][1]) + (60 * 60 * 24);
			//gd_debug(date("Y-m-d",$estamp));
			$estamp=$get['searchDate'][1];
            $q[] = "DATE_FORMAT(a.regDt,'%Y-%m-%d') <= '{$estamp}'";

            //$q[] = "a.regStamp < {$estamp}";
        }

        
        if ($get['memNm']) {
            $get['memNm'] = $db->escape($get['memNm']);
            $q[] = "CONCAT(b.memNm, b.memId) LIKE '%{$get['memNm']}%'";
        }
        
        if ($get['cardNm']) {
            $get['cardNm'] = $db->escape($get['cardNm']);
            $q[] = "a.cardNm LIKE '%{$get['cardNm']}%'";
        }
        
        if ($q)
            $conds = " WHERE " . implode(" AND ", $q);
        
        $total = $amount = 0;
        
        //$row = $db->fetch("SELECT COUNT(*) as cnt FROM wm_subscription_cards");
		//echo "SELECT COUNT(*) as cnt FROM wm_subscription_cards";
        //$amount = gd_isset($row['cnt'], 0);
        
        $sql = "SELECT COUNT(*) as cnt FROM wm_subCards AS a LEFT JOIN " . DB_MEMBER . " AS m ON a.memNo = m.memNo{$conds}";
        $row = $db->fetch($sql);
        $total = gd_isset($row['cnt'], 0);
		$amount = $total;
        
        $sql = "SELECT a.*, b.memNm, b.memId FROM wm_subCards AS a 
                        LEFT JOIN " . DB_MEMBER . " AS b ON a.memNo = b.memNo 
                        {$conds} ORDER BY a.memNo desc, a.idx desc LIMIT {$offset}, {$limit}"; 
        if ($tmp = $db->query_fetch($sql))
            $list = $tmp;
        
        $this->setData("list", $list);
        $page = App::load("\Component\Page\Page", $page, $total, $amount, $limit);
        $page->setUrl(http_build_query($get));
        $pagination = $page->getPage();
        
        $this->setData('search', $get);
        $this->setData("list", $list);
        $this->setData("pagination", $pagination);
    }
}