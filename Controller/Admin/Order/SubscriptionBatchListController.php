<?php
namespace Controller\Admin\Order;

use App;
use Request;
use Component\Policy\Policy;

class SubscriptionBatchListController extends \Controller\Admin\Controller
{

	public function index()
	{
		$this->callMenu("order", "subscription", "batch_list");
		
		$request = App::getInstance('request');

		$getValue=Request::get()->toArray();
		$page=Request::get()->get("page");
		$pageNum=Request::get()->get("pageNum");
		

		$q = [];
        
        $conds = "";
        
        if ($getValue['searchDate'][0]) {
            $sstamp = strtotime($getValue['searchDate'][0]);
            $date = date("Y-m-d H:i:s", $sstamp);
            $q[] = "o.regDt >= '{$date}'";
        }
        
        if ($getValue['searchDate'][1]) {
            $estamp = strtotime($getValue['searchDate'][1]) + (60 * 60 * 24);
            $date = date("Y-m-d H:i:s", $estamp);
            $q[] = "o.regDt < '{$date}'";
        }

		if ($getValue['orderStatus']) {
			
            $q2 = [];
			
            foreach ($getValue['orderStatus'] as $s) {

                $q2[] = "'{$s}'";
            }
           
            if ($q2)
                $q[] = "o.orderStatus IN (".implode(",", $q2).")";
        }
        
        if ($getValue['sopt'] && $getValue['skey']) {
            $fields = "";
            switch ($getValue['sopt']) {
                case "all" : 
                    $fields = "CONCAT(m.phone, m.cellPhone, oi.orderNo, oi.orderPhone, oi.orderCellPhone, oi.receiverPhone, oi.receiverCellPhone, m.memNm, oi.orderName, oi.receiverName)";
                    break;
                case "name" : 
                    $fields = "CONCAT(m.memNm, oi.orderName, oi.receiverName)";
                    break;
                case "mobile": 
                    $fields = "CONCAT(m.phone, m.cellPhone, oi.orderPhone, oi.orderCellPhone, oi.receiverPhone, oi.receiverCellPhone)";
                    break;
                default : 
                   $fields = $getValue['sopt'];
                    break;
            }
             $q[] = "{$fields} LIKE '%{$getValue['skey']}%'";
        }
        
        if ($q) 
            $conds = " AND " . implode(" AND ", $q);


		$db=\App::load(\DB::class);

		$policy = gd_policy('order.status');

		$status=array();
		foreach($policy as $pk =>$t){
			foreach($t as $tt =>$v){
				$status[$tt]=$v['user'];
			}
		}

		//gd_debug($status);
		$this->setData("status",$status);
		
		$limit=30;


		if(empty($pageNum))
			$pageNum=$limit;

		$pageObject = new \Component\Page\Page($page, 0, 0, $pageNum);

		if(empty($page))$page=1;

		$first=($page-1)*$limit;

		$strSQL="select b.*,o.orderPGLog,o.orderStatus,o.settlePrice,o.orderGoodsNm,o.orderGoodsCnt,o.totalDeliveryCharge,o.totalGoodsPrice from ".DB_ORDER." o INNER JOIN wm_subSchedules b ON o.orderNo=b.orderNo INNER JOIN ".DB_MEMBER." m ON o.memNo=m.memNo INNER JOIN ".DB_ORDER_INFO." oi ON oi.orderNo=o.orderNo where 1=1 {$conds} order by o.regDt DESC limit $first,$limit";

	
		$rows=$db->query_fetch($strSQL);

		foreach($rows as $key =>$v){
		
			$orderStatus=$v['orderStatus'];

		

			foreach($policy as $k =>$p){
				foreach($p as $kk =>$pp){
					if($kk==$orderStatus){
						$rows[$key]['orderStatus_text']=$pp['user'];
						break;
					}
				}
			}
		}

		$strSQL="select count(b.idx)as cnt from ".DB_ORDER." o INNER JOIN wm_subSchedules b ON o.orderNo=b.orderNo INNER JOIN ".DB_MEMBER." m ON o.memNo=m.memNo INNER JOIN ".DB_ORDER_INFO." oi ON oi.orderNo=o.orderNo where 1=1 {$conds} order by o.regDt ASC ";
		echo $strSQL;
		$row=$db->fetch($strSQL);
		$total=$row['cnt'];

		$pageObject->setTotal($total);
		$pageObject->setCache(true);

		$pageObject->setUrl($request->getQueryString());
		$pageObject->setPage();

		$amount=$total;
		$pageObject->setAmount($amount);

		$this->setData('page', $pageObject);

		$this->setData("list",$rows);
		$this->setData("total",$total);
		
		$this->setData("search", $getValue);		

	}

}