<?php

namespace Component\Order;

use App;
use Component\Bankda\BankdaOrder;
use Component\Database\DBTableField;
use Component\Delivery\Delivery;
use Component\Deposit\Deposit;
use Component\Godo\MyGodoSmsServerApi;
use Component\Godo\NaverPayAPI;
use Component\Mail\MailMimeAuto;
use Component\Mall\Mall;
use Component\Member\Manager;
use Component\Mileage\Mileage;
use Component\Naver\NaverPay;
use Component\Sms\Code;
use Component\Validator\Validator;
use Exception;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\Except;
use Framework\Debug\Exception\LayerNotReloadException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\StaticProxy\Proxy\UserFilePath;
use Framework\Utility\ArrayUtils;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\NumberUtils;
use Framework\Utility\StringUtils;
use Globals;
use LogHandler;
use Request;
use Session;
use Vendor\Spreadsheet\Excel\Reader as SpreadsheetExcelReader;
use Component\Page\Page;

class OrderAdmin extends \Bundle\Component\Order\OrderAdmin
{
	protected function _setSearch($searchData, $searchPeriod = 7, $isUserHandle = false)
	{
		$result = parent::_setSearch($searchData, $searchPeriod, $isUserHandle);
		
		/* 추가 검색 처리 S */
		/* 정기결제 주문건 체크 */
		$this->search['isSubscription'] = $searchData['isSubscription'];
		if ($searchData['isSubscription']) {
			$this->arrWhere[] = "o.pgName='sub'";
		}
		/* 추가 검색 처리 E */

		/* 정기결제 주문건 체크 */
		$this->search['isNormalOrder'] = $searchData['isNormalOrder'];
		if ($searchData['isNormalOrder']) {
			$this->arrWhere[] = "o.pgName!='sub'";
		}
		/* 추가 검색 처리 E */

		/* 2025-02-12 wg-john 추가상품 포함검색 여부 추가 */
		
		$this->search['addGoodsIncludeFl'] = gd_isset($searchData['addGoodsIncludeFl'], 'n');
		$this->checked['addGoodsIncludeFl'][$this->search['addGoodsIncludeFl']] = 'checked';
		if (in_array($this->search['key'], ['og.goodsNm', 'og.goodsNo']) && $searchData['addGoodsIncludeFl'] == 'n') {
			//$this->arrWhere[] = "og.goodsType !='addGoods'";
			$sql = "SELECT MIN(sno) as sno FROM es_orderGoods GROUP BY orderNo ORDER BY sno DESC";
			$orderGoodsSno = $this->db->query_fetch($sql);
			$orderGoodsSno = array_column($orderGoodsSno, 'sno');
			$this->arrWhere[] = "og.sno IN (".implode(',', $orderGoodsSno).")";
		}
		
		$this->search['searchReverseFl'] = gd_isset($searchData['searchReverseFl'], '');
		$this->checked['searchReverseFl'][$this->search['searchReverseFl']] = 'checked';
		if(in_array($this->search['key'], ['og.goodsNm', 'og.goodsNo']) && $searchData['searchReverseFl'] == 'y') {
			foreach($this->arrWhere as $key => $val) {
				$this->arrWhere[$key] = str_replace('og.goodsNm LIKE', 'og.goodsNm NOT LIKE', $val);
				$this->arrWhere[$key] = str_replace('og.goodsNo =', 'og.goodsNo !=', $this->arrWhere[$key]);
			}
		}
		/* 추가 검색 처리 E */

		// 2024-01-23 wg-eric 결제상태 검색 추가 - start
		$this->search['wgOrderStatus'] = $searchData['wgOrderStatus'];
		if ($searchData['wgOrderStatus']) {
			foreach($searchData['wgOrderStatus'] as $key => $val) {
				if($val == 'p') {
					// 결제완료
					$orderStatusWhere1[] = "(og.orderStatus like 'p%' || og.orderStatus like 'g%' || og.orderStatus like 'd%' || og.orderStatus like 's%')";
					//$orderStatusWhere1[] = "og.orderStatus not like 'o%' && og.orderStatus not like 'c%' && og.orderStatus not like 'f%'";
				} else if ($val == 'c' || $val == 'b' || $val == 'r') {
					// 취소, 반품, 환불
					$orderStatusWhere2[] = "(og.orderStatus like '".$val."%')";
				} else if($val == 'o') {
					// 미결제
					$orderStatusWhere1[] = "(og.orderStatus like 'o%' || og.orderStatus like 'c%')";
				} else {
					$orderStatusWhere1[] = "(og.orderStatus like '".$val."')";
				}

				$this->checked['wgOrderStatus'][$val] = 'checked="checked"';
			}

			if($orderStatusWhere1) $this->arrWhere[] = '(' . implode(' || ', $orderStatusWhere1) . ')';
			if($orderStatusWhere2) $this->arrWhere[] = '(' . implode(' || ', $orderStatusWhere2) . ')';
		}
		// 2024-01-23 wg-eric 결제상태 검색 추가 - end
	}

