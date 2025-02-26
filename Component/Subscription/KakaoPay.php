<?php
namespace Component\Subscription;

use Component\Subscription\Subscription;
use Component\Order\OrderAdmin;
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

class KakaoPay
{
	protected $Cid="TCSUBSCRIP";
	protected $app_admin_key="a528fbcc20bece50a8f7f3439fcf78cb";
	protected $kakao_pay_ready_url="https://kapi.kakao.com/v1/payment/ready";
	protected $kakao_pay_approve_url="https://kapi.kakao.com/v1/payment/approve";

	protected $kakao_pay_url="https://kapi.kakao.com/v1/payment/subscription";
	protected $kakao_cancel_url="https://kapi.kakao.com/v1/payment/cancel";

	protected $approval_url="";
	protected $fail_url="";
	protected $cancel_url="";

	protected $db="";
	protected $subObj="";
	
	public $schedule=0;

	public function __construct()
	{
		
		if(!is_object($this->subObj))
			$this->subObj= new \Component\Subscription\Subscription();//new Subscription();

		$subCfg = $this->subObj->getCfg();


		if($subCfg['useType']=="real")
			$this->Cid="CG99929001";


		$server=\Request::server()->toArray();
		
		if($server['HTTPS']=="on")
			$httpd="https://";
		else
			$httpd="httpd://";

		$server['SERVER_NAME']=str_replace("www.","",$server['SERVER_NAME']);

	//if(\Request::getRemoteAddress()=="112.146.205.124"){

		if (\Request::isMobile()) {
			$this->approval_url=$httpd.$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=success";
			$this->fail_url=$httpd.$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=fail";
			$this->cancel_url=$httpd.$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=cancel";

		}else{

			$this->approval_url=$httpd."www.".$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=success";
			$this->fail_url=$httpd."www.".$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=fail";
			$this->cancel_url=$httpd."www.".$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=cancel";
		}

	/*}else{
		$this->approval_url=$httpd.$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=success";
		$this->fail_url=$httpd.$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=fail";
		$this->cancel_url=$httpd.$server['SERVER_NAME']."/subscription/kakao/order_ps.php?mode=cancel";
	}
	*/

		if(!is_object($this->db))
			$this->db=App::load(\DB::class);

	}

