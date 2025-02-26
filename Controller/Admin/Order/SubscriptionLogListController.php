<?php
namespace Controller\Admin\Order;

use App;
use Request;

class SubscriptionLogListController extends \Controller\Admin\Controller
{
	
	protected $mode=array("update_change_apply"=>"배송지 및 결제카드,추가옵션 ,결제주기,옵션 변경","change_date"=>"배송일 수령일 변경","stop_subscription"=>"정기배송 중단","pause_subscription"=>"정기배송 일시정지","update_apply_list"=>"신청목록 수정","update_apply_info"=>"정기배송 신청 변경","delete_apply_list"=>"신청목록 삭제","change_card"=>"카드 변경","change_delivery_date"=>"배송일 변경","change_address"=>"배송 주기 / 배송 주소 변경","update_schedule_list"=>"스케줄 변경","delete_schedule_list"=>"스케줄 삭제","add_schedule"=>"스케줄 추가","delete_card"=>"카드삭제");

	public function index()
	{
		$this->callMenu("order", "subscription", "log_list");

		$db = App::load(\DB::class);
		$limit=20;
		$page=Request::get()->get("page");
		$pageNum=Request::get()->get("pageNum");

		if(empty($pageNum))
			$pageNum=$limit;

		$request = App::getInstance('request');
		$pageObject = new \Component\Page\Page($page, 0, 0, $pageNum);


		if(empty($page))$page=1;

		$first=($page-1)*$limit;

		$where=" and substr(regDt,1,13)>='2022-09-08 10'";

		$in=\Request::request()->all();
		$this->setData($in);

		if(!empty($in['wDate'][0]) && !empty($in['wDate'][1])){//수정일검색
			
			$where.=" and substr(a.regDt,1,10)>='{$in['wDate'][0]}' && substr(a.regDt,1,10)<='{$in['wDate'][1]}'";
		}

		if(!empty($in['idxApply']))//신청번호검색
			$where.=" and a.idxApply='{$in['idxApply']}'";
		

		if(!empty($in['goodsNm'])){//신청상품명으로검색
			
			$where.=" and (select count(wsg.idx) from wm_subApplyGoods wsg INNER JOIN ".DB_GOODS." g ON wsg.goodsNo=g.goodsNo where g.goodsNm like '%{$in['goodsNm']}%' and wsg.idxApply=a.idxApply)>'0' ";
			
		}

		if(!empty($in['memId'])){//신청자아이디 수정자아이디 검색

			$where.=" and ((select memNo from ".DB_MEMBER." m where m.memId='{$in['memId']}')=a.memNo || a.changeUser='{$in['memId']}')";
		}

		if(!empty($in['memNm'])){//신청자 이름검색

			$where.=" and (select memNo from ".DB_MEMBER." m where m.memNm='{$in['memNm']}')=a.memNo";
		}

		if(!empty($in['cellPhone'])){//신청자 휴대폰검색

			$where.=" and (select memNo from ".DB_MEMBER." m where REPLACE(m.cellPhone,'-','')=REPLACE('{$in['cellPhone']}','-',''))=a.memNo";
		}

		$sub_where=[];
		foreach($in['mode'] as $mk =>$mt){
			if(!empty($mt))
				$sub_where[]="a.mode='{$mt}'";			
		}

		if(count($sub_where)>0){
			$where.=" and (".implode(" or ",$sub_where).")";
		}

		$sql="select count(idx) as cnt from wm_user_change_log where 1=1 $where";
		$row = $db->query_fetch($sql);

		$total=$row[0]['cnt'];


		$sql="select a.*,(select mm.memNm from ".DB_MEMBER." mm where mm.memNo=a.memNo)as memNm,if(a.changeUser='user',(select memNm from ".DB_MEMBER." m where m.memNo=a.memNo and changeUser='user') , a.changeUser) as modMemNm from wm_user_change_log a where 1=1 $where order by a.idx DESC limit $first,$limit";
		$rows = $db->query_fetch($sql);

		$pageObject->setTotal($total);
		$pageObject->setCache(true);

		$pageObject->setUrl($request->getQueryString());
		$pageObject->setPage();

		$amount=$total;
		$pageObject->setAmount($amount);


		$this->setData('page', $pageObject);

		$this->setData("total",$total);

		foreach($rows as $key =>$t){

			$after_content=str_replace("=||=","</br>",$t['after_content']);

			$rows[$key]['after_content']=$after_content;
			
			$rows[$key]['mode']=$this->mode[$t['mode']];

			/*
			switch($t['mode']){
				case "update_change_apply":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"배송지 및 결제카드,추가옵션 ,결제주기,옵션 변경";
					break;
				case "change_date":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"배송일 수령일 변경";
					break;	
				case "stop_subscription":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"정기배송 중단";
					break;	
					
				case "pause_subscription":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"정기배송 일시정지";
					break;	
				//case "pause_subscription":
				//	$rows[$key]['mode']="정기배송 일시정지";
				//	break;	

				case "update_apply_list":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"신청목록 수정";
					break;	
				case "update_apply_info":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"정기배송 신청 변경";
					break;
				case "delete_apply_list":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"신청목록 삭제";
					break;
				case "change_card":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"카드 변경";
					break;
				case "change_delivery_date":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"배송일 변경";
					break;
				case "change_address":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"배송 주기 / 배송 주소 변경";
					break;
				case "update_schedule_list":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"스케줄 변경";
					break;
				case "delete_schedule_list":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"스케줄 삭제";
					break;
				case "add_schedule":
					$rows[$key]['mode']=$this->mode[$t['mode']];//"스케줄 추가";
					break;
				case "delete_card"
					$rows[$key]['mode']=$this->mode[$t['mode']];;
					break;

			}
			*/
			//gd_debug($t);
			//gd_debug(iconv("utf-8","euc-kr",$t['before_content']));
			
		}

		$this->setData("global_mode",$this->mode);
		$this->setData("data",$rows);
		
	}

}