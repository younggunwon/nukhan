<?php

namespace Controller\Mobile\Subscription\Inicis;

use App;
use Request;
use Component\Database\DBTableField; 

/**
* 카드 등록  Callback 
* 
* @author webnmobile
*/
class CardRegisterController extends \Controller\Mobile\Controller
{
	public function index()
	{
		header('Set-Cookie: same-site-cookie=foo; SameSite=Lax');
		header('Set-Cookie: cross-site-cookie=bar; SameSite=None; Secure');
		setcookie('samesite-test', '1', 0, '/; samesite=strict');

		$subscription = App::load(\Component\Subscription\Subscription::class);
		
        $httpUtil = App::load(\Component\Subscription\Inicis\HttpClient::class);
        $util = App::load(\Component\Subscription\Inicis\INIStdPayUtil::class);
	
        $pgCards = $subscription->getPgCards(); // 카드사 코드
        $pgBanks = $subscription->getPgBanks(); // 은행 코드
        $subCfg = $subscription->getCfg();
        $db = App::load(\DB::class);
		
        $in = Request::request()->all();
		$ordno = $in['orderid'];
        //$password = $in['merchantreserved'];

		$merchantData = explode("||",$in['merchantreserved']);

		//wg-brown $password삭제
		//$password=$merchantData[0];
		$memNo=$merchantData[1];    

		if(empty($memNo))
			$memNo=\Session::get("member.memNo");
		
        $billing_key = "";
		
		 $bool = true;
        //--- 로그 생성
        $settlelog	= '';
        $settlelog	.= '===================================================='.PHP_EOL;
        $settlelog	.= 'PG명 : 이니시스 정기결제(모바일)'. PHP_EOL;
         $settlelog	.= '주문번호 : '.$ordno.PHP_EOL;
        $settlelog	.= '거래번호 : '.$in['tid'].PHP_EOL;
        $settlelog .= "결과코드 : " . $in["resultcode"] . PHP_EOL;
        $settlelog	.= '결과내용 : '. strip_tags($in['resultmsg']).PHP_EOL;
        $settlelog	.= '카드번호 : '. strip_tags($in['cardno']).PHP_EOL;
        $settlelog	.= '카드종류 : '. strip_tags($in['cardkind']).PHP_EOL;
        $settlelog	.= '카드사 코드 : '.$in['cardcd'].' - '.$pgCards[$in['cardcd']].PHP_EOL;
        if ($in['resultcode'] == '00') { //  성공
            $settlelog .= "거래상태 : 거래성공".PHP_EOL;
            $billing_key = $in['billkey'];
			
            $bool= true;
        } else { // 실패
             $settlelog .= "거래상태 : 거래실패".PHP_EOL;
            $bool = false;
        }

        $settlelog	.= '===================================================='.PHP_EOL;
		//$bool=true;
		//$billing_key=11111;
		//$password=1111;

		//wg-brown $password삭제
        if ($bool && $billing_key) { // 빌링키 발급 성공 시
            
			$bankNm = $pgCards[$in['cardcd']];

			$setData = [
			'memNo' => $memNo,
			'cardNm' => $bankNm,
			'cardNo' => strip_tags($in['cardno']),
			'payKey' => $subscription->encrypt($billing_key),
			'settleLog' => $settlelog,
			'password' => '',//wg-brown $password삭제 $subscription->getPasswordHash($password)
			];

			$arrBind = $db->get_binding(DBTableField::tableWmSubscriptionCards(), $setData, "insert", array_keys($setData));
								
			$db->set_insert_db("wm_subCards", $arrBind['param'], $arrBind['bind'],  "y");
			$affectedRows = $db->affected_rows();
			$affectedRows=1;
			
			$insert_id = $db->insert_id();
			
			if ($affectedRows > 0) {
				$sql="select * from wm_subCart where memNo=?";
				$cartRow=$db->query_fetch($sql,['i',$memNo],false);

				$jsonOrderData = \Session::get('jsonOrderData');
				$jsonOrderData = json_decode($jsonOrderData, 1);
				$jsonOrderData = json_encode($jsonOrderData);
				
				if(!empty($cartRow['sno']) && strlen($jsonOrderData) > 2000){
				?>
					<script>
						window.onload = function() {
							var form = document.createElement("form");
							form.id = "frmOrder";
							form.action = "/subscription/order_ps.php";
							form.method = "post";
							
							var jsonOrderData = <?=$jsonOrderData?>;

							for(var i = 0; i < jsonOrderData.length; i++) {
								var orderInput = document.createElement("input");
								orderInput.type = "text";
								orderInput.name = jsonOrderData[i]['name'];
								orderInput.value = jsonOrderData[i]['value'];
								form.appendChild(orderInput);
							}

							var orderInput = document.createElement("input");
							orderInput.type = "text";
							orderInput.name = "idxCard";
							orderInput.value = <?=$insert_id?>;
							form.appendChild(orderInput);
							//wg-brown 숨김처리
							form.style.display = "none";

							document.body.appendChild(form);
							form.submit();
						};
					</script>
						
				<?php 
				}else{
				    
				    //마이페이지 정기결제 카드등록시 기존 정기결제 최신등록카드로 모두변경시작
				   // if(\Request::getRemoteAddress()=="182.216.219.157" || \Request::getRemoteAddress()=="124.111.182.132"){
				    if(!empty($insert_id) && !empty($memNo)){
				        $strSQL="update wm_subApplyInfo set idxCard=? where memNo=?";
				        $db->bind_query($strSQL,['ii',$insert_id,$memNo]);
				    }
				   // }
				    //마이페이지 정기결제 카드등록시 기존 정기결제 최신등록카드로 모두변경종료
				    
					$returnUrl = \Session::get("cardReturnUrl");
					\Session::set("cardReturnUrl", "../../subscription/card_list.php");
					
					//2024-04-19 wg-brwon 마이페이지에서 orderFl가 있을때 url
					if(\Request::get()->all()['orderFl'] == 'y') {
						$returnUrl = "../../subscription/card_list.php?orderFl=y";
						$url = "../../subscription/card_list.php?orderFl=y";
					}else {
						$url = "../../subscription/card_list.php";
					}

					//2024-04-19 wg-brwon 마이페이지에서 orderFl가 있을때 url
					if ($returnUrl)
						$url = $returnUrl;
					 
					 $this->js("window.location.href='{$url}';");
				}
                 exit;

				//$returnUrl = \Session::get("cardReturnUrl");
				//$url = "/subscription/card_list.php";

				//if ($returnUrl)
				//	$url = $returnUrl;
				 
				// $this->js("alert('결제 카드 등록에 성공 하였습니다.');document.location.href='/subscription/card_list.php'");
				// exit;
			}
       }
        
		//exit;
       $this->js("alert('결제 카드 등록에 실패하였습니다.');document.location.href='/subscription/card_list.php';");
        exit;
	}
}