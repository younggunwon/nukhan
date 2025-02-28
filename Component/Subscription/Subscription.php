<?php

namespace Component\Subscription;

use App;
use Request;
use Exception;
use Session;
use Component\Database\DBTableField;
use Component\GoodsStatistics\GoodsStatistics;
use Component\Subscription\Traits\PgCommon; // PG 공통 
use Component\Subscription\Traits\SubscriptionApply; // 정기결제 신청관련
use Component\Traits\DateDay; // 날짜,요일관련
use Component\Traits\Security;
use Component\Traits\Common;
use Component\Traits\GoodsInfo;
use Framework\Utility\ArrayUtils;
use Framework\Debug\Exception\AlertRedirectException;

/**
* 정기결제 관련 
*
* @author webnmobile
*/
class Subscription
{
	use PgCommon, DateDay, GoodsInfo, Security, Common, SubscriptionApply;
	
	protected $db;
	protected $cfg;
	public $schedule=0;
	
	public function __construct()
	{
		$this->db = App::load(\DB::class);
		
		/* 정기배송 설정 S */
		$sql = "SELECT * FROM wm_subConf";
		$cfg = $this->db->fetch($sql);
		if ($cfg) {
			$info = gd_policy('basic.info', DEFAULT_MALL_NUMBER);
			$cfg['mallNm'] = $info['mallNm'];
			$server = Request::server()->toArray();
			if (strtoupper($server['HTTPS']) == 'ON')
				$domain = "https://";
			else
				$domain = "http://";
			
			$domain .= $server['HTTP_HOST'];
			$cfg['domain'] = $domain;
			$cfg['uid'] = $this->getUid();
			$cfg['deliveryCycle'] = $cfg['deliveryCycle']?explode(",", $cfg['deliveryCycle']):[];
			$cfg['deliveryEa'] = $cfg['deliveryEa']?explode(",", $cfg['deliveryEa']):[];
			$cfg['deliveryEaDiscount'] = $cfg['deliveryEaDiscount']?explode(",", $cfg['deliveryEaDiscount']):[];
			$cfg['terms'] = $cfg['terms']?str_replace("\\r\\n", PHP_EOL, $cfg['terms']):"";
			$cfg['cardTerms'] = $cfg['cardTerms']?str_replace("\\r\\n", PHP_EOL, $cfg['cardTerms']):"";

			$cfg['orderTerms'] = $cfg['orderTerms']?str_replace("\\r\\n", PHP_EOL, $cfg['orderTerms']):"";

			$cfg['deliveryYoils'] = $cfg['deliveryYoils']?explode(",", $cfg['deliveryYoils']):[];
		

			$cfg['smsTemplate'] = $cfg['smsTemplate']?str_replace("\\r\\n", PHP_EOL, $cfg['smsTemplate']):"";

			$cfg['unitPrecision'] = $cfg['unitPrecision'];
			$cfg['unitRound'] = $cfg['unitRound'];
			
			$cfg['iniapiKey'] = $cfg['iniapiKey'];
			$cfg['iniapiiv'] = $cfg['iniapiiv'];
			$cfg['order_fail_retry'] = $cfg['order_fail_retry'];
			
			
			$cfg['failSmsTemplate'] = $cfg['failSmsTemplate']?str_replace("\\r\\n", PHP_EOL, $cfg['failSmsTemplate']):"";
			$cfg['rFailSmsTemplate'] = $cfg['rFailSmsTemplate']?str_replace("\\r\\n", PHP_EOL, $cfg['rFailSmsTemplate']):"";

			/* PG 설정 S */
			if ($server['PHP_SELF'] != '/policy/subscription_config.php') {
			    
			    
				if ($cfg['useType'] != 'real') {
					$cfg['mid'] = 'INIBillTst';
					$cfg['signKey'] = 'SU5JTElURV9UUklQTEVERVNfS0VZU1RS';
					$cfg['lightKey'] = "b09LVzhuTGZVaEY1WmJoQnZzdXpRdz09";
					
					$cfg['iniapiKey'] = "rKnPljRn5m6J9Mzz";
					$cfg['iniapiiv']  = "";
				}
				
				//if(\Request::getRemoteAddress()=="182.216.219.157" || \Request::getRemoteAddress()=="124.111.182.132"){
				    $SignatureUtil      = App::load(\Component\Subscription\InicisApi\INIStdPayUtil::class);
				    $cfg['mKey']        = $SignatureUtil->makeHash($cfg['signKey'], "sha256");
				    $cfg['timestamp']   = $SignatureUtil->getTimestamp();
				    
				//}else{
				//    $SignatureUtil = App::load(\Component\Subscription\Inicis\INIStdPayUtil::class);
				    
				//    $cfg['timestamp'] = $SignatureUtil->getTimestamp();
				//    $cfg['mKey'] = hash('sha256', $cfg['signKey']);
				//    $cfg['modulePath'] = dirname(__FILE__) . "/../../../subscription_module/inicis";
				//}

			}
			
			/* PG 설정 E */
		} // endif 
		/* 정기배송 설정 E */	
		
		$this->cfg = gd_isset($cfg, []);
	}
	
	/**
	* 정기배송 설정 
	*
	* @return Array
	*/
	public function getCfg($goodsNos = [])
	{
		$cfg = gd_isset($this->cfg, []);
		
		if ($cfg && $goodsNos) {
			$togetherGoodsNo = [];
			foreach ($goodsNos as $goodsNo) {
				$sql = "SELECT togetherGoodsNo FROM " . DB_GOODS . " WHERE goodsNo = ?";

				
				$row = $this->db->query_fetch($sql, ["i", $goodsNo], false);
				if ($row['togetherGoodsNo']) {
					$togetherGoodsNo = array_merge($togetherGoodsNo, explode(",", $row['togetherGoodsNo']));
				}
			}

			
			if ($togetherGoodsNo) {
				$cfg['togetherGoodsNo'] = implode(",", $togetherGoodsNo);
			}
			
			/* 상품개별 적용은 주문상품이 1개일 때 적용 */
			if (count($goodsNos) == 1) {
				$sql = "SELECT * FROM wm_subGoodsConf WHERE goodsNo = ?";
				
				$row = $this->db->query_fetch($sql, ["i", $goodsNos[0]], false);

				
				if ($row && $row['useConfig']) {
					$cfg['deliveryYoils'] = $row['deliveryYoils']?explode(",", $row['deliveryYoils']):[];
					$cfg['deliveryCycle'] = $row['deliveryCycle']?explode(",", $row['deliveryCycle']):[];
					$cfg['deliveryEa'] = $row['deliveryEa']?explode(",", $row['deliveryEa']):[];
					$cfg['deliveryEaDiscount'] = $row['deliveryEaDiscount']?explode(",", $row['deliveryEaDiscount']):[];
					$cfg['deliveryEaDiscountType'] = gd_isset($row['deliveryEaDiscountType'], 'cycle');
					$cfg['useFirstDelivery'] = $row['useFirstDelivery']?1:0;
					$cfg['firstDeliverySno'] = gd_isset($row['firstDeliverySno'], 0);	
					$cfg['pause'] = gd_isset($row['pause'], 0);	
					
					//if(!empty($row['min_order']))
						$cfg['min_order']=$row['min_order'];

					//$cfg['memberGroupSno'] = gd_isset($row['memberGroupSno'], 0);	
					
				}
				
			}
		}
		return $cfg;
	}
	
	/**
	* 비밀번호 입력 키 추출 
	*
	* @return Array  숫자 비밀번호 
	*/
    public function getShuffleChars()
    {
        $chars = range(0,9);
        shuffle($chars);

        return $chars;
    }
	
