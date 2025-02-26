<?php

/**
 *
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link      http://www.godo.co.kr
 *
 */
namespace Component\Mileage;

use App;
use Component\Database\DBTableField;
use Component\Mail\MailMimeAuto;
use Component\Member\MemberVO;
use Component\Sms\Code;
use Component\Sms\SmsAutoCode;
use Component\Validator\Validator;
use Component\Mileage\MileageUtil;
use Exception;
use Framework\Object\SimpleStorage;
use Framework\Utility\ArrayUtils;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\StringUtils;
use Framework\Utility\UrlUtils;
use Globals;
use Request;
use DateTime;

/**
 * 마일리지 관련 기능 담당 클래스
 * @package Bundle\Component\Mileage
 * @author  yjwee <yeongjong.wee@godo.co.kr>
 */
class Mileage extends \Bundle\Component\Mileage\Mileage
{
	public $expectMileageCnt = 0;


	public function setRecomMemberMileage($memNo, $targetMileage, $reasonCd, $handleMode, $handleCd, $handleNo = null, $contents = null, $frontPage = false,  $deleteScheduleDate)
    {
        $logger = \App::getInstance('logger');
        $logger->info('Start setMemberMileage.');
        StringUtils::strIsSet($memNo, '');
        $member = $this->getDataByTable(DB_MEMBER, $memNo, 'memNo', '*');
        $memberVO = new MemberVO($member);
        if ($this->db->num_rows() == 0) {
            $logger->warning(__METHOD__ . ', 마일리지 지급 대상을 찾지 못하였습니다.', func_get_args());

            return false;
        }

        $domain = new MileageDomain();   // 지급/차감 처리할 마일리지 정보
        $domain->setMemNo($memNo);
        $domain->setMileage($targetMileage);
        $domain->setReasonCd($reasonCd);
        $domain->setHandleMode($handleMode);
        $domain->setHandleCd($handleCd);
        $domain->setHandleNo($handleNo);
        $domain->setContents($contents);
        $domain->setBeforeMileage($memberVO->getMileage());
        $domain->setAfterMileage($memberVO->getMileage() + $targetMileage);

        try {
            $this->validateMileage($domain->toArray());
            /*
             * 마일리지 유효기간 설정
             * 2017-02-03 yjwee 마일리지 지급 시에만 설정하게끔 수정
             */
            if ($domain->getMileage() > 0) {
                $domain->setDeleteScheduleDt($domain->getMileage() > 0 ? MileageUtil::getDeleteRecomScheduleDate() : '0000-00-00 00:00:00');
                $logger->info(sprintf('Set add mileage delete schedule datetime[%s]', $domain->getDeleteScheduleDt()));
            }
            /*
             * 소멸 마일리지 여부
             * 2017-02-03 yjwee 소멸 마일리지 체크하는 로직 수정 날짜로는 체크가 안되어서 사유 코드로 체크하게끔 함
             */
            if ($reasonCd == self::REASON_CODE_GROUP . self::REASON_CODE_EXPIRE) {
                $domain->setDeleteFl('y');
                $logger->info(sprintf('Set delete mileage flag[%s]', $domain->getDeleteFl()));
            }

            //유저페이지에서 마일리지가 지급되는 경우 관리자 아이디가 저장되는 경우가 있어서 저장안되게 처리
            if($frontPage === true){
                $domain->setManagerNo(0);
                $domain->setManagerId('');
            }

            $sno = $this->mileageDAO->insertMileage($domain);
            $logger->info(sprintf('Mileage have been added. sno[%s], memNo[%s]', $sno, $memNo));
            $domain->setSno($sno);
            // 적립/차감 내용 저장
            if ($domain->getMileage() < 0 && $domain->getSno() > 0) {
                try {
                    $dao = new MileageDAO();
                    $history = new MileageHistory($dao, $this);
                    $history->setMileageDomain($domain);
                    $history->saveUseHistory();
                    $logger->info('Saved usage mileage history.');
                } catch (\Throwable $e) {
                    $logger->error(__METHOD__ . ', ' . $e->getFile() . '[' . $e->getLine() . '], ' . $e->getMessage(), $e->getTrace());

                    return false;
                }
            }
            // 회원 마일리지 가감 처리
            $this->memberService->update($domain->getMemNo(), 'memNo', ['mileage'], [$domain->getAfterMileage()]);
        } catch (\Throwable $e) {
            $logger->error(__METHOD__ . ', ' . $e->getFile() . '[' . $e->getLine() . '], ' . $e->getMessage(), $e->getTrace());

            return false;
        }
        StringUtils::strIsSet($member['sleepFl'], '');
        if ($member['sleepFl'] === 'y' || $reasonCd == self::REASON_CODE_GROUP . self::REASON_CODE_MEMBER_SLEEP || $reasonCd == self::REASON_CODE_GROUP . self::REASON_CODE_MEMBER_WAKE) {
            $this->allowSendSms = false;
        }
        // 마일리지 지급/차감 SMS 는 수신동의 상관없이 설정값에 따라 발송이 되므로 관리자 일괄 지급/차감에서 sms 미발송으로 설정한 경우에만 발송하지 않도록 한다.
        if ($this->allowSendSms) {
            $aBasicInfo = gd_policy('basic.info');
            $groupInfo = \Component\Member\Group\Util::getGroupName('sno=' . $memberVO->getGroupSno());
            $smsAuto = \App::load(\Component\Sms\SmsAuto::class);
            $smsAuto->setSmsType(SmsAutoCode::MEMBER);
            $smsAuto->setSmsAutoCodeType($targetMileage < 0 ? Code::MILEAGE_MINUS : Code::MILEAGE_PLUS);
            $smsAuto->setReceiver($memberVO->getCellPhone());
            $smsAuto->setReplaceArguments(
                [
                    'name'       => $memberVO->getMemNm(),
                    'memNm'      => $memberVO->getMemNm(),
                    'memId'      => $memberVO->getMemId(),
                    'deposit'    => $memberVO->getDeposit(),
                    'mileage'    => $memberVO->getMileage(),
                    'groupNm'    => $groupInfo[$memberVO->getGroupSno()],
                    'rc_mileage' => $targetMileage,
                    'rc_mallNm'  => $aBasicInfo['mallNm'],
                    'shopUrl'    => $aBasicInfo['mallDomain'],
                ]
            );
            if ($this->smsReserveTime != null) {
                $smsAuto->setSmsAutoSendDate($this->smsReserveTime);
            }
            $smsAuto->autoSend();
        } else {
            $logger->info(sprintf('%s, disAllow send sms.', __METHOD__));
        }

        return true;
    }
	
