<?php

namespace Controller\Admin\Policy;

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;
use Component\Database\DBTableField;

/**
* 정기결제 관련 DB 처리 
*
* @author webnmobile
*/
class IndbSubscritpionController extends \Controller\Admin\Controller
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			$db = App::load(\DB::class);
			$subscrition = App::load(\Component\Subscription\Subscription::class);
			switch ($in['mode']) {
				/* 정기결제 설정 S */
				case "update_config" : 
					
				//$in['deliveryCycle'] = $in['deliveryCycle']?implode(",", $in['deliveryCycle']):"";
					$deliveryCycle=[];
					if(!empty($in['day'])){
						
						$day = explode(",",$in['day']);

						foreach($day as $dk=>$dv){

							$deliveryCycle[]=$dv."_day";
						}
					}
					if(!empty($in['week'])){
						
						$week = explode(",",$in['week']);
						
						foreach($week as $dk=>$dv){

							$deliveryCycle[]=$dv."_week";
						}
					}
					if(!empty($in['month'])){
						
						$month = explode(",",$in['month']);
						foreach($month as $dk=>$dv){

							$deliveryCycle[]=$dv."_month";
						}
						
					}

					$in['deliveryCycle']=implode(",", $deliveryCycle);

					$in['deliveryYoils'] = $in['deliveryYoils']?implode(",", $in['deliveryYoils']):"";
					$in['useFirstDelivery'] = $in['useFirstDelivery']?1:0;
					$in['firstDeliverySno'] = gd_isset($in['firstDeliverySno'], 0);

					
					$arrBind = $db->get_binding(DBTableField::tableWmSubscriptionConf(), $in, "update", array_keys($in));

					$affectedRows = $db->set_update_db("wm_subConf", $arrBind['param'], "1", $arrBind['bind']);
					if ($affectedRows <= 0)
						throw new AlertOnlyException("저장에 실패하였습니다.");

					
					$data = addslashes(json_encode($in));
					$db->query("insert into wm_sub_config_log set data='{$data}'");
					
					return $this->layer("저장되었습니다.");
					break;
				/* 정기결제 설정 E */
				/* 정기결제 상품별 설정 S */
				case "update_goods_config" : 
					if (empty($in['goodsNo']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					$db->set_delete_db("wm_subGoodsConf", "goodsNo = ?", ["i", $in['goodsNo']]);
					
					$params = [
						'goodsNo',
						'useConfig',
						'deliveryYoils',
						'deliveryCycle',
						'deliveryEa',
						'deliveryEaDiscount',
						'deliveryEaDiscountType',
						'useFirstDelivery',
						'firstDeliverySno',
					];
					
					$bind = [
						'iisssssii',
						$in['goodsNo'],
						$in['useConfig']?1:0,
						$in['deliveryYoils']?implode(",", $in['deliveryYoils']):"",
						//$in['deliveryCycle']?implode(",", $in['deliveryCycle']):"",
						$in['deliveryCycle'],
						$in['deliveryEa'],
						$in['deliveryEaDiscount'],
						gd_isset($in['deliveryEaDiscountType'], "cycle"),
						$in['useFirstDelivery']?1:0,
						gd_isset($in['firstDeliverySno'], 0),
					];
					
					$db->set_insert_db("wm_subGoodsConf", $params, $bind, "y");
					$affectedRows = $db->affected_rows();
					if ($affectedRows <= 0)
						throw new AlertOnlyException("저장에 실패하였습니다.");
					
					return $this->layer("저장되었습니다.");
					break;
				/* 정기결제 상품별 설정 E */
				/* 정기결제 휴무일 S */
				case "update_holiday" : 
					if (empty($in['stamp']))
						throw new AlertOnlyException("업데이트할 휴무일을 선택하세요.");
					
					foreach ($in['stamp'] as $stamp) {
						$db->set_delete_db("wm_holiday", "stamp = ?", ["i", $stamp]);
						
						$rstamp = $in['replaceDate'][$stamp]?strtotime($in['replaceDate'][$stamp]):0;
						
						$param = [
							'stamp',
							'replaceStamp', 
							'isHoliday',
							'memo',
						];
						
						$bind = [
							'iiis',
							$stamp,
							$rstamp,
							$in['isHoliday'][$stamp]?1:0,
							$in['memo'][$stamp],
							
						];
						
						$db->set_insert_db("wm_holiday", $param, $bind, "y");
					}
					
					return $this->layer("수정되었습니다.");
					break;
				/* 정기결제 휴무일 E */
				/* 정기결제 상품설정 S */
				case "update_goods_set" : 
					if (empty($in['arrGoodsNo']))
						throw new AlertOnlyException("수정할 상품을 선택하세요.");
					
					foreach ($in['arrGoodsNo'] as $goodsNo) {
						$param = [
							'useSubscription = ?',
							'togetherGoodsNo = ?',
							'showSubscriptionLink = ?',
							'subLinkGoodsNo = ?',
							'subLinkButtonNm = ?',
						];
						
						$bind = [
							'isiisi',
							$in['useSubscription'][$goodsNo]?1:0,
							$in['togetherGoodsNo'][$goodsNo],
							$in['showSubscriptionLink'][$goodsNo]?1:0,
							$in['subLinkGoodsNo'][$goodsNo],
							$in['subLinkButtonNm'][$goodsNo],
							$goodsNo,
						];
						
						$db->set_update_db(DB_GOODS, $param, "goodsNo = ?", $bind);
					}
					
					return $this->layer("수정되었습니다.");
					
					break;
				/* 정기결제 상품설정 E */
				/* 사은품 등록(공통) S */
				case "register_gift" : 
					if (empty($in['orderCnt']))
						throw new AlertOnlyException("회차를 입력하세요.");
					
					if (!is_numeric($in['orderCnt']))
						throw new AlertOnlyException("회차는 숫자로 입려 하세요.");
					
					if (empty($in['goodsNo']))
						throw new AlertOnlyException("사은품을 선택해주세요.");
					
					foreach ($in['goodsNo'] as $goodsNo) {
						$sql = "SELECT COUNT(*) as cnt FROM wm_subGifts WHERE orderCnt = ? AND goodsNo = ?";
						$row = $db->query_fetch($sql, ["ii", $in['orderCnt'], $goodsNo], false);
						if ($row['cnt'] > 0) continue;
						
						$param = [
							'orderCnt',
							'goodsNo',
						];
						
						$bind = [
							'ii',
							$in['orderCnt'],
							$goodsNo,
						];
						
						$db->set_insert_db("wm_subGifts", $param, $bind, "y");
					}
					
					return $this->layer("등록되었습니다.");
					break;
				/* 사은품 등록(공통) E */
				/* 사은품 수정(공통) S */
				case "update_gift" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("수정할 상품을 선택하세요.");
					
					foreach ($in['idx'] as $idx) {
						$param = [
							'listOrder = ?',
							'isOpen = ?',
						];
						
						$bind = [
							'iii',
							gd_isset($in['listOrder'][$idx], 0),
							$in['isOpen'][$idx]?1:0,
							$idx,
						];
						
						$db->set_update_db("wm_subGifts", $param, "idx = ?", $bind);
					}
					
					return $this->layer("수정되었습니다.");
					break;
				/* 사은품 수정(공통) E */
				/* 사은품 삭제(공통) S */
				case "delete_gift" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("삭제할 상품을 선택하세요.");
					
					foreach ($in['idx'] as $idx) {
						$db->set_delete_db("wm_subGifts", "idx = ?", ["i", $idx]);
					}
					
					return $this->layer("삭제되었습니다.");
					break;
				/* 사은품 삭제(공통) E */
				/* 상품별 사은품 등록 S */
				case "register_goods_gift" : 
					if (empty($in['rootGoodsNo']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					if (empty($in['orderCnt']))
						throw new AlertOnlyException("회차를 입력하세요.");
					
					if (!is_numeric($in['orderCnt']))
						throw new AlertOnlyException("회차는 숫자로 입려 하세요.");
					
					if (empty($in['goodsNo']))
						throw new AlertOnlyException("사은품을 선택해주세요.");
					
					foreach ($in['goodsNo'] as $goodsNo) {
						$sql = "SELECT COUNT(*) as cnt FROM wm_subGoodsGifts WHERE rootGoodsNo = ? AND orderCnt = ? AND goodsNo = ?";
						$row = $db->query_fetch($sql, ["iii", $in['rootGoodsNo'], $in['orderCnt'], $goodsNo], false);
						if ($row['cnt'] > 0) continue;
						
						$param = [
							'rootGoodsNo',
							'orderCnt',
							'goodsNo',
						];
						
						$bind = [
							'iii',
							$in['rootGoodsNo'],
							$in['orderCnt'],
							$goodsNo,
						];
						
						$db->set_insert_db("wm_subGoodsGifts", $param, $bind, "y");
					}
					
					return $this->layer("등록되었습니다.");
					break;
				/* 상품별 사은품 등록 E */
				/* 상품별 사은품 수정 S */
				case "update_goods_gift" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("수정할 상품을 선택하세요.");
					
					foreach ($in['idx'] as $idx) {
						$param = [
							'listOrder = ?',
							'isOpen = ?',
						];
						
						$bind = [
							'iii',
							gd_isset($in['listOrder'][$idx], 0),
							$in['isOpen'][$idx]?1:0,
							$idx,
						];
						
						$db->set_update_db("wm_subGoodsGifts", $param, "idx = ?", $bind);
					}
					
					return $this->layer("수정되었습니다.");
					break;
				/* 상품별 사은품 수정 E */
				/* 상품별 사은품 삭제 S */
				case "delete_goods_gift" : 
					if (empty($in['idx']))
						throw new AlertOnlyException("삭제할 상품을 선택하세요.");
					
					foreach ($in['idx'] as $idx) {
						$db->set_delete_db("wm_subGoodsGifts", "idx = ?", ["i", $idx]);
					}
					
					return $this->layer("삭제되었습니다.");
					break;
				/* 상품별 사은품 삭제 E */
			}
		} catch (AlertOnlyException $e) {
			throw $e;
		}
		exit;
	}
}