	/**
    * 결제카드 목록 
    *
    * @param Integer|String $memNo 문자열인 경우는 회원 아이디, 숫자인 경우는 회원번호 
    *
    * @return Array
    */
	public function getOrderCards($memNo = null)
    {	
		if (empty($memNo)) {
			$memNo = Session::get("member.memNo");
		}
		
		if (!is_numeric($memNo)) {
			$sql = "SELECT memNo FROM " . DB_MEMBER . " WHERE memNo = ?";
			$row = $this->db->query_fetch($sql, ["i", $memNo], false);
			$memNo = $row['memNo'];
		} // endif 
		
		$policy = gd_policy('order.status');

		if ($memNo) {
			//wg-brown 카카오, 신용카드 한개씩 나오게하기
			$sql = '
				(
					SELECT *
					FROM wm_subCards
					WHERE method = "kakao" AND memNo = '.$memNo.'
					ORDER BY idx DESC
					LIMIT 1
				)
				UNION
				(
					SELECT *
					FROM wm_subCards
					WHERE method = "ini" AND memNo = '.$memNo.'
					ORDER BY idx DESC
					LIMIT 1
				)
			';
			//$sql = "SELECT * FROM  wm_subCards WHERE memNo = ? ORDER BY idx DESC";
			$list = $this->db->query_fetch($sql);
			//wg-brown 카카오, 신용카드 한개씩 나오게하기

			if ($list) {
				foreach ($list as $k => $v) {
					/* 카드별 설정 추출 */
					/*$sql = "SELECT cardType, cardCode, cardNm, backgroundColor, fontColor FROM wm_cardSet WHERE cardNm = ? LIMIT 0, 1";
					$row = $this->db->query_fetch($sql, ["s", $v['cardNm']], false);
					if ($row) {
						$path = dirname(__FILE__) . "/../../../data/cards/".$row['cardType']."_".$row['cardCode'];
						if (file_exists($path)) {
							$row['imageUrl'] = "/data/cards/".$row['cardType']."_".$row['cardCode'];
							$row['imagePath'] = $path;
						}
						
						$v = array_merge($v, $row);
					}*/
					
					/* 정기결제 신청 기록 여부 체크 */
					
					//if(\Request::getRemoteAddress()=="112.145.36.156"){
						$sql="SELECT b.* FROM wm_subApplyInfo a INNER JOIN wm_subSchedules b ON a.idx=b.idxApply WHERE idxCard = '{$v['idx']}'";
						$rows=$this->db->query_fetch($sql);
						
						$subCnt=0;
						foreach($rows as $kk =>$t){
							if($t['status']=='stop' && $t['autoExtend']!=1){
							}else{
								if(!empty($t['orderNo'])){
									$orderSQL="select orderStatus from ".DB_ORDER." where orderNo='{$t['orderNo']}'";
									$orderRow=$this->db->fetch($orderSQL);

									foreach($policy as $pk =>$tt){
										foreach($tt as $ttt =>$p){
											
											if($p['mode']==$orderRow['orderStatus']){
												
												if(substr($p['mode'],0,1)=='o' || substr($p['mode'],0,1)=='p' || substr($p['mode'],0,1)=='d' || substr($p['mode'],0,1)=='g'){
													$subCnt++;
												}
											}
										}
									}
								}else{
									$subCnt++;
								}
							}
						}

						$v['subCnt']=$subCnt;

					/*}else{

						$sql = "SELECT COUNT(a.idx) as cnt FROM wm_subApplyInfo a INNER JOIN wm_subSchedules b ON a.idx=b.idxApply WHERE idxCard = ? and (b.status!='stop' or (b.status='stop' and  a.autoExtend='1'))";

						$row = $this->db->query_fetch($sql, ["i", $v['idx']], false);
					
					
						if($row['cnt']>0)
							$v['subCnt']=$row['cnt'];
						else
							$v['subCnt']=0;
					}*/
					//$v['subCnt'] = gd_isset($row['cnt'], 0);
					
					$list[$k] = $v;
				}
			}
		} // endif 
		
		return gd_isset($list, []);
    } 
    
	/**
    * 결제카드 목록 
    *
    * @param Integer|String $memNo 문자열인 경우는 회원 아이디, 숫자인 경우는 회원번호 
    *
    * @return Array
    */
    public function getCards($memNo = null)
    {	
		if (empty($memNo)) {
			$memNo = Session::get("member.memNo");
		}
		
		if (!is_numeric($memNo)) {
			$sql = "SELECT memNo FROM " . DB_MEMBER . " WHERE memNo = ?";
			$row = $this->db->query_fetch($sql, ["i", $memNo], false);
			$memNo = $row['memNo'];
		} // endif 
		
		$policy = gd_policy('order.status');

		if ($memNo) {
			$sql = "SELECT * FROM  wm_subCards WHERE memNo = ? ORDER BY idx ASC";
			$list = $this->db->query_fetch($sql, ["i", $memNo]);
			if ($list) {
				foreach ($list as $k => $v) {
					/* 카드별 설정 추출 */
					/*$sql = "SELECT cardType, cardCode, cardNm, backgroundColor, fontColor FROM wm_cardSet WHERE cardNm = ? LIMIT 0, 1";
					$row = $this->db->query_fetch($sql, ["s", $v['cardNm']], false);
					if ($row) {
						$path = dirname(__FILE__) . "/../../../data/cards/".$row['cardType']."_".$row['cardCode'];
						if (file_exists($path)) {
							$row['imageUrl'] = "/data/cards/".$row['cardType']."_".$row['cardCode'];
							$row['imagePath'] = $path;
						}
						
						$v = array_merge($v, $row);
					}*/
					
					/* 정기결제 신청 기록 여부 체크 */
					
					//if(\Request::getRemoteAddress()=="112.145.36.156"){
						$sql="SELECT b.*, a.autoExtend FROM wm_subApplyInfo a INNER JOIN wm_subSchedules b ON a.idx=b.idxApply WHERE idxCard = '{$v['idx']}'";
						$rows=$this->db->query_fetch($sql);

						$subCnt=0;
						foreach($rows as $kk =>$t){
							if($t['autoExtend']==1){
								$subCnt++;
							} else if($t['status']=='stop' && $t['autoExtend']!=1){
							}else{
								if(!empty($t['orderNo'])){
									$orderSQL="select orderStatus from ".DB_ORDER." where orderNo='{$t['orderNo']}'";
									$orderRow=$this->db->fetch($orderSQL);

									foreach($policy as $pk =>$tt){
										foreach($tt as $ttt =>$p){
											
											if($p['mode']==$orderRow['orderStatus']){
												
												if(substr($p['mode'],0,1)=='o' || substr($p['mode'],0,1)=='p' || substr($p['mode'],0,1)=='d' || substr($p['mode'],0,1)=='g'){
													$subCnt++;
												}
											}
										}
									}
								}else{
									$subCnt++;
								}
							}
						}

						$v['subCnt']=$subCnt;

					/*}else{

						$sql = "SELECT COUNT(a.idx) as cnt FROM wm_subApplyInfo a INNER JOIN wm_subSchedules b ON a.idx=b.idxApply WHERE idxCard = ? and (b.status!='stop' or (b.status='stop' and  a.autoExtend='1'))";

						$row = $this->db->query_fetch($sql, ["i", $v['idx']], false);
					
					
						if($row['cnt']>0)
							$v['subCnt']=$row['cnt'];
						else
							$v['subCnt']=0;
					}*/
					//$v['subCnt'] = gd_isset($row['cnt'], 0);
					
					$list[$k] = $v;
				}
			}
		} // endif 
		
		return gd_isset($list, []);
    } 
	
	/**
	* 카드 정보 추출 
	*
	* @param Integer $idx 카드 IDX
	* @return Array 카드 등록 정보 
	*/
	public function getCard($idx = null)
	{
		if ($idx) {
			$sql = "SELECT * FROM wm_subCards WHERE idx = ?";
			$info = $this->db->query_fetch($sql, ["i", $idx], false);
			if ($info) {

				if(empty($info['cardNm']) && $info['method']=="kakao"){
					$info['cardNm']="카카오페이";
				}
				$info['payKey'] = $this->decrypt($info['payKey']);
			}
		} // endif  
		
		return gd_isset($info, []);
	}
	