	public function approveEncashment($sno)
	{
		$sql = 'UPDATE wg_mileageEncashment SET applyFl = "y", modDt = now() WHERE sno = '.$sno;
		$this->db->query($sql);
	}

	public function rejectEncashment($sno, $excelFl)
	{
		$postValue = \Request::post()->toArray();
		$refusFlStr = ', refusFl = '.gd_isset($postValue['rejectFl'], 0);

		# 현금화 거절
		$sql = 'SELECT * FROM wg_mileageEncashment WHERE sno = '.$sno.' AND applyFl = "w"';
		$memData = $this->db->query_fetch($sql, null, false);

		# 금액만큼 마일리지 지급
		if($memData) {
			$arrData = [
				'mileageCheckFl' => 'add',
				'mileageValue' => (int)$memData['encashmentPrice'],
				'reasonCd' => '01005509',
				'contents' => '적립금 현금화 거절',
				'mode' => 'add_mileage',
				'chk' => $memData['memNo'],
			];
			$this->addMileage($arrData);
		}

		if(!$excelFl) {
			$sql = 'UPDATE wg_mileageEncashment SET applyFl = "n", modDt = now() '.$refusFlStr.' WHERE sno = '.$sno;
			$this->db->query($sql);
		}
	}
	

	public function getPaybackList(array $searchDt = [], $offset = 1, $limit = 10)
    {
        $this->arrWhere = $this->arrBind = [];
        $session = \App::getInstance('session');
        $this->arrWhere[] = 'memNo=' . $session->get('member.memNo');
        $search = ['regDt' => $searchDt];
        $this->db->bindParameterByDateTimeRange('regDt', $search, $this->arrBind, $this->arrWhere, 'tableMileageEncashment');
        $this->db->strWhere = implode(' AND ', $this->arrWhere);
        $this->db->strField = '*, substr(regDt, 1, 10) AS regdate';
        $this->db->strOrder = 'sno DESC';

        if (is_null($offset) === false && is_null($limit) === false) {
            $this->db->strLimit = ($offset - 1) * $limit . ', ' . $limit;
        }

        $query = $this->db->query_complete();
		
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM wg_mileageEncashment ' . implode(' ', $query);
		
        $list = $this->db->query_fetch($strSQL, $this->arrBind);
        unset($query);
		
		$encashment = new \Component\Member\Encashment();
		foreach($list as $key => $val) {
			$list[$key]['refusFl'] = $encashment->refusFl[$val['refusFl']];
		}

        return $list;
    }

