<?php 

namespace Component\Subscription;
use Component\Subscription\Subscription;
use App;
use Request;
use Exception;
use Session;
use Component\Database\DBTableField;
use Component\GoodsStatistics\GoodsStatistics;
//use Component\Subscription\Traits\PgCommon; // PG 공통
//use Component\Subscription\Traits\SubscriptionApply; // 정기결제 신청관련
//use Component\Traits\DateDay; // 날짜,요일관련
//use Component\Traits\Security;
//use Component\Traits\Common;
//use Component\Traits\GoodsInfo;
use Framework\Utility\ArrayUtils;

class IniApiPay 
{
    //use PgCommon, DateDay, GoodsInfo, Security, Common, SubscriptionApply;
    
    private $db="";
    protected $subObj = "";
    protected $subCfg="";
    public $schedule=0;
    
    public function __construct()
    {
        if(!is_object($this->db)){
            
            $this->db = App::load(\DB::class); 
        }
        
        $this->subObj = new Subscription();
        $this->subCfg = $this->subObj->getCfg();    
    }
    
    public function INIPay($idx = null, $orderNo = null, $useException = false)
    {
        header("Content-Type: text/html; charset=utf-8");
        
        if (!$idx) {
            if ($useException) {
                throw new Exception("스케줄 등록번호가 누락되었습니다.");
            }
            return false;
        }
        

        $cfg = $this->subObj->getCfg();
        
        $order = new \Component\Order\OrderAdmin();
        $info = $this->subObj->getSchedule($idx);
      
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
        
      
        $card = $this->subObj->getCard($info['idxCard']);
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
            
            $seq   = $this->subObj->getSeq($info['idxApply']);
            $gifts = $this->subObj->getGifts($goodsNo,$seq + 1);
            //$seq = $this->getSeq($info['idxApply']);
            //$gifts = $this->getGifts($goodsNos, $seq + 1);
            
            
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
                        $info['orderEmail']="tmp@re4day.co.kr";
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
            
            
            
            //step1. 요청을 위한 파라미터 설정
            //$key = "rKnPljRn5m6J9Mzz";
            $key = $this->subCfg['iniapiKey'];
            $type = "Billing";
            
            $paymethod = "Card";
            
            $timestamp = date("YmdHis");
            $clientIp = Request::getRemoteAddress();
            $mid = $this->subCfg['mid'];
            
            $url = $this->subCfg['domain'];
            $moid = $orderNo;
            //$goodName = iconv("UTF-8", "EUC-KR", $orderView['orderGoodsNm']);
            //$buyerName = iconv("UTF-8", "EUC-KR", $orderView['orderName']);
            $goodName = $orderView['orderGoodsNm'];
			$buyerName = $orderView['orderName'];
			$buyerEmail = $orderView['orderEmail']; 
            $buyerTel = str_replace("-", "", $orderView['orderCellPhone']);
            $price = $settlePrice;
            $billKey = $card['payKey']; 
            $authentification = "00";
            $cardQuota ="00";
            $quotaInterest = "0";
            
            // hash 암호화
            $hashData = hash("sha512",$key.$type.$paymethod.$timestamp.$clientIp.$mid.$moid.$price.$billKey);
            
            
            //step2. key=value 로 post 요청
            $data = array(
                'type' => $type,
                'paymethod' => $paymethod,
                'timestamp' => $timestamp,
                'clientIp' => $clientIp,
                'mid' => $mid,
                'url' => $url,
                'moid' => $moid,
                'goodName' => $goodName,
                'buyerName' => $buyerName,
                'buyerEmail' => $buyerEmail,
                'buyerTel' => $buyerTel,
                'price' => $price,
                'billKey' => $billKey,
                'authentification' => $authentification,
                'cardQuota' => $cardQuota,
                'quotaInterest' => $quotaInterest,
                'hashData' => $hashData
            );
            
            $url = "https://iniapi.inicis.com/api/v1/billing";
            
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=utf-8'));
            curl_setopt($ch, CURLOPT_POST, 1);
            
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response);
                        
            $tid = $result->tid;
            