	/**
	* 카드 삭제 
	*
	* @param Integer $idx 카드 번호 
	* @param Boolean $chkMember  카드가 본인 소유인지 체크 
	* @param Boolean $useException Exception 처리 여부
	*
	* @return Boolean 
	* @throw Exception;
	*/
	public function deleteCard($idx, $chkMember = false, $useException = false) 
	{
		 if (!$idx) {
			return false;
		 }
		 
		$info = $this->getCard($idx);
		if (!$info) {
			return false;
		}
		
		/* 회원 정보 체크 */
		if ($chkMember) {
			$memNo = Session::get("member.memNo");
			if ($info['memNo'] != $memNo) 
				throw new Exception("본인이 등록한 카드만 삭제하실 수 있습니다.");
		} // endif 
		
		$affectedRows = $this->db->set_delete_db("wm_subCards", "idx = ?", ["i", $idx]);
	    
		return $affectedRows > 0;
	}
	
	/**
	* 카드비밀번호 체크 
	*
	* @param Integer $idxCard 카드 IDX 
	* @param String $cardPass 카드 비밀번호 
	* @param Boolean $useException 예외 발생 여부 
	* 
	* @return Boolean 
	* @throw Exception
	*/
	public function chkCardPass($idxCard = null, $cardPass = null, $useException = false)
	{
		if (!$idxCard) {
			if ($useException) {
				throw new Exception("결제할 카드를 선택하세요.");
			}
			
			return false;
		}
		
		if (!isset($cardPass)) {
			if ($useException) {
				throw new Exception("카드비밀번호를 입력하세요.");
			}
			return false;
		}
		
		$card = $this->getCard($idxCard);
		if (!$card) {
			if ($useException) {
				throw new Exception("결제 카드가 존재하지 않습니다.");
			}
			return false;
		}
		
		$result = $this->checkPasswordHash($cardPass, $card['password']);
		
		return $result;
	}
	
	/**
	* 정기배송 정보 및 스케줄등록 
	*
	* @param Array $postValue 주문정보 
	* @param Array $cartInfo 장바구니 상품 정보 
	
	* @param Integer $deliveryStamp 첫 배송일 
	* @param String $deliveryPeriod 배송주기 
	* @param Integer $deliveryEa 배송일
	* @param Boolean $useException 예외처리 유무 
	* 
	* @return Array
	*/
	public function createSchedule($postValue = [], $cartInfo = [], $deliveryPeriod = null, $deliveryEa = null,  $useException = false)
	{
		/* 로그인 및 필수 데이터 체크 S */
		if (!gd_is_login()) {
			if ($useException) {
				throw new Exception("로그인이 필요합니다.");
			}
			return [];
		}
		
		$cartSno = $postValue['cartSno']?$postValue['cartSno']:[];
		if (empty($cartSno) && $cartInfo) {
			foreach ($cartInfo as $values) {
				foreach ($values as $value) {
					foreach ($value as $v) {
						$cartSno[] =$v['sno'];
						$gnos[] = $v['goodsNo'];
					}
				}
			}
		}
		
		if (!$postValue || !$cartSno || !$deliveryPeriod || !$deliveryEa) {
			if ($useException) {
				throw new Exception("필수 데이터 누락 - 필수 항목 postValue , cartSno, deliveryPeriod, deliveryEa");
			}
			return [];
		}
		/* 로그인 및 필수 데이터 체크 E */
		
		$schedule = new \Component\Subscription\Schedule();
		$params = [
			'cartSno' => $cartSno,
			'deliveryPeriod' => $deliveryPeriod,
			'deliveryEa' => $deliveryEa,
		];


		
		
		$list = $schedule->getList($params);

			
		if(\Request::getRemoteAddress()=="112.146.205.124"){
			
			//exit;
		}

		//exit;
		if (!$list) {
			if ($useException) {
				throw new Exception("스케줄 정보가 존재하지 않습니다.");
			}
			return [];
		} // endif 
		
		$memNo = Session::get("member.memNo");
		$postValue['memNo'] = $memNo;
		
		if ($cartSno) {
			$where = $bind = [];
			foreach ($cartSno as $sno) {
				$where[] = "?";
				$this->db->bind_param_push($bind, "i", $sno);
			}
			
			$sql = "SELECT * FROM wm_subCart WHERE sno IN (" . implode(",", $where) . ")";
			$cartList = $this->db->query_fetch($sql, $bind);
		}
		
		if (!$cartList) {
			if ($useException) {
				throw new Exception("정기배송 신청 상품이 존재하지 않습니다.");
			}
			return [];
		}
		
		/* 신청정보 저장 S */
		$arrBind = $this->db->get_binding(DBTableField::tableWmSubApplyInfo(), $postValue, "insert", array_keys($postValue));
			
		$this->db->set_insert_db("wm_subApplyInfo", $arrBind['param'], $arrBind['bind'], "y");
		$idxApply = $this->db->insert_id();
		$postValue['idxApply'] = $idxApply;
		/* 신청정보 저장 E */
		
		$conf = $this->getCfg();
		if ($idxApply) {
			/* 신청 상품(공통) 정보 저장 START */
			foreach ($cartList as $c) {
				// 2024-02-01 wg-eric 정기배송 상품 자동 전환
				if($list[0]['subGoodsConf']['wgAutoChangeGoodsFl'] == 'y' && $list[0]['subGoodsConf']['wgAutoChangeGoodsNo'] !== false) {
					$c['goodsNo'] = $list[0]['subGoodsConf']['wgAutoChangeGoodsNo'];
					$c['optionSno'] = $list[0]['subGoodsConf']['wgAutoChangeOptionSno'];
				}
				if ($c['isTogetherGoods']) continue;
				
				if ($c['addGoodsNo']) {
					$c['addGoodsNo'] =  json_decode(gd_htmlspecialchars_stripslashes($c['addGoodsNo']));
					$c['addGoodsNo'] = json_encode($c['addGoodsNo']);
				}
				if ($c['addGoodsCnt']) {
					$c['addGoodsCnt'] =  json_decode(gd_htmlspecialchars_stripslashes($c['addGoodsCnt']));
					$c['addGoodsCnt'] = json_encode($c['addGoodsCnt']);
				}
				
				$c['idxApply'] = $idxApply;
				$arrBind = $this->db->get_binding(DBTableField::tableWmSubApplyGoods(), $c, "insert", array_keys($c));
				$this->db->set_insert_db("wm_subApplyGoods", $arrBind['param'], $arrBind['bind'], "y");
			} // endforeach 
			/* 신청 상품(공통) 정보 저장 E */
			
			/* 메인배송정보 저장 S */
			$arrBind = $this->db->get_binding(DBTableField::tableWmSubDeliveryInfo(), $postValue, "insert", array_keys($postValue));
			$this->db->set_insert_db("wm_subDeliveryInfo", $arrBind['param'], $arrBind['bind'], "y");
			/* 메인배송정보 저장 E */
			
			/* 스케줄 정보 저장 S */
			foreach ($list as $key => $li) {
				$data = $postValue;
				$data['deliveryStamp'] = $li['deliveryStamp'];
				$arrBind = $this->db->get_binding(DBTableField::tableWmSubSchedules(), $data, "insert", array_keys($data));
				$this->db->set_insert_db("wm_subSchedules", $arrBind['param'], $arrBind['bind'], "y");
				
				$idxSchedule = $this->db->insert_id();
				foreach ($cartList as $c) {
					// 2024-02-01 wg-eric 정기배송 상품 자동 전환
					if($li['subGoodsConf']['wgAutoChangeGoodsFl'] == 'y' && $li['subGoodsConf']['wgAutoChangeGoodsNo'] !== false) {
						if($key >= 1) {
							$c['goodsNo'] = $li['subGoodsConf']['wgAutoChangeGoodsNo'];
							$c['optionSno'] = $li['subGoodsConf']['wgAutoChangeOptionSno'];
						}
					}

					if ($c['isTogetherGoods']) continue;
					
					if ($c['addGoodsNo']) {
						$c['addGoodsNo'] =  json_decode(gd_htmlspecialchars_stripslashes($c['addGoodsNo']));
						$c['addGoodsNo'] = json_encode($c['addGoodsNo']);
					} // endif 
					
					if ($c['addGoodsCnt']) {
						$c['addGoodsCnt'] =  json_decode(gd_htmlspecialchars_stripslashes($c['addGoodsCnt']));
						$c['addGoodsCnt'] = json_encode($c['addGoodsCnt']);
					} // endif 
					
					$c['idxSchedule'] = $idxSchedule;
					$arrBind = $this->db->get_binding(DBTableField::tableWmSubSchedulesGoods(), $c, "insert", array_keys($c));
					$this->db->set_insert_db("wm_subSchedulesGoods", $arrBind['param'], $arrBind['bind'], "y");
				} // endforeach 
			} // endforeach 
			/* 스케줄 정보 저장 E */
			
			/* 전체 스케줄 정보 추출 */
			return $this->getSchedules($idxApply);
		} // endif 
		
		return [];
	}
	
