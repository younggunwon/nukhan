<?php
namespace Controller\Admin\Order;

use App;
use Request;

/**
* 정기결제 신청관리 
*
* @author webnmobile
*/ 
class SubscriptionApplyListController extends \Controller\Admin\Controller 
{
    public function index()
    {
        $this->callMenu("order", "subscription", "apply_list");


		//if(\Request::getRemoteAddress()=="112.146.205.124"){
		//	$this->setData("remote",1);
		//}
		
        $get = Request::get()->all();
		$db = App::load(\DB::class);
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$this->setData("search", $get);

		//처음신청한날로부터 현재일 까지의 몇일이 지났는지 시작//
		$sql		= "select regDt from wm_subApplyInfo order by idx ASC limit 0,1";
		$row		= $db->fetch($sql);
		
		$now_date	= date("Y-m-d");
		$first_date	= new \DateTime($now_date);
		$end_date	= new \DateTime(substr($row['regDt'],0,10));
		$all_day	= $first_date -> diff($end_date) -> days;
		
		$this->setData("all_day",$all_day);
		//처음신청한날로부터 현재일 까지의 몇일이 지났는지 종료//

		/* 배송일 검색 START */
		if ($get['deliveryDate']) {
			$stamp = strtotime($get['deliveryDate']);
			$sql = "SELECT idxApply as idx FROM wm_subSchedules WHERE deliveryStamp = ? GROUP BY idxApply";
			$list = $db->query_fetch($sql, ["i", $stamp]);
			if ($list) {
				$idxApplies = array_column($list, "idx");
				$subscription->searchIdxes = $idxApplies;
			} else {
				$result = [];
			}
		}
		/* 배송일 검색 START */
		
		/* 상품명 검색 S */
		if ($get['sopt'] == 'goodsNm' && $get['skey']) {
			$sql = "SELECT b.idxApply as idx FROM wm_subSchedulesGoods AS a 
						INNER JOIN wm_subSchedules AS b ON a.idxSchedule = b.idx
						INNER JOIN " . DB_GOODS . " AS g ON a.goodsNo = g.goodsNo 
					WHERE goodsNm LIKE ? GROUP BY b.idxApply";
			$list = $db->query_fetch($sql, ["s", "%".$get['skey']."%"]);
			if ($list) {
				$idxApplies = array_column($list, "idx");
				if ($subscription->searchIdxes) {
					$subscription->searchIdxes = array_merge($subscription->searchIdxes, $idxApplies);
				} else {
					$subscription->searchIdxes = $idxApplies;
				}
			}
			
			unset($get['sopt']);
			unset($get['skey']);
		}
		/* 상품명 검색 E */
		$subscription->adminList = true;
		$result = $subscription->setAdmin(true)->getApplyList(null, [], true, $get['page']);

		$now=time();
		
		if ($result && $result['list']) {
			foreach ($result['list'] as $k => $v) {
				$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules AS a 
								INNER JOIN " . DB_ORDER . " AS o ON a.orderNo = o.orderNo 
							WHERE a.idxApply = ? AND a.status='paid' AND SUBSTR(o.orderStatus, 1, 1) IN ('o','p','g','d','s')";
				$row = $db->query_fetch($sql, ["i", $v['idx']], false);
				$v['orderCnt'] = gd_isset($row['cnt'], 0);
				
				$sql = "SELECT g.goodsNo, g.goodsNm, a.goodsCnt,a.addGoodsNo FROM wm_subApplyGoods AS a 
								INNER JOIN " . DB_GOODS . " AS g ON a.goodsNo = g.goodsNo WHERE a.idxApply = ? LIMIT 0, 1";
								
				$row = $db->query_fetch($sql, ["i", $v['idx']], false);
				$v = $row?array_merge($v, $row):$v;


						$addGoodsNoData = json_decode(stripslashes($row['addGoodsNo']));
						$addGoodsNo=[];
						
						foreach($addGoodsNoData as $addKey=>$addVal){
							$addGoodsNo[]=$addVal;
							
							
						}
						$addSQL="select goodsNm from ".DB_ADD_GOODS." where addGoodsNo IN(".implode(",",$addGoodsNo).")";
						$addRows=$db->query_fetch($addSQL);
						$addGoodsNm=[];
						foreach($addRows as $ak =>$at){
							$addGoodsNm[]=$at['goodsNm'];
						}
					$v['addGoodsNm']=$addGoodsNm;

				
				$sql = "SELECT deliveryStamp FROM wm_subSchedules WHERE idxApply = ? AND deliveryStamp > 0 ORDER BY deliveryStamp LIMIT 0, 1";
				$row = $db->query_fetch($sql, ["i", $v['idx']], false);
				$v['firstDeliveryStamp'] = gd_isset($row['deliveryStamp'], 0);
					$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='ready' AND idxApply = ?";

				if ($v['autoExtend'])  {
					$v['isAvailable'] = 1;
				} else {
					
					$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='ready' AND idxApply = ? and deliveryStamp>'$now'";
					$row = $db->query_fetch($sql, ["i", $v['idx']], false);
					$v['isAvailable'] = $row['cnt']?1:0;


					$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='pause' AND idxApply = ? and deliveryStamp>'$now'";
					$row = $db->query_fetch($sql, ["i", $v['idx']], false);
					$v['PauseisAvailable'] = $row['cnt']?1:0;

				}

				//총횟수 시작 2022.06.21 민트웹-중지 또는 환불건 제외
				$tcountOrderStatus=array('p','s','d','o','g');
				$sql = "SELECT ws.*,o.orderNo,substr(o.orderStatus,1,1)as orderStatus FROM wm_subSchedules as ws LEFT JOIN ".DB_ORDER." o ON ws.orderNo=o.orderNo WHERE ws.status<>'stop'AND ws.idxApply = ?";
				$trows = $db->query_fetch($sql, ["i", $v['idx']]);
				$tcount=0;

				
				foreach($trows as $kk =>$tt){
					
					if(!empty($tt['orderNo']) && in_array($tt['orderStatus'],$tcountOrderStatus)!==false){
						$tcount++;
					}else if(empty($tt['orderNo']) && $tt['status']!="stop"){
						$tcount++;
					}
				}
				
				$v['totalSchedule']=$tcount;
				//총횟수 종료 2022.06.21 민트웹 

				
				$sql = "SELECT * FROM wm_subDeliveryInfo a left outer join es_member b on a.memNo = b.memNo WHERE idxApply = ?"; 
				$row = $db->query_fetch($sql, ["i", $v['idx']], false);
				if ($row) {
					$v['memNm'] = gd_isset($v['memNm'], $row['orderName']);
					$v['cellPhone'] = gd_isset($v['cellPhone'], $row['orderCellPhone']);
					$v['sexFl'] = '';
					if ($row['sexFl'] == 'm') $v['sexFl'] = '남';
					else $v['sexFl'] = '여';
				}
				
				
				$result['list'][$k] = $v;
			}
		}

		
		$this->setData($result);
		$conf = $subscription->getCfg();
		$this->setData("conf", $conf);
		


		$subscriptionBatch = \App::load('Component\\Subscription\\SubscriptionBatch');
		$subscriptionBatch->pauseUnLock();
    }
}