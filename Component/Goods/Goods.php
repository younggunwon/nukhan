<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link http://www.godo.co.kr
 */
namespace Component\Goods;

use Request;

/**
 * 상품 class
 */
class Goods extends \Bundle\Component\Goods\Goods
{

    public function getGoodsList($cateCd, $cateMode = 'category', $pageNum = 10, $displayOrder = 'sort asc', $imageType = 'list', $optionFl = false, $soldOutFl = true, $brandFl = false, $couponPriceFl = false, $imageViewSize = 0, $displayCnt = 10)
    {

		$tmp=parent::getGoodsList($cateCd, $cateMode, $pageNum, $displayOrder, $imageType, $optionFl, $soldOutFl, $brandFl, $couponPriceFl, $imageViewSize, $displayCnt);
		
		//if(\Request::getRemoteAddress()=="182.225.11.149"){

			$db=\App::load(\DB::class);
			$subObj=new \Component\Subscription\Schedule;

			foreach($tmp['listData'] as $key =>$t){

				$goodsNo = $t['goodsNo'];

				$row=$db->fetch("select useSubscription from ".DB_GOODS." where goodsNo='{$goodsNo}'");

				if($row['useSubscription']==1){

					$result=$subObj->goodsRatio($goodsNo);

					$tmp['listData'][$key]['delivery_dc_ratio']=$result['delivery_dc_ratio'][0];
					$tmp['listData'][$key]['delivery_dc']=$result['delivery_dc'][0];

					
				}

			}

		//}	
		return $tmp;
	}
    public function getGoodsSearchList($pageNum = 10, $displayOrder = 'g.regDt asc', $imageType = 'list', $optionFl = false, $soldOutFl = true, $brandFl = false, $couponPriceFl = false, $displayCnt = 10, $brandDisplayFl = false, $usePage = true, $limit = null,array $goodsNo = null)
    {
		$tmp = parent::getGoodsSearchList($pageNum, $displayOrder, $imageType, $optionFl, $soldOutFl, $brandFl, $couponPriceFl, $displayCnt, $brandDisplayFl, $usePage, $limit,$goodsNo);
		
		//if(\Request::getRemoteAddress()=="182.225.11.149"){

			$db=\App::load(\DB::class);
			$subObj=new \Component\Subscription\Schedule;

			foreach($tmp['listData'] as $key =>$t){

				$goodsNo = $t['goodsNo'];

				$row=$db->fetch("select useSubscription from ".DB_GOODS." where goodsNo='{$goodsNo}'");

				if($row['useSubscription']==1){

					$result=$subObj->goodsRatio($goodsNo);

					$tmp['listData'][$key]['delivery_dc_ratio']=$result['delivery_dc_ratio'][0];
					$tmp['listData'][$key]['delivery_dc']=$result['delivery_dc'][0];

					
				}

			}

			//gd_debug($tmp);

		//}
		
		return $tmp;
	}

    public function getGoodsInfo($goodsNo = null, $goodsField = null, $arrBind = null, $dataArray = false, $usePage = false)
    {
		
		//2022.05.23 민트웹 해당 부분은 상품 설정기본 배송비가 나오도록 하였으나 업체 요청에 의해 정기결제 상품인경우 정기결제에 설정된 상품배송정보로 대체처리함 시작
		$tmp=parent::getGoodsInfo($goodsNo, $goodsField, $arrBind, $dataArray, $usePage);


		$db=\App::load(\DB::class);
		$row=$db->fetch("select useSubscription from ".DB_GOODS." where goodsNo='{$goodsNo}'");

		if($row['useSubscription']==1){
			$Obj= new \Component\Subscription\Subscription();
			$tmpGoodsNo[]=$goodsNo;
			$cfg = $Obj->getCfg($tmpGoodsNo);

		
			$server=\Request::server()->toArray();

		

			if($server['REDIRECT_URL']=="/goods/goods_view.php"){

				
				
				if(!empty($cfg['firstDeliverySno']))
					$tmp['deliverySno']=$cfg['firstDeliverySno'];

			}
			
			
		}

			
		return $tmp;
		//2022.05.23 민트웹 해당 부분은 상품 설정기본 배송비가 나오도록 하였으나 업체 요청에 의해 정기결제 상품인경우 정기결제에 설정된 상품배송정보로 대체처리함 종료

	}

	//2022.06.17민트웹
    public function getGoodsView($goodsNo)
    {
		$goodsView = parent::getGoodsView($goodsNo);

		//if(\Request::getRemoteAddress()=="182.216.219.50"){
			

			if(!empty($goodsView['addViewFl'])){
			
				$addViewFl = json_decode(stripslashes($goodsView['addViewFl']));

				$addView=[];
				foreach($addViewFl as $key =>$v){
					$addView[$key]=$v;
				}
				$goodsView['addViewFl']=$addView;
			}
			
		//}

		return $goodsView;
	}

	public function recomGoodsMileageView($getValue)
	{
		$page = \App::load('\\Component\\Page\\Page', $getValue['page']);
        $page->page['list'] = $getValue['pageNum']; // 페이지당 리스트 수
        $page->block['cnt'] = Request::isMobile() ? 5 : 10; // 블록당 리스트 개수
        $page->setPage();
        $page->setUrl(\Request::getQueryString());
		
		//g.recomSubMileage, g.recomNoMileageUnit
		$this->db->strField = 'g.goodsNm, g.goodsNo,	g.recomNoMileage, g.recomNoMileageUnit, g.recomMileageFl'; 

		$join[] = 'LEFT JOIN es_goodsSearch g1 ON g1.goodsNo=g.goodsNo';
		$this->db->strJoin = implode('', $join);
		
		if(Request::isMobile()) {
			$arrWhere[] = 'g.goodsDisplayMobileFl="y"';
		}else {
			$arrWhere[] = 'g.goodsDisplayFl="y"';
		}

		$arrWhere[] = '(g.recomMileageFl = "c" OR (g.recomMileageFl = "g" AND g.recomSubMileage IS NOT NULL AND g.recomSubMileage != "0" AND g.recomNoMileage IS NOT NULL AND g.recomNoMileage != "0"))';
		$arrWhere[] = 'g1.delFl = "n"';
        $this->db->strWhere = implode(' AND ', $arrWhere);
        $this->db->strOrder = $sort;
		$this->db->strLimit = $page->recode['start'] . ',' . $getValue['pageNum'];
		
		$query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM es_goods g' . implode(' ', $query);
		$data = $this->db->query_fetch($strSQL, $this->arrBind);
		unset($this->arrBind, $this->arrWhere);

		/* 검색 count 쿼리 */
		$totalCountSQL = 'SELECT COUNT(g.goodsNo) AS totalCnt FROM es_goods as g '.implode('', $join).'  WHERE '.implode(' AND ', $arrWhere);
		$dataCount = $this->db->slave()->query_fetch($totalCountSQL, $this->arrBind,false);
		unset($this->arrBind, $this->arrWhere);

		// 검색 레코드 수
		$page->recode['total'] = $dataCount['totalCnt']; //검색 레코드 수
		$page->setPage();
		
		return $data;
	}
	//public function getWgGoodsView($goodsNo)
    //{
    //    $sql = 'SELECT * FROM es_goods WHERE goodsNo='.$goodsNo;
	//	$getData = $this->db->query_fetch($sql, null, false);
    //    return $getData;
    //}
}