	/**
	* 정기결제 신청기록 모두 삭제 
	*
	* @param Integer $idxApply
	*/
	public function deleteScheduleAll($idxApply = null)
	{
		if ($idxApply) {
			$sql = "SELECT idx FROM wm_subSchedules WHERE idxApply = ?";
			$list = $this->db->query_fetch($sql, ["i", $idxApply]);
			if ($list) {
				foreach ($list as $li) {
					$this->db->set_delete_db("wm_subSchedulesGoods", "idxSchedule = ?", ["i", $li['idx']]);
				}
			}
			$this->db->set_delete_db("wm_subSchedules", "idxApply = ?", ["i", $idxApply]);
			$this->db->set_delete_db("wm_subApplyInfo", "idx = ?", ["i", $idxApply]);
			$this->db->set_delete_db("wm_subApplyGoods", "idxApply = ?", ["i", $idxApply]);
			$this->db->set_delete_db("wm_subDeliveryInfo", "idxApply = ?", ["i", $idxApply]);
		}
	}
	
	/**
	* 스케줄 목록 추출 
	*
	* @param Integer $idxApply 신청 IDX
	* 
	* @return Array
	*/
	public function getSchedules($idxApply = null)
	{
		if ($idxApply) {
			$sql = "SELECT * FROM wm_subSchedules WHERE idxApply = ? ORDER BY deliveryStamp";
			

			$list = $this->db->query_fetch($sql, ["i", $idxApply]);

			if ($list) {
				foreach ($list as $k => $v) {

					$v['deliveryYoilStr'] = $this->getYoilStr($v['deliveryStamp']);
					$list[$k] = $v;
				}
			}
		}

		return gd_isset($list, []);
	}
	
	/**
	* 정기결제 회차별 신청 정보 
	*
	* @param Integer $idx 신청번호 
	*
	* @return Array
	*/
	public function getSchedule($idx = null)
	{
		if ($idx) {
			$sql = "SELECT s.*, a.deliveryPeriod, a.deliveryEa, a.autoExtend, a.idxCard, m.memId, m.memNm FROM wm_subSchedules AS s 
							INNER JOIN wm_subApplyInfo AS a ON s.idxApply = a.idx 
							INNER JOIN " . DB_MEMBER . " AS m ON s.memNo = m.memNo 
							WHERE s.idx = ?";
			$info = $this->db->query_fetch($sql, ['i', $idx], false);
			if ($info) {
				$conf = $this->getCfg();
				if ($info['deliveryStamp'] > 0) {
					$info['payStamp'] = $info['deliveryStamp'] - (60  * 60 * 24 * $conf['payDayBeforeDelivery']);
					$info['smsStamp'] = $info['deliveryStamp'] - (60  * 60 * 24 * $conf['smsPayBeforeDay']);
				}
				$info['goods'] = $this->getScheduleGoods($idx);
			}
		} // endif 
		
		return gd_isset($info, []);
	}
	
	/**
	* 정기결제 회차별 신청 상품정보 
	*
	* @param Integer $idx 신청번호
	* @return Array
	*/
	public function getScheduleGoods($idx = null)
	{
		if ($idx) {
			$sql = "SELECT * FROM wm_subSchedulesGoods WHERE idxSchedule = ? ORDER BY idx";
			$list = $this->db->query_fetch($sql, ["i", $idx]);
		} // enif

		return gd_isset($list, []);
	}
	