	//카카오페이 등록준비
	public function KakaoRegister($pass,$regiserType="order")
	{
		list($usec) = explode(' ', microtime());
		$tmpNo = sprintf('%04d', round($usec * 10000));

		$data['cid']=$this->Cid;
		$data['partner_order_id']=$tmpNo;
		$data['partner_user_id']=\Session::get("member.memId");
		$data['item_name']="카카오페이등록";
		$data['quantity']=1;
		$data['total_amount']=0;
		$data['vat_amount']=0;
		$data['tax_free_amount']=0;
		$data['approval_url']=$this->approval_url."&orderNo=".$tmpNo;

		$data['fail_url']=$this->fail_url;
		$data['cancel_url']=$this->cancel_url;




		$result = $this->Remote($data,$this->kakao_pay_ready_url);

		$result_data = json_decode(stripslashes($result));


		if (Request::isMobileDevice()) {
			$url=$result_data->next_redirect_mobile_url;
		}else{
			$url=$result_data->next_redirect_pc_url;
		}
		

		$tid = $result_data->tid;
		unset($data);

		$data['orderNo']=$tmpNo;
		$data['url']=$url;
		$data['tid']=$tid;
		$json_data = json_encode($data);

		$memNo = \Session::get("member.memNo");
		$sql="insert into wm_subscription_tmp set memNo=?,orderNo=?,tid=?,pass=?,regiserType='$regiserType',regDt=sysdate()";
		$this->db->bind_query($sql,['iiss',$memNo,$tmpNo,$tid,$pass]);
		return $json_data;
		
	}
	//카카오페이 카드최종등록
	public function kakaoSid($pg_token,$orderNo,$tid,$pass)
	{

		$memNo=\Session::get("member.memNo");
		
		//wg-brown 패스워드 주석
		//|| empty($pass)
		if(empty($memNo) || empty($pg_token) || empty($orderNo) || empty($tid))
			return false;

		$subscription=new subscription();

		$data['cid']=$this->Cid;
		$data['tid']=$tid;
		$data['partner_order_id']=$orderNo;
		$data['partner_user_id']=\Session::get("member.memId");
		$data['pg_token']=$pg_token;

		$result = $this->Remote($data,$this->kakao_pay_approve_url);
		$result_data = json_decode(stripslashes($result));

		$sid=$result_data->sid;
		$cardName="";

		if($result_data->payment_method_type=="MONEY"){
			$cardName="카카오페이";
		}else{
			foreach($result_data->card_info as $k =>$t){
				$cardName=$t->purchase_corp;
			}
		}

		if(empty($cardName))
			$cardName="카카오페이";

		
		$sid = $subscription->encrypt($sid);
		//wg-brown 페스워드 주석
		//$pass=$subscription->getPasswordHash($pass);
		$pass = '';

		if(!empty($sid)){


			$sql="insert into wm_subCards set memNo='$memNo',cardNm='{$cardName}',payKey='{$sid}',settleLog='',password='{$pass}',method='kakao'";

			$this->db->query($sql);

			//마이페이지 정기결제 카드등록시 기존 정기결제 최신등록카드로 모두변경시작
			$cardSQL="select idx from wm_subCards where memNo=? order by idx DESC limit 0,1";
			$cardRow = $this->db->query_fetch($cardSQL,['i',$memNo],false);

			$this->db->query("update wm_subApplyInfo set idxCard='{$cardRow['idx']}' where memNo='{$memNo}'");
			//마이페이지 정기결제 카드등록시 기존 정기결제 최신등록카드로 모두변경종료

		}

		
		$sql="select * from wm_subCart where memNo=?";
		$cartRow=$this->db->query_fetch($sql,['i',$memNo],false);

		if (\Request::isMobile()) {
			if(!empty($cartRow['sno'])){

				$this->db->query("delete from wm_subscription_tmp where memNo='$memNo'");
			?>
				<form name="oFrom" method="post" action="../subscription/order.php">
					<input type="hidden" name="cartSno[]" value="<?=$cartRow['sno']?>"/>
				</form>
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
				<script>
					if($(top.document).find('.mypage_menu.card_list_page').length > 0) {
						window.parent.location.reload();
						//window.close();	
						//opener.location.reload();
					}else {
						$.ajax({
							method: "POST",
							cache: false,
							url: "../../subscription/order.php",
							data: "",
							success: function (data) {
								var parent = window.parent.document;
								
								var cardReload = $(data).find('.card_list .card_list_page').html();
								$(top.document).find('.card_list .card_list_page').html(cardReload);
								$(top.document).find('.card_list .card_list_page input[name="card_method"][value=kakao]').click();
								$(top.document).find('.btn_pay_wrap #paymentBtn').click();
								$(top.document).find('.order_pay_iframe').remove();

								//var cardReload = $(data).find('.card_list .card_list_page').html();
								//opener.$('.card_list .card_list_page').html(cardReload);
								////가장 처음 카드 클릭후 결제 
								//opener.$('.card_list .card_list_page input[name="card_method"][value=kakao]').click();
								//opener.$('.btn_pay_wrap #paymentBtn').click();

								window.close();
							},
							error: function(){			
								alert('카드등록이 실패했습니다. 다시 시도해주세요.');
								window.close();				
							}
						 });
					
					}
					//opener.location.reload();//document.oFrom.submit();
				</script>
					
			<?php 
			}else{?>
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
				<script>
					if($(top.document).find('.mypage_menu.card_list_page').length > 0) {
						window.parent.location.reload();
						//window.close();	
						//opener.location.reload();
					}else {
						$.ajax({
							method: "POST",
							cache: false,
							url: "../../subscription/order.php",
							data: "",
							success: function (data) {
								var cardReload = $(data).find('.card_list .card_list_page').html();
								$(top.document).find('.card_list .card_list_page').html(cardReload);
								$(top.document).find('.card_list .card_list_page input[name="card_method"][value=kakao]').click();
								$(top.document).find('.btn_pay_wrap #paymentBtn').click();
								$(top.document).find('.order_pay_iframe').remove();

								//var cardReload = $(data).find('.card_list .card_list_page').html();
								//opener.$('.card_list .card_list_page').html(cardReload);
								
								////가장 처음 카드 클릭후 결제 
								//opener.$('.card_list .card_list_page input[name="card_method"][value=kakao]').click();
								//opener.$('.btn_pay_wrap #paymentBtn').click();

								window.close();
							},
							error: function(){			
								alert('카드등록이 실패했습니다. 다시 시도해주세요.');
								window.close();				
							}
						 });
					}
					//opener.location.reload();//document.oFrom.submit();
				</script>
			<?php }

		}else {
			if(!empty($cartRow['sno'])){

				$this->db->query("delete from wm_subscription_tmp where memNo='$memNo'");
			?>
				<form name="oFrom" method="post" action="../subscription/order.php">
					<input type="hidden" name="cartSno[]" value="<?=$cartRow['sno']?>"/>
				</form>
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
				<script>
					if(opener.$('.mypage_menu.card_list_page').length > 0) {
						window.close();	
						opener.location.reload();
					}else {
						$.ajax({
							method: "POST",
							cache: false,
							url: "../../subscription/order.php",
							data: "",
							success: function (data) {
								var cardReload = $(data).find('.card_list .card_list_page').html();
								opener.$('.payment_progress .card_list .card_list_page').html(cardReload);
								
								//가장 처음 카드 클릭후 결제 
								opener.$('.payment_progress .card_list .card_list_page input[name="card_method"][value=kakao]').click();
								opener.$('.payment_final .btn_order_buy.order-buy').click();

								window.close();
							},
							error: function(){			
								alert('카드등록이 실패했습니다. 다시 시도해주세요.');
								window.close();				
							}
						 });
					}
					
					
					//opener.location.reload();//document.oFrom.submit();
				</script>
					
			<?php 
			}else{?>
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
				<script>
					if(opener.$('.mypage_menu.card_list_page').length > 0) {
						window.close();	
						opener.location.reload();
					}else {
						$.ajax({
							method: "POST",
							cache: false,
							url: "../../subscription/order.php",
							data: "",
							success: function (data) {
								var cardReload = $(data).find('.card_list .card_list_page').html();
								opener.$('.payment_progress .card_list .card_list_page').html(cardReload);
								
								//가장 처음 카드 클릭후 결제 
								opener.$('.payment_progress .card_list .card_list_page  input[name="card_method"][value=kakao]').click();
								opener.$('.payment_final .btn_order_buy.order-buy').click();

								window.close();
							},
							error: function(){			
								alert('카드등록이 실패했습니다. 다시 시도해주세요.');
								window.close();				
							}
						 });
						//opener.location.reload();//document.oFrom.submit();
					}
				</script>
			<?php }

		}
	}
	//카카오페이 결제
	public function kpay($idx,$orderNo)
	{
		
		
		if (!$idx) {
			if ($useException) {
				throw new Exception("스케줄 등록번호가 누락되었습니다.");
			}
			return false;
		}


		$subscription = new Subscription();

		$cfg = $subscription->getCfg();

		$order = new \Component\Order\Order();

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

			$seq = $this->subObj->getSeq($info['idxApply']);
			$gifts = $this->subObj->getGifts($goodsNos, $seq + 1);


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


			
			// 주문서 정보 체크
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

				//if(empty($info['orderEmail'])){
				//	$msql="select email from ".DB_MEMBER." where memNo=?";
				//	$mrow=$this->db->query_fetch($msql,['i',$info['memNo']],false);
				//	$info['orderEmail']=$mrow['email'];
				//}
			$postValue = $order->setOrderDataValidation($info, true);


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
           // $smsAuto = \App::load('Component\\Sms\\SmsAuto');
			$smsAuto = new \Component\Sms\SmsAuto();
            $smsAuto->setUseObserver(true);
           // $mailAuto = \App::load('Component\\Mail\\MailMimeAuto');
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
			$goodsCnt=0;
            foreach ($orderView['goods'] as $values) {
				foreach ($values as $value) {
					foreach ($value as $v) {
						$goodsCnt+=$v['goodsCnt'];
						/*$arrOrderGoodsSno['sno'][] = $v['sno'];

						//부가세가 있는경우는 부가세계산
						if($v['goodsTaxInfo']['0']=='t' && $v['goodsTaxInfo'][1] >0){
							
							$Tax=$settlePrice*$v['goodsTaxInfo'][1]/100;
							$Tax=round($Tax);
							$MerchantReserved1="Tax=".$Tax;
						}*/
					}
				}
			} // endforeach 

		

			$param2 = $bind2 = [];


			$pay_data=[];
			$pay_data['cid']=$this->Cid;
			$pay_data['sid']=$card['payKey'];
			$pay_data['partner_order_id']=$orderNo;
			$pay_data['partner_user_id']=$info['memId'];
			$pay_data['item_name']=$orderView['orderGoodsNm'];
			$pay_data['quantity']=$goodsCnt;

			$pay_data['total_amount']=(int)$settlePrice;
			$pay_data['vat_amount']=(int)$orderView['realTaxVatPrice'];
			$pay_data['tax_free_amount']=(int)$orderView['realTaxFreePrice'];

			$result = $this->Remote($pay_data,$this->kakao_pay_url);
			$result_data = json_decode(stripslashes($result));

            $settlelog  = '';
            $settlelog  .= '===================================================='.PHP_EOL;
            $settlelog  .= 'PG명 : 카카오페이 정기결제'.PHP_EOL;

			if(empty($result_data->code)){//성공
				
				$tid=$result_data->tid;
				$settlelog .= "거래번호 : ". $tid . chr(10);

				 $isSuccess = true;
				 $arrOrderGoodsSno['changeStatus'] = "p1";
                
				//try{
					$order->statusChangeCodeP($orderNo, $arrOrderGoodsSno); // 입금확인으로 변경

					$this->db->query("update ".DB_ORDER." set orderStatus='p1',pgTid='{$tid}' where orderNo='$orderNo'");
					$this->db->query("update ".DB_ORDER_GOODS." set orderStatus='p1' where orderNo='$orderNo'");
				//}catch(Exception $e){
				//	return false;
				//}
				$param = [
					'method = ?',
					'status = ?',
					'orderNo = ?',
				];
				
				$bind = [
					'sssi',
					'kakao',
					'paid', 
					$orderNo,
					$idx,
				];
				$this->db->set_update_db("wm_subSchedules", $param, "idx = ?", $bind);
				
				if($result_data->payment_method_type=="CARD"){

					$this->db->query("update ".DB_ORDER." set pgName='sub',pgResultCode='00',pgAppNo='{$result_data->card_info->approved_id}',pgAppDt=sysdate(),orderPGLog='{$settlelog}',paymentDt=sysdate() where orderNo='$orderNo'");

					$this->db->query("update ".DB_ORDER_GOODS." set paymentDt=sysdate() where orderNo='$orderNo'");


					//2022.05.30민트웹 추가시작
					$memNo = \Session::get("member.memNo");
					$match_data = preg_match('/admin/u',$server['SERVER_NAME'],$match);//2023.05.26웹앤모바일 관리자,사용자 한브라우져에서 동시 로그인 하는듯 하여 추가함

					if(!empty($memNo) && empty($match_data)){
						//첫결제처리부분
						$this->db->query("update ".DB_MEMBER." set groupSno ='{$cfg['memberGroupSno']}' where memNo='$memNo'");

						if (\App::getInstance('request')->getSubdomainDirectory() != 'admin' && \Session::has('member')) {
							\Session::set('member.groupSno', $cfg['memberGroupSno']);
						}
					}else{
						//관리자수동결제 스케줄러결제부분
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

						$sSQL="select idxApply,deliveryStamp from wm_subSchedules where idx=?";
						$sRow=$this->db->query_fetch($sSQL,['i',$idx]);
						
						//$sRow[0]['deliveryStamp']=time();//현재결제일을 넘김
						// 2024-10-29 wg-eric 1일씩 차이나서 수정함
						$sRow[0]['deliveryStamp']= strtotime(date('Y-m-d')) + (60 * 60 * 24 * gd_isset($cfg['payDayBeforeDelivery'], 1));
						
						if(\Request::getRemoteAddress()=="182.216.219.157"){
							//gd_debug($sRow);
							//gd_debug("=========ff===========");
							
						 }	
						
						
						$this->subObj->schedule_set_date($idx,$sRow[0]['idxApply'],$sRow[0]['deliveryStamp'],'scheduler');

					}
					//2022.05.30민트웹 추가종료
				}else{
				
					$this->db->query("update ".DB_ORDER." set pgName='sub',pgResultCode='00',pgAppDt=sysdate(),orderPGLog='{$settlelog}',paymentDt=sysdate() where orderNo='$orderNo'");
					$this->db->query("update ".DB_ORDER_GOODS." set paymentDt=sysdate() where orderNo='$orderNo'");


					//2022.05.30민트웹 추가시작
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

						$sSQL="select idxApply,deliveryStamp from wm_subSchedules where idx=?";
						$sRow=$this->db->query_fetch($sSQL,['i',$idx]);
						
						//$sRow[0]['deliveryStamp']=time();//현재결제일을 넘김
						// 2024-10-29 wg-eric 1일씩 차이나서 수정함
						$sRow[0]['deliveryStamp']= strtotime(date('Y-m-d')) + (60 * 60 * 24 * gd_isset($cfg['payDayBeforeDelivery'], 1));
						
						$this->subObj->schedule_set_date($idx,$sRow[0]['idxApply'],$sRow[0]['deliveryStamp'],'scheduler');
					}
				}


			}else{//실패

				 
			
				$settlelog .= "처리결과 : 카카오페이 결제실패".chr(10);
				$settlelog .= "결과코드 : ". $result_data->code . PHP_EOL;
				$settlelog .= "결과코드 : ". $result_data->extras->method_result_code . PHP_EOL;
				$settlelog .= "결과내용 : " . $result_data->extras->method_result_message . PHP_EOL;

				//실패로그 남김 시작
				if($this->schedule==1 && !empty($cfg['order_fail_retry'])){
    				$table="wm_subscription_fail";
    				$FailSQL="insert into ".$table." set idxApply=?,scheduleIdx=?,fail_regdt=sysdate(),retry_config=?,fail_log='{$settlelog}'";
    				$this->db->bind_query($FailSQL,['iis',$info['idxApply'],$idx,$cfg['order_fail_retry']]);
				}
				//실패로그 남김 종료

				 $param = [
					'orderStatus = ?',
				 ];
				 
				 $bind = [
					'ss',
					'f3',
					$orderNo
				 ];
				 
				 $this->db->set_update_db(DB_ORDER, $param, "orderNo = ?", $bind);
				 $this->db->set_update_db(DB_ORDER_GOODS, $param, "orderNo = ?", $bind);

				 $this->db->query("update ".DB_ORDER." set orderPGLog='{$settlelog}' where orderNo='$orderNo'");

			}
			PHP_EOL;

			