    public function getOrderListForAdmin($searchData, $searchPeriod, $isUserHandle = false)
    {
        if(trim($searchData['orderAdminGridMode']) !== ''){
            //주문리스트 그리드 설정
            $orderAdminGrid = \App::load('\\Component\\Order\\OrderAdminGrid');
            $this->orderGridConfigList = $orderAdminGrid->getSelectOrderGridConfigList($searchData['orderAdminGridMode']);
        }

        // --- 검색 설정
        $this->_setSearch($searchData, $searchPeriod, $isUserHandle);

        // 주문번호별로 보기
        $isDisplayOrderGoods = ($this->search['view'] !== 'order');// view모드가 orderGoods & orderGoodsSimple이 아닌 경우 true
        $this->search['searchPeriod'] = gd_isset($searchData['searchPeriod']);

        // --- 페이지 기본설정
        gd_isset($searchData['page'], 1);
        gd_isset($searchData['pageNum'], 20);
        $page = \App::load('\\Component\\Page\\Page', $searchData['page'],0,0,$searchData['pageNum']);
        $page->setCache(true)->setUrl(\Request::getQueryString()); // 페이지당 리스트 수

        // 주문상태 정렬 예외 케이스 처리
        if ($searchData['sort'] == 'og.orderStatus asc') {
            $searchData['sort'] = 'case LEFT(og.orderStatus, 1) when \'o\' then \'01\' when \'p\' then \'02\' when \'g\' then \'03\' when \'d\' then \'04\' when \'s\' then \'05\' when \'e\' then \'06\' when \'b\' then \'07\' when \'r\' then \'08\' when \'c\' then \'09\' when \'f\' then \'10\' else \'11\' end';
        } elseif ($searchData['sort'] == 'og.orderStatus desc') {
            $searchData['sort'] = 'case LEFT(og.orderStatus, 1) when \'f\' then \'01\' when \'c\' then \'02\' when \'r\' then \'03\' when \'b\' then \'04\' when \'e\' then \'05\' when \'s\' then \'06\' when \'d\' then \'07\' when \'g\' then \'08\' when \'p\' then \'09\' when \'o\' then \'10\' else \'11\' end';
        }

        if($isDisplayOrderGoods){
            if(trim($searchData['sort']) !== ''){
                $orderSort = $searchData['sort'] . ', og.orderDeliverySno asc';
            }
            else {
                if($this->isUseMultiShipping === true){
                    $orderSort = $this->orderGoodsMultiShippingOrderBy;
                }
                else {
                    $orderSort = $this->orderGoodsOrderBy;
                }
            }
        }
        else {
            $orderSort = gd_isset($searchData['sort'], 'og.orderNo desc');
        }

        //상품준비중 리스트에서 묶음배송 정렬 기준
        if(preg_match("/packetCode/", $orderSort)){
            if(preg_match("/desc/", $orderSort)){
                $orderSort = "oi.packetCode desc, og.orderNo desc";
            }
            else {
                $orderSort = "oi.packetCode desc, og.orderNo asc";
            }
        }
        //복수배송지 사용시 배송지별 묶음
        if($this->isUseMultiShipping === true){
            if(!preg_match("/orderInfoCd/", $orderSort)){
                $orderSort = $orderSort . ", oi.orderInfoCd asc";
            }
        }

        $arrIncludeOh = [
            'handleMode',
            'beforeStatus',
            'refundMethod',
            'handleReason',
            'handleDetailReason',
            'regDt AS handleRegDt',
            'handleDt',
        ];
        $arrIncludeOi = [
            'orderName',
            'receiverName',
            'orderMemo',
            'orderCellPhone',
            'packetCode',
            'smsFl',
        ];


        $tmpField[] = ['oh.regDt AS handleRegDt'];
        $tmpField[] = DBTableField::setTableField('tableOrderHandle', $arrIncludeOh, null, 'oh');
        $tmpField[] = DBTableField::setTableField('tableOrderInfo', $arrIncludeOi, null, 'oi');
        $tmpField[] = ['oi.sno AS orderInfoSno'];

        // join 문
        $join[] = ' LEFT JOIN ' . DB_ORDER . ' o ON o.orderNo = og.orderNo ';
        $join[] = ' LEFT JOIN ' . DB_ORDER_HANDLE . ' oh ON og.handleSno = oh.sno AND og.orderNo = oh.orderNo ';
        $join[] = ' LEFT JOIN ' . DB_ORDER_DELIVERY . ' od ON og.orderDeliverySno = od.sno ';
		$join[] = ' LEFT JOIN ' . DB_MEMBER_HACKOUT_ORDER . ' mho ON og.orderNo = mho.orderNo ';
        $join[] = ' LEFT JOIN ' . DB_ORDER_INFO . ' oi ON (og.orderNo = oi.orderNo)   
                    AND (CASE WHEN od.orderInfoSno > 0 THEN od.orderInfoSno = oi.sno ELSE oi.orderInfoCd = 1 END)';

        if(($this->search['key'] =='all' && empty($this->search['keyword']) === false)  || $this->search['key'] =='sm.companyNm' || strpos($orderSort, "sm.companyNm ") !== false || $this->multiSearchScmJoinFl) {
            $join[] = ' LEFT JOIN ' . DB_SCM_MANAGE . ' sm ON og.scmNo = sm.scmNo ';

        }

        if((($this->search['key'] =='all' && empty($this->search['keyword']) === false)  || $this->search['key'] =='pu.purchaseNm') && gd_is_plus_shop(PLUSSHOP_CODE_PURCHASE) === true && gd_is_provider() === false || $this->multiSearchPurchaseJoinFl) {
            $join[] = ' LEFT JOIN ' . DB_PURCHASE . ' pu ON og.purchaseNo = pu.purchaseNo ';
        }

        if(($this->search['key'] =='all' && empty($this->search['keyword']) === false) || $this->search['key'] =='m.nickNm' || $this->search['key'] =='m.memId' || ($this->search['memFl'] =='y' && empty($this->search['memberGroupNo']) === false ) || $this->multiSearchMemberJoinFl) {
            $join[] = ' LEFT JOIN ' . DB_MEMBER . ' m ON o.memNo = m.memNo AND m.memNo > 0 ';
        }

        //상품 브랜드 코드 검색
        if(empty($this->search['brandCd']) === false || empty($this->search['brandNoneFl'])=== false) {
            $join[] = ' LEFT JOIN ' . DB_GOODS . ' as g ON og.goodsNo = g.goodsNo ';
        }

        //택배 예약 상태에 따른 검색
        if ($this->search['invoiceReserveFl']) {
            $join[] = ' LEFT JOIN ' . DB_ORDER_GODO_POST . ' ogp ON ogp.invoiceNo = og.invoiceNo ';
        }

        // 쿠폰검색시만 join
        if ($this->search['couponNo'] > 0) {
            $join[] = ' LEFT JOIN ' . DB_ORDER_COUPON . ' oc ON o.orderNo = oc.orderNo ';
            $join[] = ' LEFT JOIN ' . DB_MEMBER_COUPON . ' mc ON mc.memberCouponNo = oc.memberCouponNo ';
        }

        // 반품/교환/환불신청 사용에 따른 리스트 별도 처리 (조건은 검색 메서드 참고)
        if ($isUserHandle) {

            $arrIncludeOuh = [
                'sno',
                'userHandleMode',
                'userHandleFl',
                'userHandleGoodsNo',
                'userHandleGoodsCnt',
                'userHandleReason',
                'userHandleDetailReason',
                'adminHandleReason',
            ];
            $tmpField[] = ['ouh.regDt AS userHandleRegDt','ouh.sno AS userHandleNo'];
            $tmpField[] = DBTableField::setTableField('tableOrderUserHandle', $arrIncludeOuh, null, 'ouh');
            $joinOrderStatusArray = $this->getExcludeOrderStatus($this->orderStatus, $this->statusUserClaimRequestCode);
            $join[] = ' LEFT JOIN ' . DB_ORDER_USER_HANDLE . ' ouh ON (og.userHandleSno = ouh.sno || (og.sno = ouh.userHandleGoodsNo && og.orderStatus IN (\'' . implode('\',\'', array_keys($joinOrderStatusArray)) . '\')))';
        }
        // @kookoo135 고객 클레임 신청 주문 제외
        if ($this->search['userHandleViewFl'] == 'y') {
            if (!$isDisplayOrderGoods) {
                $this->arrWhere[] = ' NOT EXISTS (SELECT 1 FROM ' . DB_ORDER_USER_HANDLE . ' WHERE o.orderNo = orderNo AND userHandleFl = \'r\')';
            } else {
                $this->arrWhere[] = ' NOT EXISTS (SELECT 1 FROM ' . DB_ORDER_USER_HANDLE . ' WHERE (og.userHandleSno = sno OR og.sno = userHandleGoodsNo) AND userHandleFl = \'r\')';
            }
        }

        // 상품º주문번호별 메모 검색시
        if($this->search['withAdminMemoFl'] == 'y'){
            $join[] = ' LEFT JOIN ' . DB_ADMIN_ORDER_GOODS_MEMO . ' aogm ON o.orderNo = aogm.orderNo ';
        }

        // 쿼리용 필드 합침
        $tmpKey = array_keys($tmpField);
        $arrField = [];
        foreach ($tmpKey as $key) {
            $arrField = array_merge($arrField, $tmpField[$key]);
        }
        unset($tmpField, $tmpKey);

        // 현 페이지 결과
        $this->db->strField = 'og.sno,og.orderNo,og.goodsNo,og.scmNo ,og.mallSno ,og.purchaseNo ,o.memNo, o.trackingKey, o.orderTypeFl, o.appOs, o.pushCode ,' . implode(', ', $arrField) . ',og.orderDeliverySno, o.pgName';
        // addGoods 필드 변경 처리 (goods와 동일해서)

        $this->db->strJoin = implode('', $join);
        $this->db->strWhere = implode(' AND ', gd_isset($this->arrWhere));
        $this->db->strOrder = $orderSort;
        if (!$isDisplayOrderGoods) {
            if($searchData['statusMode'] === 'o'){
                // 입금대기리스트 > 주문번호별 에서 '주문상품명' 을 입금대기 상태의 주문상품명만으로 노출시키기 위해 개수를 구함
                $this->db->strField .= ', SUM(IF(LEFT(og.orderStatus, 1)=\'o\', 1, 0)) AS noPay';
            }
            $this->db->strField .= ', o.regDt, SUM(IF(LEFT(og.orderStatus, 1)=\'o\' OR LEFT(og.orderStatus, 1)=\'p\' OR LEFT(og.orderStatus,1)=\'g\', 1, 0)) AS noDelivery';
            $this->db->strField .= ', SUM(IF(LEFT(og.orderStatus, 1)=\'d\' AND og.orderStatus != \'d2\', 1, 0)) AS deliverying';
            $this->db->strField .= ', SUM(IF(og.orderStatus=\'d2\' OR LEFT(og.orderStatus, 1)=\'s\', 1, 0)) AS deliveryed';
            $this->db->strField .= ', SUM(IF(LEFT(og.orderStatus, 1)=\'c\', 1, 0)) AS cancel';
            $this->db->strField .= ', SUM(IF(LEFT(og.orderStatus, 1)=\'e\', 1, 0)) AS exchange';
            $this->db->strField .= ', SUM(IF(LEFT(og.orderStatus, 1)=\'b\', 1, 0)) AS back';
            $this->db->strField .= ', SUM(IF(LEFT(og.orderStatus, 1)=\'r\', 1, 0)) AS refund';

            $this->db->strGroup = 'og.orderNo';
        } else if($this->search['withAdminMemoFl'] == 'y'){
            // 상품º주문번호별 메모 검색시
            $this->db->strGroup = 'og.sno';
        }

        gd_isset($searchData['useStrLimit'], true);
        if ($searchData['useStrLimit']) {
            $this->db->strLimit = $page->recode['start'] . ',' . $searchData['pageNum'];
        }

        $query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_ORDER_GOODS . ' og ' . implode(' ', $query);
        $getData = $this->db->query_fetch($strSQL, $this->arrBind);

        // 검색 레코드 수
        $query['group'] = 'GROUP BY og.orderNo';
        unset($query['order']);
        if($page->hasRecodeCache('total') === false) {
            if (Manager::isProvider()) {
                // 검색된 주문의 개수
                $total = $this->db->query_fetch('SELECT COUNT(distinct(og.sno)) AS cnt FROM ' . DB_ORDER_GOODS . ' og ' . implode(' ', str_ireplace('limit ' . $page->recode['start'] . ',' . $searchData['pageNum'], '', $query)), $this->arrBind, true);

                // 검색된 주문 총 배송비 금액
                $priceDeliveryQuery = $query;
                if(trim($query['group']) !== ''){
                    $priceDeliveryQuery['group'] = $query['group'] . ', og.orderDeliverySno';
                }
                else {
                    $priceDeliveryQuery['group'] = 'GROUP BY og.orderNo, og.orderDeliverySno';
                }

                $providerPriceQueryArr = [];
                $providerPriceQueryArr[] = 'SELECT';
                $providerPriceQueryArr[] = '(od.realTaxSupplyDeliveryCharge + od.realTaxVatDeliveryCharge + od.realTaxFreeDeliveryCharge + od.divisionDeliveryUseDeposit + od.divisionDeliveryUseMileage) AS deliveryPrice';
                $providerPriceQueryArr[] = 'FROM ' . DB_ORDER_GOODS . ' og';
                $providerPriceQueryArr[] = implode(' ', str_ireplace('limit ' . $page->recode['start'] . ',' . $searchData['pageNum'], '', $priceDeliveryQuery));
                $providerPriceQuery = implode(' ', $providerPriceQueryArr);
                $providerTotalDeliveryPrice = $this->db->query_fetch($providerPriceQuery, $this->arrBind, true);
                if(count($total) > 0){
                    $total[0]['price'] += array_sum(array_column($providerTotalDeliveryPrice, 'deliveryPrice'));
                }

                // 검색된 주문 총 상품 금액
                $priceQuery = $query;
                if(trim($query['where']) !== ''){
                    $priceQuery['where'] = str_replace("WHERE ", "WHERE og.orderStatus != 'r3' AND ", $query['where']);
                }
                else {
                    $priceQuery['where'] = "WHERE og.orderStatus != 'r3'";
                }

                $providerPriceQueryArr = [];
                $providerPriceQueryArr[] = 'SELECT';
                $providerPriceQueryArr[] = 'SUM((og.goodsPrice + og.optionPrice + og.optionTextPrice) * og.goodsCnt) AS price';
                $providerPriceQueryArr[] = 'FROM ' . DB_ORDER_GOODS . ' og';
                $providerPriceQueryArr[] = implode(' ', str_ireplace('limit ' . $page->recode['start'] . ',' . $searchData['pageNum'], '', $priceQuery));
                $providerPriceQuery = implode(' ', $providerPriceQueryArr);
                $providerTotalPrice = $this->db->query_fetch($providerPriceQuery, $this->arrBind, true);

                if(count($total) > 0){
                    $total[0]['price'] += array_sum(array_column($providerTotalPrice, 'price'));
                }
            }
            else {
				// 2024-01-23 wg-eric 주문리스트에 총 환불금액 가져오기 - start
				$refundFl = false;
				if ($searchData['wgOrderStatus']) {
					foreach($searchData['wgOrderStatus'] as $key => $val) {
						if ($val == 'r') {
							$refundFl = true;
						}
					}
				}
				if($refundFl) {
					$total = $this->db->query_fetch('SELECT SUM(oh.refundPrice) as refundPrice, (o.realTaxSupplyPrice + o.realTaxFreePrice + o.realTaxVatPrice) AS price, COUNT(distinct(og.sno)) AS cnt FROM ' . DB_ORDER_GOODS . ' og ' . implode(' ', str_ireplace('limit ' . $page->recode['start'] . ',' . $searchData['pageNum'], '', $query)), $this->arrBind, true);
				} else {
					$total = $this->db->query_fetch('SELECT (o.realTaxSupplyPrice + o.realTaxFreePrice + o.realTaxVatPrice) AS price, COUNT(distinct(og.sno)) AS cnt FROM ' . DB_ORDER_GOODS . ' og ' . implode(' ', str_ireplace('limit ' . $page->recode['start'] . ',' . $searchData['pageNum'], '', $query)), $this->arrBind, true);
				}
				// 2024-01-23 wg-eric 주문리스트에 총 환불금액 가져오기 - end
            }

            $page->recode['totalPrice'] = array_sum(array_column($total, 'price'));
			$page->recode['totalRefundPrice'] = array_sum(array_column($total, 'refundPrice'));
        }

        if ($isDisplayOrderGoods) {
            $ogSno = 'og.sno';
            $groupby = '';
            $page->recode['total'] = array_sum(array_column($total, 'cnt'));
            $this->search['deliveryFl'] = true;
        } else {
            $ogSno = 'og.orderNo';
            $groupby = ' GROUP BY og.orderNo';
            $page->recode['total'] = count($total);
        }

        // 주문상태에 따른 전체 갯수
        if($page->hasRecodeCache('amount') === false) {
            if (Manager::isProvider()) {
                if ($this->search['statusMode'] !== null) {
                    $query = 'SELECT COUNT(' . $ogSno . ') as total FROM ' . DB_ORDER_GOODS . ' og WHERE og.scmNo=' . Session::get('manager.scmNo') . ' AND (og.orderStatus LIKE concat(\'' . $this->search['statusMode'] . '\',\'%\'))' . $groupby;
                } else {
                    $query = 'SELECT COUNT(' . $ogSno . ') as total FROM ' . DB_ORDER_GOODS . ' og WHERE og.scmNo=' . Session::get('manager.scmNo') . ' AND LEFT(og.orderStatus, 1) NOT IN (\'o\', \'c\') AND og.orderStatus != \'' . $this->arrBind[1] . '\'' . $groupby;
                }
            } else {
                if ($this->search['statusMode'] !== null) {
                    $query = 'SELECT COUNT(' . $ogSno . ') as total FROM ' . DB_ORDER_GOODS . ' og WHERE (og.orderStatus LIKE concat(\'' . $this->search['statusMode'] . '\',\'%\'))' . $groupby;
                } else if ($searchData['navTabs'] && $searchData['memNo']) { // CRM 주문관리 회원일련번호기준 갯수
                    $query = 'SELECT COUNT(' . $ogSno . ') as total FROM ' . DB_ORDER_GOODS . ' og LEFT JOIN ' . DB_ORDER . ' o ON o.orderNo = og.orderNo WHERE o.memNo = ' . $searchData['memNo'] . ' AND (og.orderStatus != \'' . $this->arrBind[1] . '\') AND og.orderStatus != \'f1\'' . $groupby;
                } else {
                    $query = 'SELECT COUNT(' . $ogSno . ') as total FROM ' . DB_ORDER_GOODS . ' og WHERE (og.orderStatus != \'' . $this->arrBind[1] . '\') AND og.orderStatus != \'f1\'' . $groupby;
                }
            }
            
            if (!$isDisplayOrderGoods) {
                $query = "SELECT COUNT(*) as total FROM ({$query}) as t";
            }

            $total = $this->db->query_fetch($query, null, false)['total'];
            $page->recode['amount'] = $total;
        }

        $page->setPage(null,['totalPrice']);

        $tmp = $this->setOrderListForAdmin($getData, $isUserHandle, $isDisplayOrderGoods, true, $searchData['statusMode']);
		//$tmp = parent::getOrderListForAdmin($searchData, $searchPeriod, $isUserHandle);

		//gd_debug($tmp['data']);

		foreach($tmp['data'] as $key1=>$val1){
		
			foreach($val1['goods'] as $key2 =>$val2){

				foreach($val2 as $key3=>$val3){
				
					foreach($val3 as $key4=>$val4){
						$sql="select count(idx) as cnt from wm_subSchedules where orderNo=?";
						$row = $this->db->query_fetch($sql,['i',$val4['orderNo']],false);

						if($row['cnt']>0){
							$tmp['data'][$key1]['goods'][$key2][$key3][$key4]['orderGoodsNm']="[정기결제]".$val4['orderGoodsNm'];
						}
					}
				}
				
			}
		}
		return $tmp;
	}