	public function foundPaybackByListSession()
    {
        $db = \App::getInstance('DB');
        $db->strField = 'COUNT(*) AS cnt';
        $db->strWhere = implode(' AND ', $this->arrWhere);
        $query = $db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM wg_mileageEncashment AS a ' . implode(' ', $query);
        $resultSet = $db->query_fetch($strSQL, $this->arrBind, false);
        StringUtils::strIsSet($resultSet['cnt'], 0);
		
        return $resultSet['cnt'];
    }
	
	public function recomListBySession(array $searchDt = [], $offset = 1, $limit = 10)
    {
		$session = \App::getInstance('session');
		$order = \App::load('Component\\Order\\Order');
		$member = \App::load('\\Component\\Member\\Member');
		$recomMileageConfig = gd_policy('member.recomMileageGive');
		$today = new DateTime(); //오늘 날짜
		$blackList = explode(',',$recomMileageConfig['blackList']); //블랙리스트

		if($recomMileageConfig['blackListFl'] == 'y') {
			$strBlackListWhere = 'AND m.recommId NOT IN ("'.implode('","', $blackList).'")';
		}

		//루딕스-brown 자신의 하위회원들 가져오기
		$downMemNo = $member->getDownMemNo($session->get('member.memId'));
		if(count($downMemNo) == 1) {
			$arrMemNo[0] = $downMemNo['memNo'];
		}else if(count($downMemNo) > 1){
			$arrMemNo = array_column($downMemNo, 'memNo');
		}

		//적립예정 주문가져오기
		$sql = '
		SELECT  orderGoodsNm, idxApply, orderNo, regDt, recommId, memNo, recomMileagePayFl, couponNm
		FROM (
			SELECT 
				 ss.idxApply, ss.orderNo, o.regDt as regDt, o.orderGoodsNm, m.recommId, ss.memNo, m.recomMileagePayFl, oc.couponNm
			FROM 
				wm_subSchedules ss
			LEFT JOIN 
				es_member m ON m.memNo = ss.memNo
			LEFT JOIN 
				es_order o ON o.orderNo = ss.orderNo
			LEFT JOIN 
				es_orderCoupon oc ON oc.orderNo = o.orderNo
			WHERE
				ss.status = "paid" 
				AND (m.recommId != "" '.$strBlackListWhere.')	
				AND m.recomMileagePayFl = "n"
				AND oc.orderNo IS NOT NULL
				AND m.memNo IN ('.implode(',', $arrMemNo).')
			GROUP BY ss.idxApply
			HAVING COUNT(ss.idx) >= 2 AND regDt > "2024-01-10"

			UNION ALL

			SELECT 
				ss.idxApply, o.orderNo, o.regDt as regDt, o.orderGoodsNm, m.recommId, m.memNo, m.recomMileagePayFl, NULL as couponNm
			FROM 
				es_order o
			LEFT JOIN 
				es_orderGoods og ON og.orderNo = o.orderNo 
			LEFT JOIN 
				es_member m ON m.memNo = o.memNo 
			LEFT JOIN 
				wm_subSchedules ss ON ss.orderNo = o.orderNo 
			WHERE
				og.paymentDt != "0000-00-00 00:00:00" 
				AND m.recomMileagePayFl = "n"
				AND og.handleSno ="" 
				AND og.userHandleSno =""
				AND LEFT(og.orderStatus, 1) NOT IN ("r", "f", "d", "c") 
				AND (m.recommId != "" '.$strBlackListWhere.')	
				AND ss.orderNo IS NULL
				AND o.regDt > "2024-01-10 00:00:00"
				AND m.memNo IN ('.implode(',', $arrMemNo).')
			GROUP BY o.orderNo
			
			UNION ALL

			SELECT 
				 ss.idxApply, ss.orderNo, o.regDt as regDate, o.orderGoodsNm, m.recommId, ss.memNo, m.recomMileagePayFl, NULL as couponNm
			FROM 
				wm_subSchedules ss
			LEFT JOIN 
				es_member m ON m.memNo = ss.memNo
			LEFT JOIN 
				es_order o ON o.orderNo = ss.orderNo
			LEFT JOIN 
				es_orderCoupon oc ON oc.orderNo = o.orderNo
			WHERE
				ss.status = "paid" 
				AND (m.recommId != "" '.$strBlackListWhere.')	
				AND m.recomMileagePayFl = "n"
				AND oc.orderNo IS NULL
				AND m.memNo IN ('.implode(',', $arrMemNo).')
			GROUP BY ss.idxApply
			HAVING COUNT(ss.idx) >= 1 AND regDate > "2024-01-10"
		) AS combined_table
		ORDER BY regDt ASC;
		';
	 	$orderData = $this->db->query_fetch($sql);
		//적립예정 주문가져오기

		$newOrderData = [];
		$duplicateMemNo = []; //회원중복 방지

		//적립예정 금액 저장
		$includeStatus = ['p', 's', 'g', 'd']; //결제이후 상태들 
		foreach($orderData as $key => $val ){
			if(in_array($val['memNo'], $duplicateMemNo)) {
				continue;
			}
			//orderData에 같은 회원이 다른주문을 주문시 마일리지 중복지급 방지(회원당 추천인에게 마일리지 적립 1회만 지급)
			$memberRecomMileagePayFl = $order->getMemberRecomMileagePayFl($val['memNo']);
			if($memberRecomMileagePayFl == 'n') {
				$getRecomMemNo = $member->getRecomMemNo($val['recommId']); //추천인 회원번호
				$orderData[$key]['goods'] = $order->getOrderGoods($val['orderNo'], null, null); //주문상품정보
				$totalMileage = 0;

				if($val['idxApply']) { //정기배송주문
					//해당 idxApply에서 paid인 주문들이 환불/반품이 제외 2개 이상일때 적립예정
					$sql = 'SELECT count(o.orderNo) as cnt FROM wm_subSchedules ss LEFT JOIN es_order o ON o.orderNo = ss.orderNo WHERE ss.idxApply='.$val['idxApply'].' AND ss.status="paid" AND LEFT(o.orderStatus, 1) NOT IN ("b","r")';
					$applyCnt = $this->db->query_fetch($sql, null, false)['cnt'];
					
					if($val['couponNm']) {
						if($applyCnt < 2){
							continue;
						}
					}else {
						if($applyCnt < 1){
							continue;
						}
					}

					foreach($orderData[$key]['goods'] as $goodsData) {
						if(!$goodsData['userHandleSno'] && !$goodsData['handleSno'] && !in_array(substr($goodsData['orderStatus'], 0, 1), ['r','f','c','d'])){ //해당 주문이 반품/환불/취소/실패인지 검사
							if($goodsData['recomMileagePayFl'] == 'w' && in_array(substr($goodsData['orderStatus'], 0, 1), $includeStatus)) { //해당 주문상품이 추천인 마일리지를 지급했는지 안했는지
								$totalMileage += $order->recomSubMileagePrice($goodsData);	
							}
						}
					}
					if($totalMileage > 0) {
						$val['expectMileage'] = $totalMileage;
						$val['contents'] = '정기배송주문시 추천인 마일리지 지급';
						$val['regdate'] = subStr($val['regDt'], 0 ,10);
						$newOrderData[] = $val;
						$duplicateMemNo[] = $val['memNo'];
					}
				}else { //일반주문
					foreach($orderData[$key]['goods'] as $goodsData) {
						if(!$goodsData['userHandleSno'] && !$goodsData['handleSno'] && !in_array(substr($goodsData['orderStatus'], 0, 1), ['r','f','c','d'])){ 
							if($goodsData['recomMileagePayFl'] == 'w' && in_array(substr($goodsData['orderStatus'], 0, 1), $includeStatus)) { //해당 주문상품이 추천인 마일리지를 지급했는지 안했는지
								//상품별 마일리지데이터 가져오기		
								$totalMileage += $order->recomMileagePrice($goodsData);
							}
						}
					}

					if($totalMileage > 0) {
						$val['expectMileage'] = $totalMileage;
						$val['contents'] = '1회구매 시 추천인 마일리지 지급';
						$val['regdate'] = subStr($val['regDt'], 0 ,10);
						$newOrderData[] = $val;
						$duplicateMemNo[] = $val['memNo'];
					}
				}	
			}
		}

	
		$this->expectMileageCnt = count($newOrderData);

		
        $this->arrWhere = $this->arrBind = [];
		$join[] = ' LEFT JOIN es_order o ON mm.handleCd = o.orderNo';

		//추천인 적립금 지급 
        $this->arrWhere[] = '(reasonCd = 01005506 OR reasonCd = 01005507 OR reasonCd = 01005007)';
        $this->arrWhere[] = 'mm.memNo=' . $session->get('member.memNo');
		$this->arrWhere[] = 'mm.handleMode="o"';
        $search = ['mm.regDt' => $searchDt];
        $this->db->bindParameterByDateTimeRange('mm.regDt', $search, $this->arrBind, $this->arrWhere, $this->tableFunctionName);
		$this->db->strJoin = implode(' ', $join);
        $this->db->strWhere = implode(' AND ', $this->arrWhere);
        $this->db->strField = 'o.orderGoodsNm, mm.*, substr(mm.regDt, 1, 10) AS regdate';
        $this->db->strOrder = 'mm.sno DESC';

        $query = $this->db->query_complete();
        $strSQL = 'SELECT' . array_shift($query) . ' FROM ' . DB_MEMBER_MILEAGE .' mm '. implode(' ', $query);
        $list = $this->db->query_fetch($strSQL, $this->arrBind);

		//적립예정과 기존의 지급된 마일리지 합치고 최신순서로 정렬
		$mergedArray = array_merge($newOrderData, $list);
		usort($mergedArray, function ($a, $b) {
			return strtotime($b['regDt']) - strtotime($a['regDt']);
		});
		$list = $mergedArray;

		
		//page를 위한 나누기
		$start = ($offset - 1) * $limit; // 시작 지점
		$list = array_slice($list, $start, $limit);


        unset($query);
        return $list;
    }