			 /*$param2[] = 'orderPGLog = ?';
			 $this->db->bind_param_push($bind2, "s", $settlelog);
			 $this->db->bind_param_push($bind2, "s", $orderNo);

			 $this->db->set_update_db(DB_ORDER, $param2, "orderNo = ?", $bind2);
			 */

		} // endif 
		
		return $isSuccess;
		/* 결제 처리 E */

	}

	public function kakao_cancel($orderNo, $isApplyRefund = true, $msg = null, $useException = false)
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

		 $orderReorderCalculation = new \Component\Order\ReOrderCalculation();

		$order = new \Component\Order\OrderAdmin();
		$orderView = $order->getOrderView($orderNo);

		 $row = $this->db->query_fetch("SELECT pgTid FROM " . DB_ORDER . " WHERE orderNo = ? LIMIT 0, 1", ["s", $orderNo], false);
		 if (!$row) {
			if ($useException) {
				 throw new Exception("주문정보가 존재하지 않습니다.");
			 }
			 return false;
		 }
		
		//gd_debug($orderView);
		$pay_data=[];
		$pay_data['cid']=$this->Cid;
		$pay_data['tid']=$orderView['pgTid'];
		$pay_data['cancel_amount']=(int)$orderView['settlePrice'];
		$pay_data['cancel_vat_amount']=(int)$orderView['realTaxVatPrice'];
		if(empty($pay_data['cancel_vat_amount']) && !empty($orderView['taxVatPrice']))
			$pay_data['cancel_vat_amount']=(int)$orderView['taxVatPrice'];

		$pay_data['cancel_tax_free_amount']=(int)$orderView['realTaxFreePrice'];
		$pay_data['cancel_available_amount']=(int)$orderView['settlePrice'];



		$result = $this->Remote($pay_data,$this->kakao_cancel_url);
		$result_data = json_decode(stripslashes($result));

		//gd_debug($result_data);

        $settlelog = "";
        $settlelog .= '====================================================' . PHP_EOL;
        $settlelog .= 'PG명 : 카카오페이 정기결제 취소' . PHP_EOL;

		
		if($result_data->status=="CANCEL_PAYMENT"){
		
			$settlelog .= "결과코드 : " . $result_data->status . PHP_EOL;
			$settlelog .= "취소날짜 : " . $result_data->approved_at . PHP_EOL;

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
			}
		}else{
			$settlelog .= "결과코드 : " . $result_data->code . PHP_EOL;
		
		}
	

		$settlelog	.= '===================================================='.PHP_EOL;
		$settlelog = $this->db->escape($settlelog);
		$orderNo = $this->db->escape($orderNo);
		$sql = "UPDATE " . DB_ORDER . " SET orderPGLog=concat(ifnull(orderPGLog,''),'".$settlelog."') WHERE orderNo='{$orderNo}'";
		$this->db->query($sql);

		$this->db->query("update ".DB_ORDER_GOODS." where cancelDt=sysdate() where orderNo='$orderNo'");
		
		return true;
	}
	public function Remote($data,$url)
	{


		$app_admin_key=$this->app_admin_key;


		$data=http_build_query($data);


		$ch = curl_init();
		$header = array("Authorization:KakaoAK {$app_admin_key}");
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, false);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt ($ch, CURLOPT_HEADER, false); // 헤더 출력 여부
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		
		curl_close($ch);

		return $response;

	}


}