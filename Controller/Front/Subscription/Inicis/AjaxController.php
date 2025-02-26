<?php

namespace Controller\Front\Subscription\Inicis;

use App;
use Request;
use Exception;

/**
* 결제 카드 등록관련 Ajax 처리 
*
* @author webnmobile
*/
class AjaxController extends \Controller\Front\Controller
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			
			$db = \App::load(\DB::class);
			$memNo = \Session::get("member.memNo");
			$merchantData = $in['merchantData'];
			
			//if(\Request::getRemoteAddress()=="182.216.219.157"){
				
				if($in['mode']=="getPGParams"){
					$tmp = explode("||",$merchantData);
				
					if($tmp[1] != $memNo){
					
						$this->json([
							'error' => 1,
							'message' => "로그인 정보 오류입니다",
						]);	
						exit;
					}
					
					
				}
			//}
			
			# 2024-09-23 wg-john 추가 - 카드등록 후 바로 결제를 위해 주문정보 저장		
			if($in['jsonOrderData']) {
				\Session::set('jsonOrderData', $in['jsonOrderData']);
			}
			
			//wg-brown 마이페이지에서 왔을 경우 
			if($in['mypageFl'] == 'y') {
				\Session::del('jsonOrderData');
			}
			
			$subscription = App::load(\Component\Subscription\Subscription::class);
			$conf = $subscription->getCfg();
			switch ($in['mode']) {
				/* PG Sign키 생성 */
				case "getPGParams"; 
					$sign = $subscription->getPgSign($in['uid'], $in['price'], $conf['timestamp'], $in['isMobile']);
					
					$data = [
						'uid' => $in['uid'],
						'timestamp' => $conf['timestamp'],
						'sign' => $sign,
						'testData' => $in['jsonOrderData'],
					];
					
					$this->json([
						'error' => 0,
						'data' => $data,
					]);
					break;
			}
		} catch (Exception $e) {
			$this->json([
				'error' => 1,
				'message' => $e->getMessage(),
			]);
		}
		exit;
	}
}