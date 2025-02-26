<?php
namespace Controller\Front\Subscription\Inicis;

use App;
use Request;
use Component\Database\DBTableField; 

class OrderPsController extends \Controller\Front\Controller
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

        $bool = true;
        //--- 로그 생성
        $settlelog	= '===================================================='.PHP_EOL;
        $settlelog	.= 'PG명 : 이니시스 정기결제'.PHP_EOL;


        try {

            //#############################
            // 인증결과 파라미터 일괄 수신
            //#############################

            //#####################
            // 인증이 성공일 경우만
            //#####################
            if (strcmp("0000", $in["resultCode"]) == 0) {

               //############################################
               // 1.전문 필드 값 설정(***가맹점 개발수정***)
               //############################################

               $mid 				= $subCfg["mid"];
               $signKey = $subCfg['signKey'];

               $timestamp 			= $util->getTimestamp();   						// util에 의해서 자동생성

               $charset 			= "UTF-8";        								// 리턴형식[UTF-8,EUC-KR](가맹점 수정후 고정)

               $format 			= "JSON";        								// 리턴형식[XML,JSON,NVP](가맹점 수정후 고정)

               $authToken 			= $in["authToken"];   					// 취소 요청 tid에 따라서 유동적(가맹점 수정후 고정)

               $authUrl 			= $in["authUrl"];    						// 승인요청 API url(수신 받은 값으로 설정, 임의 세팅 금지)

               $netCancel 			= $in["netCancelUrl"];   					// 망취소 API url(수신 받은f값으로 설정, 임의 세팅 금지)

               $mKey 				= $subCfg['mKey'];					// 가맹점 확인을 위한 signKey를 해시값으로 변경 (SHA-256방식 사용)

               
				$merchantData = explode("||",$in['merchantData']);
				
				$password=$merchantData[0];     
				
				$memNo=$merchantData[1];               
				if(empty($memNo))
					$memNo=\Session::get("member.memNo");

				$orderNo=$merchantData[2];    
				$scheduleIdx=$merchantData[3];     

               $returnUrl = $in['returnUrl'];

               //#####################
               // 2.signature 생성
               //#####################
               $signParam["authToken"] = $authToken;  		// 필수
               $signParam["timestamp"] = $timestamp;  		// 필수

               // signature 데이터 생성 (모듈에서 자동으로 signParam을 알파벳 순으로 정렬후 NVP 방식으로 나열해 hash)
               $signature = $util->makeSignature($signParam);

               //#####################
               // 3.API 요청 전문 생성
               //#####################
               $authMap["mid"] 		= $mid;   			// 필수
               $authMap["authToken"] 	= $authToken; 		// 필수
               $authMap["signature"] 	= $signature; 		// 필수
               $authMap["timestamp"] 	= $timestamp; 		// 필수
               $authMap["charset"] 	= $charset;  		// default=UTF-8
               $authMap["format"] 		= $format;  		// default=XML

               $billking_key = "";
               try {

                   //#####################
                   // 4.API 통신 시작
                   //#####################
                   $resultMap = [];
                   $authResultString = "";
                   if ($httpUtil->processHTTP($authUrl, $authMap)) {
                       $authResultString = $httpUtil->body;
                       if ($tmp = json_decode($authResultString, true))
                           $resultMap = $tmp;
                   } else {
                       echo "Http Connect Error\n";
                       echo $httpUtil->errormsg;

                       throw new Exception("Http Connect Error");
                   }

                   $ordno = $resultMap['MOID'];
                   $tid = $resultMap['tid'];
                   $billing_key = $resultMap['CARD_BillKey']; // 빌링키

                  //############################################################
                   //5.API 통신결과 처리(***가맹점 개발수정***)
                   //############################################################
                   $settlelog	.= '주문번호 : '.$ordno.PHP_EOL;
                   $settlelog	.= '거래번호 : '.$tid.PHP_EOL;
                   $settlelog	.= '결과코드 : '.$resultMap['resultCode'].PHP_EOL;
                   $settlelog	.= '결과내용 : '. strip_tags($resultMap['ResultMsg']).PHP_EOL;
                   $settlelog	.= '지불방법 : 정기결제 빌링키 발급신청'.PHP_EOL;

                  //$settlelog	.= '신용카드번호 : '.$inipay->GetResult('CARD_Num').PHP_EOL;
                  $settlelog	.= '카드할부여부 : '.$resultMap['CARD_Interest'].' (1이면 무이자할부)'.PHP_EOL;
                  $settlelog	.= '카드할부기간 : '.$resultMap['CARD_Quota'].PHP_EOL;
                  $settlelog	.= '카드사 코드 : '.$resultMap['CARD_Code'].' - '.$pgCards[$resultMap['CARD_Code']].PHP_EOL;
                  $settlelog	.= '카드 발급사 : '.$resultMap['CARD_BankCode'].' - '.$pgBanks[$resultMap['CARD_BankCode']].PHP_EOL;
                  $settlelog .= '카드 이벤트 : '.$resultMap['EventCode'].PHP_EOL;

                  $skipFields = array("MOID", "tid", "resultCode", "ResultMsg", 'CARD_Num', 'CARD_Interest', 'CARD_Quota', 'CARD_Code', 'CARD_BankCode', 'EventCode');
                   foreach ($resultMap as $k => $v) {
                       if (in_array($k, $skipFields))
                           continue;


                       $settlelog .= "{$k} : {$v}".PHP_EOL;
                   }


                   /*************************  결제보안 추가 2016-05-18 START ****************************/
                   $secureMap["mid"]		= $mid;							//mid
                   $secureMap["tstamp"]	= $timestamp;					//timestemp
                   $secureMap["MOID"]		= $resultMap["MOID"];			//MOID
                   $secureMap["TotPrice"]	= $resultMap["TotPrice"];		//TotPrice

                   // signature 데이터 생성
                   $secureSignature = $util->makeSignatureAuth($secureMap);
                   /*************************  결제보안 추가 2016-05-18 END ****************************/

					if ((strcmp("0000", $resultMap["resultCode"]) == 0) && (strcmp($secureSignature, $resultMap["authSignature"]) == 0) ){	//결제보안 추가 2016-05-18
						/*****************************************************************************
						  * 여기에 가맹점 내부 DB에 결제 결과를 반영하는 관련 프로그램 코드를 구현한다.

						[중요!] 승인내용에 이상이 없음을 확인한 뒤 가맹점 DB에 해당건이 정상처리 되었음을 반영함
							 처리중 에러 발생시 망취소를 한다.
						  ******************************************************************************/
                           $settlelog .= "거래상태 : 거래성공".PHP_EOL;
				   } else {
                       $bool = false;
                       $settlelog .= "거래상태 : 거래실패(" .$resultMap["resultCode"].")".PHP_EOL;
                       $settlelog .= "결과코드 : " . $resultMap["resultCode"] . PHP_EOL;


						//결제보안키가 다른 경우.
					   if (strcmp($secureSignature, $resultMap["authSignature"]) != 0) {
							$settlelog .= "위변조 체크 : 데이터 위변조 체크 실패".PHP_EOL;
							//망취소
							if(strcmp("0000", $resultMap["resultCode"]) == 0) {
							  throw new Exception("데이터 위변조 체크 실패");
							}
					   }
				   }
		
				echo "
				   <form name='frm' method='post'>
					 <input type='hidden' name='tid' value='" . @(in_array($resultMap["tid"] , $resultMap) ? $resultMap["tid"] : "null" ) . "'/>
				   </form>";

			} catch (Exception $e) {
					$bool = false;
                   //    $s = $e->getMessage() . ' (오류코드:' . $e->getCode() . ')';
                   //####################################
                   // 실패시 처리(***가맹점 개발수정***)
                   //####################################
                   //---- db 저장 실패시 등 예외처리----//
                   $s = $e->getMessage() . ' (오류코드:' . $e->getCode() . ')';
                   $settlelog .= "오류메세지 : {$s}".PHP_EOL;

                   //#####################
                   // 망취소 API
                   //#####################

                   $netcancelResultString = ""; // 망취소 요청 API url(고정, 임의 세팅 금지)
                   if ($httpUtil->processHTTP($netCancel, $authMap)) {
                       $netcancelResultString = $httpUtil->body;
                   } else {
                       $settlelog .= "망취소 실패 : " . $httpUtil->errormsg . PHP_EOL;

                       throw new Exception("Http Connect Error");
                   }

                   /*##XML output##*/
                   //$netcancelResultString = str_replace("<", "&lt;", $$netcancelResultString);
                   //$netcancelResultString = str_replace(">", "&gt;", $$netcancelResultString);

                   // 취소 결과 확인
                   $settlelog .= "망취소 : " . $netcancelResultString .PHP_EOL;
               }
           } else {
              $bool = false;
              $settlelog .= "인증실패".PHP_EOL;
           }
        } catch (Exception $e) {
           $bool = false;
           $s = $e->getMessage() . ' (오류코드:' . $e->getCode() . ')';
           $settlelog .= "오류코드 : {$s}" . PHP_EOL;
        }
        

       if ($bool  && $billing_key) {

		    $cardDEL="delete from wm_subCards where memNo='$memNo'";
			$db->query($cardDEL);

            $bankNm = $pgBanks[$resultMap['CARD_BankCode']] ?? $pgCards[$resultMap['CARD_Code']];
			
			$setData = [
				'memNo' => $memNo,
				'cardNm' => $bankNm,
				'payKey' => $subscription->encrypt($billing_key),
				'settleLog' => $settlelog,
				'password' => $subscription->getPasswordHash($password),
			];
			
            $arrBind = $db->get_binding(DBTableField::tableWmSubscriptionCards(), $setData, "insert", array_keys($setData));
                                
			$db->set_insert_db("wm_subCards", $arrBind['param'], $arrBind['bind'], "y");
			$affectedRows = $db->affected_rows();


			$applySQL="select idxApply from wm_subSchedules where idx=? group by idxApply";
			$applyRow = $db->query_fetch($applySQL,['i',$scheduleIdx],false);

			$cardSQL="select idx from wm_subCards where memNo=? order by idx DESC limit 0,1";
			$cardRow = $db->query_fetch($cardSQL,['i',$memNo],false);

			$db->query("update wm_subApplyInfo set idxCard='{$cardRow['idx']}' where idx='{$applyRow['idxApply']}'");

            if ($affectedRows > 0) {
					
					try{
						$subscription->pay($scheduleIdx,$orderNo,true);
					}catch(Exception $e){
						
					}
               
          }
       }else{
	   
		
	   }
       $this->js("document.location.href='../../order/order_end.php?orderNo=".$orderNo."'");
		
      // $this->js("alert('결제 카드 등록에 실패하였습니다.');document.location.href='../../subscription/card_list.php';");
        exit;
    }

}