	public function recomFoundRowsByListSession()
    {
        $db = \App::getInstance('DB');
        $db->strField = 'COUNT(*) AS cnt';
        $db->strWhere = implode(' AND ', $this->arrWhere);
        $query = $db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_MEMBER_MILEAGE . ' AS mm ' . implode(' ', $query);
        $resultSet = $db->query_fetch($strSQL, $this->arrBind, false);
		//gd_Debug($resultSet);
        StringUtils::strIsSet($resultSet['cnt'], 0);

		//루딕스-brown 적립예정 갯수더하기
		$resultSet['cnt'] = $resultSet['cnt'] + $this->expectMileageCnt;
		return $resultSet['cnt'];
    }

	public function getHistoryPayback($memNo)
	{
		$sql = 'SELECT * FROM wg_mileageEncashment WHERE memNo = '.$memNo.' ORDER BY regDt DESC LIMIT 1';
		$historyData = $this->db->query_fetch($sql, null, false);
		return $historyData;
	}
	
	public function encashmentCheck($memNo)
	{
		//페이백신청 월1회 검사
		$ym= date('Y-m');
		$today = date('Y-m-d');
		$sql = 'SELECT * FROM wg_mileageEncashment WHERE memNo = '.$memNo.' AND regDt BETWEEN "'.$ym.'-01 00:00:00" AND "'.$today.' 23:59:59"';
		$historyData = $this->db->query_fetch($sql);
		if($historyData) {
			throw new Exception(__('페이백 신청은 월기준 1회로 제한됩니다.'));
		}
		return true;
	}