	/**
     * orderUserHandle 테이블의 수량
     *
     * @return bool|string 수량 배열
     */
    public function getCountUserHandles()
    {
        // 합계계산
		// 2024-11-01 wg-eric 환불완료 했는데 userHandleFl 안 바뀌는 경우 있어서 수정
		$this->db->strField = '
            IFNULL(SUM(IF(userHandleMode=\'b\', 1, 0)), 0) AS backAll,
            IFNULL(SUM(IF(userHandleFl=\'r\' AND userHandleMode=\'b\', 1, 0)), 0) AS backRequest,
            IFNULL(SUM(IF(userHandleFl!=\'r\' AND userHandleMode=\'b\', 1, 0)), 0) AS backAccept,
            IFNULL(SUM(IF(userHandleMode=\'r\', 1, 0)), 0) AS refundAll,
            IFNULL(SUM(IF((o.orderStatus != \'r3\' AND userHandleFl=\'r\' AND userHandleMode=\'r\'), 1, 0)), 0) AS refundRequest,
            IFNULL(SUM(IF((userHandleFl!=\'r\' AND userHandleMode=\'r\') OR (o.orderStatus = \'r3\' AND userHandleMode=\'r\') , 1, 0)), 0) AS refundAccept,
            IFNULL(SUM(IF(userHandleMode=\'e\', 1, 0)), 0) AS exchangeAll,
            IFNULL(SUM(IF(userHandleFl=\'r\' AND userHandleMode=\'e\', 1, 0)), 0) AS exchangeRequest,
            IFNULL(SUM(IF(userHandleFl!=\'r\' AND userHandleMode=\'e\', 1, 0)), 0) AS exchangeAccept
        ';
		$this->db->strJoin = 'LEFT JOIN es_order AS o ON o.orderNo = ouh.orderNo';
        $query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_ORDER_USER_HANDLE . ' as ouh ' . implode(' ', $query);
        $getData = $this->db->query_fetch($strSQL, $arrBind, false);

        // 데이타 출력
        if (count($getData) > 0) {
            return gd_htmlspecialchars_stripslashes($getData);
        } else {
            return false;
        }
    }

	/**
     * getEachOrderStatusAdmin
     * 관리자 메인에 주문관리 / 주문현황
     * 주문 상태에 따른 상품 수
     * 주문 상태마다 기준 검색 날짜가 다름 ( 입금대기-주문일, 결제완료-결제일..... )
     * 주문 상태에 따른 주문 수와 상품 수에서 상태마다 표시 방법이 달라 우선 상품의 수로 노출함
     *
     * @param array $mainOrderStatus
     * @param int   $day
     * @param null  $scmNo
     *
     * @return array|string
     *
     * @author su
     */
    public function getEachOrderStatusAdmin($mainOrderStatus, $day = 30, $scmNo = null, $orderCountFl = 'goods')
    {
        // 주문상태 정보
        $status = $this->_getOrderStatus();
        $status['er'] = '고객교환신청';
        $status['br'] = '고객반품신청';
        $status['rr'] = '고객환불신청';

        // 상태별 조회 기준 날짜
        $statusCodeSearchDate = [
            'o' => 'og.regDt',
            'f' => 'og.regDt',
            'p' => 'og.paymentDt',
            'g' => 'og.paymentDt',
            'd1' => 'og.deliveryDt',
            'd2' => 'og.deliveryCompleteDt',
            's' => 'og.finishDt',
            'c' => 'og.cancelDt',
            'b' => 'oh.regDt',
            'e' => 'oh.regDt',
            'z' => 'oh.regDt',
            'r' => 'oh.regDt',
            'br' => 'ouh.regDt',
            'er' => 'ouh.regDt',
            'rr' => 'ouh.regDt',
        ];

        foreach ($mainOrderStatus as $val) {

            $code = $val;
            $str = $status[$val];

            // 배열 선언
            $arrBind = $arrWhere = $arrJoin = [];

            // 데이터 초기화
            $getData[$code]['name'] = $str;
            $getData[$code]['count'] = 0;
            $getData[$code]['active'] = '';

            // 공급사 로그인한 경우
            if (Manager::isProvider() || ($scmNo !== null && $scmNo > 0)) {
                if (Manager::isProvider()) {
                    // 공급사로 로그인한 경우 기존 scm에 값 설정
                    $arrWhere[] = 'og.scmNo = ' . Session::get('manager.scmNo');
                } else {
                    // 공급사의 검색일 경우 scm에 값 설정
                    $arrWhere[] = 'og.scmNo = ' . $scmNo;
                }
            }

            // 상태별 조회 기준 날짜가 변경됨
            $groupCode = substr($code, 0, 1);
            $groupCode2 = substr($code, 1, 1);
            if ($groupCode == 'd') { // 배송상태마다 검색일 기준이 다름 - 배송완료은 deliveryCompleteDt / 배송중은 deliveryDt
                $codeDate = $statusCodeSearchDate[$code];
            } else if ($groupCode == 'e' || $groupCode == 'r' || $groupCode == 'b' || $groupCode == 'z') { // 교환, 환불, 반품은 고객신청과 관리자처리마다 검색일 기준이 다름
                if ($groupCode2 == 'r') { // 고객 신청
                    $codeDate = $statusCodeSearchDate[$code];
                } else { // 관리자 처리
                    $codeDate = $statusCodeSearchDate[$groupCode];
                }
            } else { // 그 외는 주문상태 그룹마다 검색일 기준이 다름
                $codeDate = $statusCodeSearchDate[$groupCode];
            }

            if ($day > 0) {
                // 기간산출 (한달 이내로 쿼리 조작)
                $arrWhere[] = $codeDate . ' BETWEEN ? AND ?';
                $this->db->bind_param_push($arrBind, 's', date('Y-m-d', strtotime('-' . $day . ' days')) . ' 00:00:00');
                $this->db->bind_param_push($arrBind, 's', date('Y-m-d') . ' 23:59:59');
            } else {
                $arrWhere[] = $codeDate . ' BETWEEN ? AND ?';
                $this->db->bind_param_push($arrBind, 's', date('Y-m-d') . ' 00:00:00');
                $this->db->bind_param_push($arrBind, 's', date('Y-m-d') . ' 23:59:59');
            }

            // 반품 환품 교환 이면 orderHandle 도 join
            if ($groupCode == 'b' || $groupCode == 'e' || $groupCode == 'r' || $groupCode == 'z') {
                if ($groupCode2 == 'r') {
                    $arrJoin[] =  'INNER JOIN ' . DB_ORDER_USER_HANDLE. ' as ouh ON og.userHandleSno = ouh.sno';

                    // 상태별 조건
					// 2024-11-01 wg-eric 환불완료 했는데 userHandleFl 안 바뀌는 경우 있어서 수정
                    $arrWhere[] = 'ouh.userHandleMode = ? AND ouh.userHandleFl = ? AND og.orderStatus != "r3"';
                    $this->db->bind_param_push($arrBind, 's', $groupCode);
                    $this->db->bind_param_push($arrBind, 's', $groupCode2);
                } else {
                    $arrJoin[] =  'LEFT JOIN ' . DB_ORDER_HANDLE. ' as oh ON og.handleSno = oh.sno';

                    // 상태별 조건
                    $arrWhere[] = 'og.orderStatus = ?';
                    $this->db->bind_param_push($arrBind, 's', $code);
                }
            } else {
                // 상태별 조건
                $arrWhere[] = 'og.orderStatus = ?';
                $this->db->bind_param_push($arrBind, 's', $code);
            }

            if($orderCountFl == 'goods'){
                $orderCountField = 'og.sno';
            }else{
                $orderCountField = 'DISTINCT og.orderNo';
            }

            // 상태 합계 산출
            $this->db->strJoin = implode(' ', $arrJoin);
            if ($groupCode2 == 'r') { // 고객 신청
                $this->db->strField = 'date(' . $codeDate . ') as regDt, count('. $orderCountField. ') AS countStatus';
                $this->db->strGroup = 'date(ouh.regDt)';
            } else {
                $this->db->strField = 'count('. $orderCountField. ') AS countStatus';
            }
            $this->db->strWhere = implode(' AND ', $arrWhere);
            $query = $this->db->query_complete();
            $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_ORDER_GOODS . ' as og ' . implode(' ', $query);
            $result = $this->db->query_fetch($strSQL, $arrBind, true);
            unset($arrBind);
            unset($arrWhere);
            unset($arrJoin);

            $count = $result[0]['countStatus'];
            if ($groupCode2 == 'r') { // 고객 신청
                $count = 0;
                foreach($result as $val) {
                    $count += $val['countStatus'];
                }
            }
            if($count > 0) {
                $getData[$code]['count'] = $count;
                $getData[$code]['active'] = 'active';
            }
        }

        return gd_htmlspecialchars_stripslashes($getData);
    }
}