	/**
	*  PG 결제 처리 
	*
	* @param Integer $idx 스케줄 등록 번호
	* @param String $orderNo 주문번호 
	* @param Boolean $useException 예외 출력 여부 
	*
	* @return Boolean 
	* @throw Exception
	*/
	public function pay($idx = null, $orderNo = null, $useException = false)
	{
		if (!$idx) {
			if ($useException) {
				throw new Exception("스케줄 등록번호가 누락되었습니다.");
			}
			return false;
		}
		
		$order = new \Component\Order\OrderAdmin();
		
		$info = $this->getSchedule($idx);
		if (!$info) {
			if ($useException) {
				throw new Exception("결제 정보가 존재하지 않습니다.");
			}
			return false;
		}

		if ($info['status'] != 'ready') {
			if ($useException) {
				throw new Exception("결제 예정 상태가 아닙니다.");
			}
			return false;
		}

		/* 결제카드 체크 S */
		if (!$info['idxCard']) {
			if ($useException) {
				throw new Exception("결제카드가 존재하지 않습니다.");
			}
			return false;
		}


		$card = $this->getCard($info['idxCard']);
		if (!$card || !$card['payKey']) {
			if ($useException) {
				throw new Exception("결제카드가 존재하지 않습니다.");
			}
			return false;
		}
		/* 결제카드 체크 E */
		
		$orderView = [];
		if ($orderNo) {
			$orderView = $order->getOrderView($orderNo);
		} // endif 
		

		/* 주문접수 처리 S */
		if (empty($orderView)) {
			if (!$info['goods']) {
				if ($useException) {
					throw new Exception("주문상품이 존재하지 않습니다.");
				}
				return false;
			} // endif
						
			$cart = new \Component\Subscription\CartSubAdmin($info['memNo']);
			$cart->setCartRemove();
			$cartSno = $goodsNos = [];
			$goodsCnt = 0;
			
			$goods = new \Component\Goods\Goods();

			foreach ($info['goods'] as $c) {
				$c['addGoodsCnt'] =  $c['addGoodsCnt']?json_decode(gd_htmlspecialchars_stripslashes($c['addGoodsCnt']), true):[];
				$c['addGoodsNo'] =  $c['addGoodsNo']?json_decode(gd_htmlspecialchars_stripslashes($c['addGoodsNo']), true):[];
				
				$getData = $goods->getGoodsInfo($c['goodsNo']);

				$arrData = [
					'goodsPrice'=>$getData['goodsPrice'],
					'goodsNo' => $c['goodsNo'],
					'optionSno' => $c['optionSno'],
					'goodsCnt' => $c['goodsCnt'],
					'addGoodsNo' => $c['addGoodsNo'],
					'addGoodsCnt' => $c['addGoodsCnt'],
					'optionText' => $c['optionText'],
					'deliveryEa' => $info['deliveryEa'],
				];
		

				try{
					
					$cartSno[] = $cart->saveGoodsToCart($arrData);	
				}catch(Exception $e){
					return false;
				}

				$goodsNos[] = $c['goodsNo'];
				$goodsCnt = $c['goodsCnt'];
			} // endforeach

			$seq = $this->getSeq($info['idxApply']);
			$gifts = $this->getGifts($goodsNos, $seq + 1);


			if ($gifts) {
				foreach ($gifts as $list) {
					foreach ($list as $g) {
						$sql = "SELECT go.sno FROM " . DB_GOODS_OPTION . " AS go INNER JOIN " . DB_GOODS . " AS g ON go.goodsNo = g.goodsNo WHERE g.delFl='n' AND g.goodsSellFl='y' AND go.optionSellFl='y' AND go.goodsNo = ? ORDER BY go.optionNo";
						$ro = $this->db->query_fetch($sql, ["i", $g['goodsNo']], false);
						if (!$ro) continue;
						
						$arrData = [
							'goodsNo' => $g['goodsNo'],
							'optionSno' => $ro['sno'],
							'goodsCnt' => $goodsCnt,
							'deliveryEa' => $info['deliveryEa'],
							'isGift' => 1,
						];
						
						$sno= $cart->saveGoodsToCart($arrData);	
						$param = [
							'isGift = ?',
						];
						
						$bind = [
							'ii',
							1,
							$sno,
						];
						
						$this->db->set_update_db("wm_subCartAdmin", $param, "sno = ?", $bind);
						
						$cartSno[] = $sno;
					} // endforeach 
				} // endforeach 
			}

			if ($cartSno) {
				foreach ($cartSno as $sno) {
					 $param = [
							'seq = ?',
							'deliveryEa = ?',
					  ];
								 
					$bind = [
						'iii',
						$seq,
						$info['deliveryEa'],
						$sno,
					];

					$this->db->set_update_db("wm_subCartAdmin", $param, "sno = ?", $bind);
			     } // endforeach 
			} // endif 
			
			$order = new \Component\Order\OrderAdmin();
			
			if(empty($info['orderEmail'])){
				$msql="select email from ".DB_MEMBER." where memNo=?";
				$mrow=$this->db->query_fetch($msql,['i',$info['memNo']],false);

				if(!empty($mrow['email']))
					$info['orderEmail']=$mrow['email'];
				else{
					$oSQL="select oi.orderEmail from ".DB_ORDER." o INNER JOIN ".DB_ORDER_INFO." oi ON o.orderNo=oi.orderNo where o.memNo='{$info['memNo']}'  and oi.orderEmail<>'' order by oi.sno DESC limit 0,1";
					$oROW=$this->db->fetch($oSQL);
					$info['orderEmail']=$oROW['orderEmail'];

					
				}

				if(empty($info['orderEmail'])){
					$info['orderEmail']="info@nukhan.co.kr";
				}

			}
			// 주문서 정보 체크
			$postValue = $order->setOrderDataValidation($info, false);



			unset($postValue['goods']);
			$postValue['settleKind'] = 'pc';
			
			$address = $postValue['receiverAddress'];
			$cartInfo = $cart->getCartGoodsData($cartSno, $address, null, true, false, $postValue);
			
			$postValue['settlePrice'] = $cart->totalSettlePrice;
			// 주문서 작성시 발생되는 금액을 장바구니 프로퍼티로 설정하고 최종 settlePrice를 산출 (사용 마일리지/예치금/주문쿠폰)
            $orderPrice = $cart->setOrderSettleCalculation($postValue);
			 
			/*
            * 주문정보 발송 시점을 트랜잭션 종료 후 진행하기 위한 로직 추가
            */
            //$smsAuto = \App::load('Component\\Sms\\SmsAuto');
            //$smsAuto->setUseObserver(true);
            //$mailAuto = \App::load('Component\\Mail\\MailMimeAuto');
            //$mailAuto->setUseObserver(true);
			
			$smsAuto = new \Component\Sms\SmsAuto();
            $smsAuto->setUseObserver(true);
			$mailAuto = new \Component\Mail\MailMimeAuto();
            $mailAuto->setUseObserver(true);

            // 주문 저장하기 (트랜젝션)
            $result = \DB::transaction(function () use ($order, $cartInfo, $postValue, $orderPrice, $cart) {
				// 장바구니에서 계산된 전체 과세 비율 필요하면 추후 사용 -> $cart->totalVatRate
				$postValue['settleKind'] = 'pc';
				$postValue['orderChannelFl'] = 'shop';
				return $order->saveOrderInfo($cartInfo, $postValue, $orderPrice);
            });
            
			$smsAuto->notify();
            $mailAuto->notify();
			
			
			if ($result) {
				// PG에서 정확한 장바구니 제거를 위해 주문번호 저장 처리
                if (empty($cart->cartSno) === false) {
					if (is_array($cart->cartSno)) {
						$tmpWhere = [];
						foreach ($cart->cartSno as $sno) {
							$tmpWhere[] = $this->db->escape($sno);
						}
						$arrWhere[] = 'sno IN (' . implode(' , ', $tmpWhere) . ')';
						unset($tmpWhere);
					} elseif (is_numeric($cartSno)) {
						$arrWhere[] = 'sno = ' . $cartSno . '';
					}

					$arrBind = [
						's',
						$order->orderNo,
					];
					$this->db->set_update_db("wm_subCartAdmin", 'tmpOrderNo = ?', implode(' AND ', $arrWhere), $arrBind);
				}

                // 장바구니 통계 구매여부 저장 처리
                $eventConfig = \App::getConfig('event')->toArray();
                if ($eventConfig['cartOrderStatistics'] !== 'n') {
					$cartStatistics = new GoodsStatistics();
                    $cartStatistics->setCartOrderStatistics($cart->cartSno, $order->orderNo);
                }
					
				// 장바구니 비우기
                $cart->setCartRemove($order->orderNo);
				 
				$orderNo = $order->orderNo;
				$orderView = $order->getOrderView($orderNo);
			} // endif 
		} // endif
		
		if (!$orderView) {
			if ($useException) {
				throw new Exception("주문정보가 존재하지 않습니다.");
			}
			return false;
		} // endif 
		/* 주문접수 처리 E */
		
		/* 결제 처리 S */
		$isSuccess = false;
		$settlePrice = (Integer)$orderView['settlePrice'];
		
		$server = Request::server()->toArray();

		//if ($server['REMOTE_ADDR'] == '121.173.70.193') {

		
		// 결제 시도 상태이고  결제금액이 0이상인 경우 PG 결제 처리
		if (substr($orderView['orderStatus'], 0, 1)  == 'f' && $settlePrice > 0) {
			/* 주문상품 체크 */
			if (!$orderView['goods']) {
				if ($useException) {
					throw new Exception("주문상품이 존재하지 않습니다.");
				}
                return false;
			} // endif 
			
			$arrOrderGoodsSno = [];
			//$MerchantReserved1="";
            foreach ($orderView['goods'] as $values) {
				foreach ($values as $value) {
					foreach ($value as $v) {
						$arrOrderGoodsSno['sno'][] = $v['sno'];

						//부가세가 있는경우는 부가세계산
						if($v['goodsTaxInfo']['0']=='t' && $v['goodsTaxInfo'][1] >0){
							
							$Tax=$settlePrice*$v['goodsTaxInfo'][1]/100;
							$Tax=round($Tax);
							$MerchantReserved1="Tax=".$Tax;
						}
					}
				}
			} // endforeach 

		

			$param2 = $bind2 = [];
			
			$cfg = $this->getCfg();
			$inipay = new \Component\Subscription\Inicis\INIpay41Lib();
			//$inipay->m_inipayHome = dirname(__FILE__) . "/../../../subscription_module/inicis";   // INIpay Home (절대경로로 적절히 수정)
			$inipay->m_inipayHome = '/www/wowbiotech1_godomall_com/subscription_module/inicis';

            $inipay->m_keyPw = "1111";        // 키패스워드(상점아이디에 따라 변경)
            $inipay->m_type = "reqrealbill";       // 고정 (절대 수정금지)
            $inipay->m_pgId = "INIpayBill";       // 고정 (절대 수정금지)
            $inipay->m_payMethod = "Card";           // 고정 (절대 수정금지)
            $inipay->m_billtype = "Card";              // 고정 (절대 수정금지)
            $inipay->m_subPgIp = "203.238.3.10";       // 고정 (절대 수정금지)
            $inipay->m_debug = "true";        // 로그모드("true"로 설정하면 상세한 로그가 생성됨)
            $inipay->m_mid = $cfg['mid'];         // 상점아이디
            $inipay->m_billKey = $card['payKey'];        // billkey 입력
            $inipay->m_goodName = iconv("UTF-8", "EUC-KR", $orderView['orderGoodsNm']);       // 상품명 (최대 40자)
            $inipay->m_currency = "WON";       // 화폐단위
            $inipay->m_price = $settlePrice;        // 가격
            $inipay->m_buyerName = iconv("UTF-8", "EUC-KR", $orderView['orderName']);       // 구매자 (최대 15자)
            $inipay->m_buyerTel = str_replace("-", "", $orderView['orderCellPhone']);       // 구매자이동전화
            $inipay->m_buyerEmail = $orderView['orderEmail'];       // 구매자이메일

			//$testId = \Session::get("member.memId");

			//if($testId=="carol"){
			//	$inipay->m_cardQuota = "03";       // 할부기간
				
			//}else{
				$inipay->m_cardQuota = "00";       // 할부기간
				
			//}

            $inipay->m_quotaInterest = "0";      // 무이자 할부 여부 (1:YES, 0:NO)
			
            $inipay->m_url = $cfg['domain'];    // 상점 인터넷 주소
            $inipay->m_cardPass = "";       // 키드 비번(앞 2자리)
            $inipay->m_regNumber = "";       // 주민 번호 및 사업자 번호 입력
            $inipay->m_authentification = "01"; //( 신용카드 빌링 관련 공인 인증서로 인증을 받은 경우 고정값 "01"로 세팅)
            $inipay->m_oid = $orderNo;        //주문번호
            $inipay->m_merchantreserved1 = $MerchantReserved1;  // Tax : 부가세 , TaxFree : 면세 (예 : Tax=10&TaxFree=10)
            $inipay->m_merchantreserved2 = $MerchantReserved2;  // 예비2
            $inipay->m_merchantreserved3 = $MerchantReserved3;  // 예비3
            $inipay->startAction();
            $tid = $inipay->m_tid;

            $settlelog  = '';
            $settlelog  .= '===================================================='.PHP_EOL;
            $settlelog  .= 'PG명 : 이니시스 정기결제'.PHP_EOL;
            $settlelog .= "거래번호 : ". $tid . chr(10);
            $settlelog .= "결과코드 : ". $inipay->m_resultCode . PHP_EOL;
			$settlelog .= "결과내용 : " . iconv("EUC-KR", "UTF-8",strip_tags($inipay->m_resultMsg)) . PHP_EOL;


			 if ($inipay->m_resultCode == "00") {
				 $settlelog .= "처리결과 : 성공".PHP_EOL;
				 $isSuccess = true;
				 $arrOrderGoodsSno['changeStatus'] = "p1";
                
				
				$order->statusChangeCodeP($orderNo, $arrOrderGoodsSno); // 입금확인으로 변경
				
				$param = [
					'method =?',
					'status = ?',
					'orderNo = ?',
				];
				
				$bind = [
					'sssi',
					'ini',
					'paid', 
					$orderNo,
					$idx,
				];
				$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
				$settlelog .= "승인번호 : " . $inipay->m_authCode . PHP_EOL;
				$settlelog .= "승인날짜 : " . $inipay->m_pgAuthDate . PHP_EOL;
				$settlelog .= "승인시각 : " . $inipay->m_pgAuthTime . PHP_EOL;
				$settlelog .= "부분취소가능여부 : " . $inipay->m_prtcCode . PHP_EOL;
				$settlelog	.= '===================================================='.
				
				$param2 = [
					'pgName = ?',
					'pgResultCode = ?',
					'pgTid = ?',
					'pgAppNo = ?',
					'pgAppDt = ?',
				 ];
				
				$bind2 = [
					'sssss',
					'sub',
					$inipay->m_resultCode,
					$tid,
					$inipay->m_authCode,
					$inipay->m_pgAuthDate,
				];
				
				$param = [
					'orderNo = ?',
				];
				
				$bind = [
					'si', 
					$orderNo,
					$idx,
				];
				
				$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);

				$memNo = \Session::get("member.memNo");

				if(!empty($memNo)){

					$this->db->query("update ".DB_MEMBER." set groupSno ='{$cfg['memberGroupSno']}' where memNo='$memNo'");

					if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
						\Session::set('member.groupSno', $cfg['memberGroupSno']);
					}
				}else{
					
					$memNo=$info['memNo'];

					$ChkSQL="select count(idx) as cnt from wm_subSchedules where memNo=? and status='ready'";
					$ChkROW = $this->db->query_fetch($ChkSQL,['i',$memNo],false);

					if($info['autoExtend']!=1 && $ChkROW['cnt']<1){
						$this->db->query("update ".DB_MEMBER." set groupSno ='1' where memNo='$memNo'");

						if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
							\Session::set('member.groupSno', 1);
						}
					}else{
						$this->db->query("update ".DB_MEMBER." set groupSno ='{$cfg['memberGroupSno']}' where memNo='$memNo'");

						if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
							\Session::set('member.groupSno', $cfg['memberGroupSno']);
						}
					}

				}
			 } else {
				$settlelog .= "처리결과 : 실패".chr(10);
				$settlelog .= "결과코드 : ". $inipay->m_resultCode . PHP_EOL;
				$settlelog .= "결과내용 : " . iconv("EUC-KR", "UTF-8",strip_tags($inipay->m_resultMsg)) . PHP_EOL;

				 $param = [
					'orderStatus = ?',
				 ];
				 
				 $bind = [
					'ss',
					'f3',
					$orderNo,
				 ];
				 //실패로그 남김 시작
				 
    		     $conf = $this->getCfg();
				 if($this->schedule==1 && !empty($conf['order_fail_retry'])){
    				 $table="wm_subscription_fail";
    				 $FailSQL="insert into ".$table." set idxApply=?,scheduleIdx=?,fail_regdt=sysdate(),retry_config=?,fail_log='{$settlelog}'";
    				 $this->db->bind_query($FailSQL,['iis',$info['idxApply'],$idx,$conf['order_fail_retry']]);
				 }
				 
				 //실패로그 남김 종료
				 $this->db->set_update_db(DB_ORDER, $param, "orderNo = ?", $bind);
				 $this->db->set_update_db(DB_ORDER_GOODS, $param, "orderNo = ?", $bind);
				 /* 처리 실패했을경우 실 결제가 되는 경우도 있는데 이런 경우는 결제 취소 처리 */
				  if ($tid) {
					$inipay = new \Component\Subscription\Inicis\INIpay41Lib();
                    //$inipay->m_inipayHome = dirname(__FILE__) . "/../../../subscription_module/inicis";   // INIpay Home (절대경로로 적절히 수정)
					$inipay->m_inipayHome = '/www/wowbiotech1_godomall_com/subscription_module/inicis';
                    $inipay->m_keyPw = "1111";        // 키패스워드(상점아이디에 따라 변경)
                    $inipay->m_type = "cancel";       // 고정 (절대 수정금지)
                    $inipay->m_pgId = "INIpayBill";       // 고정 (절대 수정금지)
                    $inipay->m_subPgIp = "203.238.3.10";       // 고정 (절대 수정금지)
                    $inipay->m_debug = "true";        // 로그모드("true"로 설정하면 상세한 로그가 생성됨)
                    $inipay->m_mid = $cfg['mid'];         // 상점아이디
                    $inipay->m_tid = $tid;
                    $inipay->m_merchantreserved = $MerchantReserved1;  // Tax : 부가세 , TaxFree : 면세 (예 :
					
                    $inipay->startAction();
                    $settlelog .= "";
                    $settlelog .= '====================================================' . PHP_EOL;
                    $settlelog .= 'PG명 : 이니시스 정기결제 취소' . PHP_EOL;

                    $settlelog .= "결과코드 : " . $inipay->m_resultCode . PHP_EOL;
                    $settlelog .= "결과내용 : " . iconv("EUC-KR", "UTF-8", $inipay->m_resultMsg) . PHP_EOL;
                    $settlelog .= "취소날짜 : " . $inipay->m_pgCancelDate . PHP_EOL;
                    $settlelog .= "취소시각 : " . $inipay->m_pgCancelTime . PHP_EOL;
                    $settlelog	.= '===================================================='.PHP_EOL;
                } else { 
					// TID도 존재하지 않는 경우 취소 처리 
				} // endif 
			 } // endif 
			 
			 PHP_EOL;
			 $param2[] = 'orderPGLog = ?';
			 $this->db->bind_param_push($bind2, "s", $settlelog);
			 $this->db->bind_param_push($bind2, "s", $orderNo);
			 $this->db->set_update_db(DB_ORDER, $param2, "orderNo = ?", $bind2);
		} // endif 
		
		if($isSuccess) {
			$sql = "SELECT m.memId FROM es_order o LEFT JOIN es_member m ON o.memNo = m.memNo WHERE o.orderNo = '". $orderView['orderNo'] ."'";
			$member = $this->db->query_fetch($sql)[0];

			if($member['memId']) {
				$notifly = \App::load('Component\\Notifly\\Notifly');
				$memberInfo = [];
				$memberInfo['memId'] = $member['memId'];
				$memberInfo['subscriptionPayFl'] = 'y';
				$notifly->setUser($memberInfo);
			}
		}
		return $isSuccess;
		/* 결제 처리 E */
	}
	
	/**
	* 정기결제 PG 취소
	*
	* @param String $orderNo 주문번호
	* @param Boolean $isApplyRefund 환불여부 체크 
	* @param String $msg 메세지
	* @param Boolean $useException 예외 출력여부 
	*
	* @return Boolean
	*/
	public function cancel($orderNo = null, $isApplyRefund = true, $msg = null, $useException = false)
	{
		 if (!$orderNo) {
			 if ($useException) {
				 throw new Exception("주문번호 누락");
			 }
            return false;
		 }
		
		 if (!$msg) {
            $msg = "관리자 취소";
		 }
		 
		 $inipay = new \Component\Subscription\Inicis\INIpay41Lib();
		 $orderReorderCalculation = new \Component\Order\ReOrderCalculation();
		
		 $row = $this->db->query_fetch("SELECT pgTid FROM " . DB_ORDER . " WHERE orderNo = ? LIMIT 0, 1", ["s", $orderNo], false);
		 if (!$row) {
			if ($useException) {
				 throw new Exception("주문정보가 존재하지 않습니다.");
			 }
			 return false;
		 }
		 
		 $tid = $row['pgTid'];
		 $cfg = $this->getCfg();
		 //$inipay->m_inipayHome = dirname(__FILE__) . "/../../../subscription_module/inicis";   // INIpay Home (절대경로로 적절히 수정)
		 $inipay->m_inipayHome = '/www/wowbiotech1_godomall_com/subscription_module/inicis';
        $inipay->m_keyPw = "1111";        // 키패스워드(상점아이디에 따라 변경)
        $inipay->m_type = "cancel";       // 고정 (절대 수정금지)
        $inipay->m_pgId = "INIpayBill";       // 고정 (절대 수정금지)
        $inipay->m_subPgIp = "203.238.3.10";       // 고정 (절대 수정금지)
        $inipay->m_debug = "true";        // 로그모드("true"로 설정하면 상세한 로그가 생성됨)
        $inipay->m_mid = $cfg['mid'];         // 상점아이디
        $inipay->m_tid = $tid;
        $inipay->m_merchantreserved = $MerchantReserved1;  // Tax : 부가세 , TaxFree : 면세 (예 :

        $inipay->startAction();
        $settlelog = "";
        $settlelog .= '====================================================' . PHP_EOL;
        $settlelog .= 'PG명 : 이니시스 정기결제 취소' . PHP_EOL;

        $settlelog .= "결과코드 : " . $inipay->m_resultCode . PHP_EOL;
        $settlelog .= "결과내용 : " . iconv("EUC-KR", "UTF-8", $inipay->m_resultMsg) . PHP_EOL;
        $settlelog .= "취소날짜 : " . $inipay->m_pgCancelDate . PHP_EOL;
        $settlelog .= "취소시각 : " . $inipay->m_pgCancelTime . PHP_EOL;
        if ($inipay->m_resultCode == "00") {
			if ($isApplyRefund) {
				$sql = "SELECT sno, orderStatus FROM " . DB_ORDER_GOODS . " WHERE orderNo= ?";
				$list = $this->db->query_fetch($sql, ["s", $orderNo]);
				if ($list) {
					 $arrData = ['mode' => 'refund', 'orderNo'=> $orderNo, "orderChannelFl" => "shop"];
					  foreach ($list as $li) {
						   $arrData['refund']['statusCheck'][$li['sno']] = $li['sno'];
						   $arrData['refund']['statusMode'][$li['sno']] = $li['orderStatus'];
						   $arrData['refund']['goodsType'][$li['sno']] = 'goods';
					  }
					  $return = \DB::transaction(function () use ($orderReorderCalculation, $arrData) {
						return $orderReorderCalculation->setBackRefundOrderGoods($arrData, 'refund');
					});
				} // endif 
			} // endif 
		}
		
		$settlelog	.= '===================================================='.PHP_EOL;
		$settlelog = $this->db->escape($settlelog);
		$orderNo = $this->db->escape($orderNo);
		$sql = "UPDATE " . DB_ORDER . " SET orderPGLog=concat(ifnull(orderPGLog,''),'".$settlelog."') WHERE orderNo='{$orderNo}'";
		$this->db->query($sql);
		
		return true;
	}
	
	/**
	* 결제 차수 추출 
	*
	* @param Integer $idxApply 신청 IDX 
	* @return Integer 결제 차수 
	*/
	public function getSeq($idxApply = null)
	{
		$seq = 0;
		if ($idxApply) {
			$sql = "SELECT COUNT(*) as cnt FROM wm_subSchedules WHERE status='paid' AND idxApply = ?";
			$row = $this->db->query_fetch($sql, ["i", $idxApply], false);
			$seq = gd_isset($row['cnt'], 0);
			/* 환불된 주문건 체크 */
			$cnt = 0;
			$sql = "SELECT o.orderStatus FROM wm_subSchedules AS s 
							INNER JOIN " . DB_ORDER . " AS o ON s.orderNo = o.orderNo 
							WHERE s.status='paid' AND s.idxApply = ? AND s.orderNo <> ''";
			$list = $this->db->query_fetch($sql, ["i", $idxApply]);
			if ($list) {
				foreach ($list as $li) {
					$orderStatus = substr($li['orderStatus'], 0, 1);
					if (!in_array($orderStatus, ["p", "g", "d", "s"])) $cnt++;
				}
			}
			
			$seq -= $cnt;
		}
		
		if ($seq < 0) $seq = 0;

		return $seq;
	}
	
	/**
	* 회차별 사은품 추출 
	*
	* @param Array $goodsNos 상품번호 
	* @param Integer 배송 회차 
	* @param Boolean 상품상세 정보 추출 
	*
	* @return Array
	*/
	public function getGifts($goodsNos = [], $seq = 0, $withGoods = false)
	{
		if (!$goodsNos) return [];
		
		if (!is_array($goodsNos)) $goodsNos = [$goodsNos];
		
		$sql = "SELECT * FROM wm_subGifts WHERE  isOpen='1'";
				
		if ($seq > 0) {
			$sql .= " AND orderCnt = ?";
			$this->db->bind_param_push($bind, "i", $seq);
		}
		$sql .= " ORDER BY listOrder, idx";
			
		$common = $this->db->query_fetch($sql, $bind);
		
		$list = [];
		foreach ($goodsNos as $goodsNo) {
			$bind = [
				'i',
				$goodsNo,
			];
			$sql = "SELECT * FROM wm_subGoodsGifts WHERE rootGoodsNo = ? AND isOpen='1'";
			if ($seq > 0) {
				$sql .= " AND orderCnt = ?";
				$this->db->bind_param_push($bind, "i", $seq);
			}
			
			$sql .= " ORDER BY listOrder, idx";
			
			$tmp = $this->db->query_fetch($sql, $bind);
			
			if (empty($tmp)) $tmp = $common;
			
			if ($tmp) {
				foreach($tmp as $t) {
					$list[$t['orderCnt']][$t['goodsNo']] = $t;
				}
			} // endif 
		} // endforeach
		
		foreach ($list as $k => $v) {
			$list[$k] = array_values($v);
		}
		
		if ($withGoods) {
			foreach ($list as $key => $_list) {
				foreach ($_list as $k => $v) {
					$goods = $this->getGoods([$v['goodsNo']], true);
					if ($goods && $goods[0]) {
						$v['goods'] = $goods[0];
						$list[$key][$k] = $v;
					}
				}
			}
		} // endif 
		
		return $list;
	}

	//정기결제 카드종류-이니시스카카오,네이버
	public function orderType($orderNo=0,$idx=0)
	{

		if(empty($orderNo) && empty($idx))
			return false;

		if(!empty($orderNo)){
		
			$sql="select idx,idxApply,method from wm_subSchedules where orderNo=?";
			$row = $this->db->query_fetch($sql,['i',$orderNo],false);

			$idx=$row['idx'];
		}

		if(!empty($row['method'])){
			
			return $row['method'];

		}else{

			$info = $this->getSchedule($idx);
			$card = $this->getCard($info['idxCard']);

			if(empty($card['method']))
				$card['method']="ini";

			return $card['method'];
		}

		

	
	}

	//결제일 기준 나머지 스케줄링날짜 재설정 함수
	public function schedule_set_date($scheduleIdx,$idxApply,$pay_date="",$mode="")
	{
	    if(empty($scheduleIdx) || empty($idxApply))
	        return false;
	        
	        if(empty($pay_date)){
	            $pay_date = date("Y-m-d");
	            
	        }
	        
	        //$subObj = new Subscription();
			
			 if(\Request::getRemoteAddress()=="182.216.219.157"){
			 
				gd_debug($scheduleIdx);
				gd_debug($idxApply);
				gd_debug($pay_date);
				gd_debug($mode);
				gd_debug("=========f===========");
				
			 }
	        
	        $schedule = new \Component\Subscription\Schedule();
	        
	        $sql          = "select * from wm_subApplyInfo where idx=?";
	        $applyInfoRow = $this->db->query_fetch($sql,['i',$idxApply]);
	        
	        $deliveryPeriod       = explode("_",$applyInfoRow['0']['deliveryPeriod']);
	        $period = $deliveryPeriod[0];
	        $periodUnit = $deliveryPeriod[1]?$deliveryPeriod[1]:"week";
	        
	        $sql          = "select * from wm_subSchedules where idxApply=? and idx>? and (status='ready' OR status='pause')";
	        //$sql          = "select * from wm_subSchedules where idxApply=? and idx>? and status<>'paid'";
	        $scheduleRows = $this->db->query_fetch($sql,['ii',$idxApply,$scheduleIdx]);
	        
	        $no=1;
			$list = [];

			 if(\Request::getRemoteAddress()=="182.216.219.157"){
				gd_debug($scheduleRows);
				gd_debug("=========s===========");
				
			 }	        
	        foreach($scheduleRows as $key =>$t){
	            $cfgSQL="select goodsNo from wm_subSchedulesGoods where idxSchedule=?";
	            $cfgRow = $this->db->query_fetch($cfgSQL,['i',$t['idx']]);
	            
	            foreach($cfgRow as $gKey =>$gt){
	                $gons[]=$gt['goodsNo'];
	            }
	            $conf = $this->getCfg($gons);
	            
	            if(empty($mode)){
	               $deliveryStamp = strtotime($pay_date);
	            }else if($mode=="change_date" || $mode=="scheduler"){
	                $deliveryStamp = $pay_date;
	            }
	            $firstStamp = $schedule->getFirstDay($deliveryStamp, $gnos);

				// 2024-10-28 wg-eric timestmap 형식인지 확인
				if(is_numeric($firstStamp) && (int)$firstStamp == $firstStamp) {
				} else {
					$firstStamp = strtotime($firstStamp);
				}

	            // 2024-08-22 wg-eric 배송 결제가 지정 결제일인 경우 추가
				if($applyInfoRow['0']['payMethodFl'] == 'dayPeriod') {
					if(end($list)) {
						$setDeliveryStamp = end($list);
					} else {
						$setDeliveryStamp = $firstStamp;
					}

					$newDate = new \DateTime();
					$newDate->setTimestamp($setDeliveryStamp);

					// 현재 날짜의 년도와 월을 가져옴
					$currentDay = $newDate->format('d');
					$currentYear = $newDate->format('Y');
					$currentMonth = $newDate->format('m');

					// 해당 달의 마지막 날 구하기
					$lastDayOfMonth = (int) $newDate->format('t');

					// 입력한 일이 해당 달의 마지막 일을 초과하는지 확인
					if ($applyInfoRow['0']['deliveryDayPeriod'] > $lastDayOfMonth) {
						$deliveryDayPeriod = $lastDayOfMonth;
					} else {
						$deliveryDayPeriod = $applyInfoRow['0']['deliveryDayPeriod'];
					}
					// 날짜를 설정
					$targetDate = new \DateTime("$currentYear-$currentMonth-$deliveryDayPeriod");

					// 주어진 날짜가 지난 경우 다음 달로 설정
					if ($newDate >= $targetDate) {
						$targetDate = new \DateTime("$currentYear-$currentMonth-01");
						$targetDate->modify('+1 month');

						// 현재 날짜의 년도와 월을 가져옴
						$targetYear = $targetDate->format('Y');
						$targetMonth = $targetDate->format('m');

						// 해당 달의 마지막 날 구하기
						$lastDayOfMonth = (int) $targetDate->format('t');

						// 입력한 일이 해당 달의 마지막 일을 초과하는지 확인
						if ($applyInfoRow['0']['deliveryDayPeriod'] > $lastDayOfMonth) {
							$deliveryDayPeriod = $lastDayOfMonth;
						} else {
							$deliveryDayPeriod = $applyInfoRow['0']['deliveryDayPeriod'];
						}
						// 날짜를 설정
						$targetDate = new \DateTime("$targetYear-$targetMonth-$deliveryDayPeriod");
					}

					// 타임스탬프 형식으로 출력
					$stamp = $targetDate->getTimestamp();

					$yoil = date("w", $stamp);
				
					$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
					$row = $this->db->query_fetch($sql, ["i", $stamp], false);
					$stamp = $stamp + (60  * 60 * 24 * gd_isset($conf['payDayBeforeDelivery'], 1));

					if ($row['isHoliday']) {
						while(1){
							$stamp = strtotime("+1 day", $stamp);
							$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
							$row = $this->db->query_fetch($sql, ["i", $stamp], false);
							if (!$row['isHoliday']){
								break;
							}
						}
					}

				} else if($applyInfoRow['0']['payMethodFl'] == 'period') {
					$priod = $period * $no;
					$str = "+{$priod} {$periodUnit}";
					$stamp = strtotime($str, $firstStamp);
					
					while(true){
						
						$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
						$row = $this->db->query_fetch($sql, ["i", $stamp], false);
						if ($row && $row['isHoliday']) {
							
							if ($row['replaceStamp']) {
								$stamp = $row['replaceStamp'];
							} else {
								
								$stamp = strtotime("+1 day", $stamp);
								
							}
						}else{
							break;
						}
						
					}
					
					$yoil = date("w", $stamp);
					
					if ($conf['deliveryYoils']) {
						
						if(!in_array($yoil, $conf['deliveryYoils'])){
							
							
							while(true){
								$stamp = strtotime("+1 day", $stamp);
								
								while(true){
									$sql = "SELECT * FROM wm_holiday WHERE stamp = ?";
									$row = $this->db->query_fetch($sql, ["i", $stamp], false);
									if ($row && $row['isHoliday']) {
										
										if ($row['replaceStamp']) {
											$stamp = $row['replaceStamp'];
										} else {
											$stamp = strtotime("+1 day", $stamp);
											
											
										}
									}else{
										break;
									}
								}
								
								
								$yoil = date("w", $stamp);
								if(in_array($yoil, $conf['deliveryYoils'])){
									
									break;
								}
								
							}
						}
					}
						   
					if(\Request::getRemoteAddress()=="182.216.219.157"){
						gd_debug(date("Y-m-d",$firstStamp));
						gd_debug(date("Y-m-d",$stamp));
						gd_debug("=========s===========");
					}	   
				}
				 
				$upSQL="update wm_subSchedules set deliveryStamp='$stamp' where idx=?";
				$this->db->bind_query($upSQL,['i',$t['idx']]);

				$no++;
				$list[] = $stamp;
	        }
	        
	        
	}
}