	/**
     * 회원 세션의 회원번호를 이용하여 해당 회원의 마일리지 내역을 조회한다.
     *
     * @param array $searchDt
     * @param int   $offset
     * @param int   $limit
     *
     * @return mixed
     */
    public function listBySession(array $searchDt = [], $offset = 1, $limit = 10)
    {
        $this->arrWhere = $this->arrBind = [];
        $session = \App::getInstance('session');
        $this->arrWhere[] = 'memNo=' . $session->get('member.memNo');
		//2024-01-17 루딕스-brown 추천인 적립은 제외
		$this->arrWhere[] = '(reasonCd != 01005506 AND reasonCd != 01005507 AND reasonCd != 01005007)';

        $search = ['regDt' => $searchDt];
        $this->db->bindParameterByDateTimeRange('regDt', $search, $this->arrBind, $this->arrWhere, $this->tableFunctionName);
        $this->db->strWhere = implode(' AND ', $this->arrWhere);
        $this->db->strField = '*, substr(regDt, 1, 10) AS regdate';
        $this->db->strOrder = 'sno DESC';

        if (is_null($offset) === false && is_null($limit) === false) {
            $this->db->strLimit = ($offset - 1) * $limit . ', ' . $limit;
        }

        $query = $this->db->query_complete();
        $strSQL = 'SELECT /* 회원 적립금 */' . array_shift($query) . ' FROM ' . DB_MEMBER_MILEAGE . ' ' . implode(' ', $query);
        $list = $this->db->query_fetch($strSQL, $this->arrBind);

        unset($query);

        return $list;
    }