            if ($result->resultCode == "00") {
                $settlelog .= "처리결과 : 성공".PHP_EOL;
                $isSuccess = true;
                $arrOrderGoodsSno['changeStatus'] = "p1";
                
                
                $order->statusChangeCodeP($orderNo, $arrOrderGoodsSno); // 입금확인으로 변경
                
                $param = [
					'pay_billingkey=?',
                    'method =?',
                    'status = ?',
                    'orderNo = ?',
                ];
                
                $bind = [
                    'ssssi',
					 $billKey,
                    'ini',
                    'paid',
                    $orderNo,
                    $idx,
                ];
				
				
                $this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
                $settlelog .= "결과메세지 : ".$result->resultMsg;
                $settlelog .= "승인번호 : " . $result->payAuthCode . PHP_EOL;
                $settlelog .= "승인날짜 : " . $result->payDate . PHP_EOL;
                $settlelog .= "승인시각 : " . $result->payTime . PHP_EOL;
                $settlelog .= "부분취소가능여부 : " . $result->prtcCode . PHP_EOL;
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
                        $result->resultCode,
                        $tid,
                        $result->payAuthCode,
                        $result->payDate,
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
					
					$match_data = preg_match('/admin/u',$server['SERVER_NAME'],$match);//2023.05.26웹앤모바일 관리자,사용자 한브라우져에서 동시 로그인 하는듯 하여 추가함

					//$log_content="회원번호:".$memNo."=||=관리자번호:".\Session::get('manager.sno')."=||=idx:".$idx;
					//$log_sql="insert into wm_manulpay_log set content='{$content}',regDt=sysdate()";
					//$this->db->query($log_sql);

                    if(!empty($memNo) && empty($match_data)){
                        //첫결제처부분
                        $this->db->query("update ".DB_MEMBER." set groupSno ='{$this->subCfg['memberGroupSno']}' where memNo='$memNo'");

						//로그
						$this->db->query("INSERT INTO wg_subscriptionMemberGroupLog(memNo, idx, afterMemberGroup, matchData, autoExtend, rowCnt, regDt) VALUES('{$memNo}', '{$idx}', '{$this->subCfg['memberGroupSno']}', '{$match_data}', '{$info['autoExtend']}', null, now())");

						if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
							\Session::set('member.groupSno', $this->subCfg['memberGroupSno']);
						}
                    }else{
                        //관리자수동결제 스케줄러결제부분
                        $memNo=$info['memNo'];
                        
                        $ChkSQL="select count(idx) as cnt from wm_subSchedules where memNo=? and status='ready'";
                        $ChkROW = $this->db->query_fetch($ChkSQL,['i',$memNo],false);
                        
                        if($info['autoExtend']!=1 && $ChkROW['cnt']<1){
                            $this->db->query("update ".DB_MEMBER." set groupSno ='1' where memNo='$memNo'");

							//로그
							$this->db->query("INSERT INTO wg_subscriptionMemberGroupLog(memNo, idx, afterMemberGroup, matchData, autoExtend, rowCnt, regDt) VALUES('{$memNo}', '{$idx}', '1', '{$match_data}', '{$info['autoExtend']}', '{$ChkROW['cnt']}', now())");

							if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
								\Session::set('member.groupSno', 1);
							}
                        }else{
                            $this->db->query("update ".DB_MEMBER." set groupSno ='{$this->subCfg['memberGroupSno']}' where memNo='$memNo'");

							//로그
							$this->db->query("INSERT INTO wg_subscriptionMemberGroupLog(memNo, idx, afterMemberGroup, matchData, autoExtend, rowCnt, regDt) VALUES('{$memNo}', '{$idx}', '{$this->subCfg['memberGroupSno']}', '{$match_data}', '{$info['autoExtend']}', '{$ChkROW['cnt']}', now())");

							if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
								\Session::set('member.groupSno', $this->subCfg['memberGroupSno']);
							}
                        }
                        
                        $sSQL="select idxApply,deliveryStamp from wm_subSchedules where idx=?";
                        $sRow=$this->db->query_fetch($sSQL,['i',$idx]);
						
						//$deliveryStamp =date("Y-m-d",$sRow[0]['deliveryStamp']);
						//$nowDate = date("Y-m-d");
						
						//if($deliveryStamp<$nowDate)
						//$sRow[0]['deliveryStamp']=time();//현재결제일을 넘김
						// 2024-10-29 wg-eric 1일씩 차이나서 수정함
						$sRow[0]['deliveryStamp']= strtotime(date('Y-m-d')) + (60 * 60 * 24 * gd_isset($cfg['payDayBeforeDelivery'], 1));
                        
                        
 if(\Request::getRemoteAddress()=="182.216.219.157"){
				//gd_debug($sRow);
				//gd_debug("=========ff===========");
				
			 }		 
			 
                        $this->subObj->schedule_set_date($idx,$sRow[0]['idxApply'],$sRow[0]['deliveryStamp'],'scheduler');
                        
                    }
            } else {
                $settlelog .= "처리결과 : 실패".chr(10);
                $settlelog .= "결과코드 : ". $result->resultCode . PHP_EOL;
                $settlelog .= "결과내용 : " . $result->resultMsg . PHP_EOL;
                
                //실패시 실패로그를 남김시작-해당 로그데이터는 결제 재시도시 이용함
                if($this->schedule==1 && !empty($cfg['order_fail_retry'])){
                    //$conf = $this->getCfg();
                    $table="wm_subscription_fail";
                    $FailSQL="insert into ".$table." set idxApply=?,scheduleIdx=?,fail_regdt=sysdate(),retry_config=?,fail_log='{$settlelog}'";
                    $this->db->bind_query($FailSQL,['iis',$info['idxApply'],$idx,$cfg['order_fail_retry']]);
                }
                //실패시 실패로그를 남김종료
                
                $param = [
                    'orderStatus = ?',
                ];
                
                $bind = [
                    'ss',
                    'f3',
                    $orderNo,
                ];
                
                $this->db->set_update_db(DB_ORDER, $param, "orderNo = ?", $bind);
                $this->db->set_update_db(DB_ORDER_GOODS, $param, "orderNo = ?", $bind);
                /* 처리 실패했을경우 실 결제가 되는 경우도 있는데 이런 경우는 결제 취소 처리 */
                if ($tid) {
                    
                    //step1. 요청을 위한 파라미터 설정

                    $type        = "Refund";
                    $msg         = "정기결제 취소";
                    
                    $hashData = hash("sha512",(string)$key.(string)$type.(string)$paymethod.(string)$timestamp.(string)$clientIp.(string)$mid.(string)$tid); // hash 암호화
                    
                    
                    //step2. key=value 로 post 요청
                    $data = array(
                        'type' => $type,
                        'paymethod' => $paymethod,
                        'timestamp' => $timestamp,
                        'clientIp' => $clientIp,
                        'mid' => $mid,
                        'tid' => $tid,
                        'msg' => $msg,
                        'hashData'=> $hashData
                    );
                    
                    
                    $url = "https://iniapi.inicis.com/api/v1/refund";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=utf-8'));
                    curl_setopt($ch, CURLOPT_POST, 1);
                    
                    $response = curl_exec($ch);
                    curl_close($ch);
                    
                    $cancle_result = json_decode($response);
                    
                    //step3. 요청 결과
                    
                    $settlelog .= "";
                    $settlelog .= '====================================================' . PHP_EOL;
                    $settlelog .= 'PG명 : 이니시스 정기결제 취소' . PHP_EOL;
                    
                    $settlelog .= "결과코드 : " . $cancle_result->resultCode . PHP_EOL;
                    $settlelog .= "결과내용 : " . iconv("EUC-KR", "UTF-8", $cancle_result->resultMsg) . PHP_EOL;
                    $settlelog .= "취소날짜 : " . $cancle_result->cancelDate . PHP_EOL;
                    $settlelog .= "취소시각 : " . $cancle_result->cancelTime . PHP_EOL;
                    $settlelog .= '===================================================='.PHP_EOL;

                    
                    
                } else {
                    // TID도 존재하지 않는 경우 취소 처리
                } // endif
            } // endif
            
            
            $param2[] = 'orderPGLog = ?';
            $this->db->bind_param_push($bind2, "s", $settlelog);
            $this->db->bind_param_push($bind2, "s", $orderNo);
            $this->db->set_update_db(DB_ORDER, $param2, "orderNo = ?", $bind2);
        } // endif
        
        return $isSuccess;
        /* 결제 처리 E */
    }
    
    
    public function INIcancel($orderNo = null, $isApplyRefund = true, $msg = null, $useException = false)
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
        
       // $inipay = new \Component\Subscription\Inicis\INIpay41Lib();
        $orderReorderCalculation = new \Component\Order\ReOrderCalculation();
        
        $row = $this->db->query_fetch("SELECT pgTid FROM " . DB_ORDER . " WHERE orderNo = ? LIMIT 0, 1", ["s", $orderNo], false);
        if (!$row) {
            if ($useException) {
                throw new Exception("주문정보가 존재하지 않습니다.");
            }
            return false;
        }
        
        $tid = $row['pgTid'];
        $cfg = $this->subCfg;//$this->subObj->getCfg();
       
        $key = $this->subCfg['iniapiKey'];//"rKnPljRn5m6J9Mzz";
        
        $paymethod = "Card";
        
        $timestamp = date("YmdHis");
        $clientIp = Request::getRemoteAddress();
        $mid = $this->subCfg['mid'];
        
        $url = $this->subCfg['domain'];
        $moid = $orderNo;
        $type        = "Refund";
        $msg         = "주문번호 ".$orderNo." 정기결제 취소";
        
        $hashData = hash("sha512",(string)$key.(string)$type.(string)$paymethod.(string)$timestamp.(string)$clientIp.(string)$mid.(string)$tid); // hash 암호화
        
        
        //step2. key=value 로 post 요청
        $data = array(
            'type' => $type,
            'paymethod' => $paymethod,
            'timestamp' => $timestamp,
            'clientIp' => $clientIp,
            'mid' => $mid,
            'tid' => $tid,
            'msg' => $msg,
            'hashData'=> $hashData
        );
        
        
        $url = "https://iniapi.inicis.com/api/v1/refund";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=utf-8'));
        curl_setopt($ch, CURLOPT_POST, 1);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $cancle_result = json_decode($response);

        $settlelog = "";
        $settlelog .= '====================================================' . PHP_EOL;
        $settlelog .= 'PG명 : 이니시스 정기결제 취소' . PHP_EOL;
        
        $settlelog .= "결과코드 : " . $cancle_result->resultCode . PHP_EOL;
        $settlelog .= "결과내용 : " . iconv("EUC-KR", "UTF-8", $cancle_result->resultMsg) . PHP_EOL;
        $settlelog .= "취소날짜 : " . $cancle_result->cancelDate . PHP_EOL;
        $settlelog .= "취소시각 : " . $cancle_result->cancelTime . PHP_EOL;
        
        
        if ($cancle_result->resultCode == "00") {
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
}


?>