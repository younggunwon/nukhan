<?php

namespace Controller\Admin\Goods;

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;
use Component\Database\DBTableField;

/**
* 정기결제 상품관련 DB 처리 
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
			switch ($in['mode']) {
				/* 정기배송 상품설정 S */
				case "update_goods_set" : 
					if (empty($in['arrGoodsNo']))
						throw new AlertOnlyException("수정할 상품을 선택하세요.");
					
					foreach ($in['arrGoodsNo'] as $goodsNo) {
						$param = [
							'useSubscription = ?',
						];
						
						$bind = [
							'ii',
							$in['useSubscription'][$goodsNo]?1:0,
							$goodsNo,
						];
						
						$db->set_update_db(DB_GOODS, $param, "goodsNo = ?", $bind);
					}
					
					return $this->layer("수정되었습니다.");
					break;
				/* 정기배송 상품설정 E */
				/* 정기배송 상품 개별 설정 S */
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
						'min_order',
						// 2024-01-19 wg-eric 상품 전환 추가
						'wgAutoChangeGoodsFl',
						'wgAutoChangeGoodsDay',
						'wgAutoChangeGoodsNo',
						'wgAutoChangeOptionSno',
					];
					// 2024-01-19 wg-eric 상품 전환 사용안하면 데이터 null
					if($in['wgAutoChangeGoodsFl'] == 'n') {
						$in['wgAutoChangeGoodsDay'] = null;
						$in['wgAutoChangeGoodsNo'] = null;
					} else {
						if($in['wgAutoChangeGoodsDay'] !== false) {
							if($in['wgAutoChangeGoodsDay'] < 1) {
								//throw new AlertOnlyException("상품 자동 전환은 최소 1일 부터 가능합니다.");
							}
						}
					}

					//배송횟수 미입력시 자동2회로 설정됨
					if(empty($in['deliveryEa']))
						$in['deliveryEa']=2;
					
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

					$bind = [
						'iisssssiiisiii',
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
						gd_isset($in['min_order'],0),
						// 2024-01-19 wg-eric 상품 전환 추가
						gd_isset($in['wgAutoChangeGoodsFl']),
						gd_isset($in['wgAutoChangeGoodsDay']),
						gd_isset($in['wgAutoChangeGoodsNo']),
						gd_isset($in['wgAutoChangeOptionSno']),
					];
					
					$db->set_insert_db("wm_subGoodsConf", $params, $bind, "y");
				
					$param = [
						'minOrderCnt = ?',
						'maxOrderCnt = ?',
						'togetherGoodsNo = ?',
						'showSubscriptionLink = ?',
						'subLinkGoodsNo = ?',
						'subLinkGoodsNo2 = ?',
						'subLinkGoodsNo3 = ?',
						'subLinkGoodsNo4 = ?',
						'subLinkGoodsNo5 = ?',
						'subLinkButtonNm = ?',
						
					];
					
					$bind = [
						'iissiiiiisi',
						gd_isset($in['minOrderCnt'], 1),
						gd_isset($in['maxOrderCnt'], 0),
						$in['togetherGoodsNo'],
						$in['showSubscriptionLink'],
						$in['subLinkGoodsNo'],
						$in['subLinkGoodsNo2'],
						$in['subLinkGoodsNo3'],
						$in['subLinkGoodsNo4'],
						$in['subLinkGoodsNo5'],
						$in['subLinkButtonNm'],
						$in['goodsNo'],
					];

					
					$affectedRows = $db->set_update_db(DB_GOODS, $param, "goodsNo = ?", $bind);
					if ($affectedRows <= 0) 
						throw new AlertOnlyException("저장에 실패하였습니다.");
					

					return $this->layer("저장되었습니다.");
					break;
				/* 정기배송 상품 개별 설정 E */

				case "update_goods_config_list":
					/*정기배송 상품 일괄설정 S*/

					if (empty($in['goodsNoList']))
						throw new AlertOnlyException("잘못된 접근입니다.");
					
					$goodsNo_List = explode(",",$in['goodsNoList']);

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

					foreach($goodsNo_List as $goodsNo){
						$db->set_delete_db("wm_subGoodsConf", "goodsNo = ?", ["i", $goodsNo]);
						
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
							'min_order',
						];

						//배송횟수 미입력시 자동2회로 설정됨
						if(empty($in['deliveryEa']))
							$in['deliveryEa']=2;
						
						$bind = [
							'iisssssiii',
							$goodsNo,
							$in['useConfig']?1:0,
							$in['deliveryYoils']?implode(",", $in['deliveryYoils']):"",
							//$in['deliveryCycle']?implode(",", $in['deliveryCycle']):"",
							$in['deliveryCycle'],
							$in['deliveryEa'],
							$in['deliveryEaDiscount'],
							gd_isset($in['deliveryEaDiscountType'], "cycle"),
							$in['useFirstDelivery']?1:0,
							gd_isset($in['firstDeliverySno'], 0),
							gd_isset($in['min_order'],0),
						];

						
						
						$db->set_insert_db("wm_subGoodsConf", $params, $bind, "y");
						
						$param = [
							'minOrderCnt = ?',
							'maxOrderCnt = ?',
							'togetherGoodsNo = ?',
							'showSubscriptionLink = ?',
							'subLinkGoodsNo = ?',
							'subLinkGoodsNo2 = ?',
							'subLinkGoodsNo3 = ?',
							'subLinkGoodsNo4 = ?',
							'subLinkGoodsNo5 = ?',
							'subLinkButtonNm = ?',
							'useSubscription=?',
							
						];
						
						$bind = [
							'iissiiiiisii',
							gd_isset($in['minOrderCnt'], 1),
							gd_isset($in['maxOrderCnt'], 0),
							$in['togetherGoodsNo'],
							$in['showSubscriptionLink'],
							$in['subLinkGoodsNo'],
							$in['subLinkGoodsNo2'],
							$in['subLinkGoodsNo3'],
							$in['subLinkGoodsNo4'],
							$in['subLinkGoodsNo5'],
							$in['subLinkButtonNm'],
							1,
							$goodsNo,
						];
						
						$affectedRows = $db->set_update_db(DB_GOODS, $param, "goodsNo = ?", $bind);
						//if ($affectedRows <= 0) 
						//	throw new AlertOnlyException("저장에 실패하였습니다.");
						



						

					}
					

					return $this->layer("저장되었습니다.");
					/*정기배송 상품 일괄설정 E*/
					break;
			}
		} catch (AlertOnlyException $e) {
			throw $e;
		}
		exit;
	}
}