    /**
     * 세션 기준 마일리지 리스트 검색결과 카운트 함수
     * \Component\Mileage\Mileage::listBySession 함수가 실행되어야 검색조건을 참조한다.
     *
     * @return int
     * @throws \Framework\Debug\Exception\DatabaseException
     */
    public function foundRowsByListSession()
    {
        $db = \App::getInstance('DB');
        $db->strField = 'COUNT(*) AS cnt';
        $db->strWhere = implode(' AND ', $this->arrWhere);
        $query = $db->query_complete();
        $strSQL = 'SELECT /* 회원 적립금 */' . array_shift($query) . ' FROM ' . DB_MEMBER_MILEAGE . ' AS a ' . implode(' ', $query);
        $resultSet = $db->query_fetch($strSQL, $this->arrBind, false);
        StringUtils::strIsSet($resultSet['cnt'], 0);
        return $resultSet['cnt'];
    }

	public function reasonCdModifyMileage($arrData)
    {
		//루딕스-brown mileage 이전 마일리지 정보가져오기
		$sql = 'SELECT reasonCd as beforeReasonCd, contents as beforeContents FROM es_memberMileage WHERE sno='.$arrData['sno'];
		$milageData = $this->db->query_fetch($sql, null, false);
		//루딕스-brown mileage 이전 마일리지 정보가져오기

		$rs = parent::reasonCdModifyMileage($arrData);

		//루딕스-brown mileage 히스토리에 저장
		$history = \App::load('Component\\Member\\History');
		$mileageReasons = MileageUtil::getReasons();

		$milageData['memberMileageSno'] = $arrData['sno'];
		if($arrData['reasonModiyCode'] == self::REASON_CODE_GROUP . self::REASON_CODE_ETC) {
            $milageData['afterReasonCd'] = self::REASON_CODE_GROUP . self::REASON_CODE_ETC;
            $milageData['afterContents'] = $arrData['contents'];
        }else{
            $milageData['afterReasonCd'] = $arrData['reasonModiyCode'];
            $milageData['afterContents'] = $mileageReasons[$arrData['reasonModiyCode']];
        }

		if($milageData['afterReasonCd'] && $milageData['afterContents']) {
			if($milageData['afterReasonCd'] == self::REASON_CODE_GROUP . self::REASON_CODE_ETC && $milageData['beforeReasonCd'] == self::REASON_CODE_GROUP . self::REASON_CODE_ETC) {
				if($milageData['afterContents'] != $milageData['beforeContents']) {
					$history->insertMemberMileageHistory($milageData);
				}
			}else {
				if($milageData['afterReasonCd'] != $milageData['beforeReasonCd']){
					$history->insertMemberMileageHistory($milageData);
				}
			}
		}
		//루딕스-brown mileage 히스토리에 저장

		return $rs;  
    }
}