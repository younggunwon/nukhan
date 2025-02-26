<?php

namespace Controller\Mobile\Subscription;

use App;
use Session;
use Component\Subscription\CartSub;
use Component\Cart\Cart;
use Component\Order\Order;

/**
* 카드 등록관리 
* 
* @author webnmobile
*/
class CardListController extends \Controller\Mobile\Controller
{
	public function index()
	{
		$this->addCss(["mypage/mypage.css"]);
		$this->addScript(["subscription/card.js", "slider/slick/slick.min.js"]);
		
		if (!gd_is_login())
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		
		//if(\Request::getRemoteAddress()=="182.216.219.157" || \Request::getRemoteAddress()=="124.111.182.132"){
		    
		    $this->getView()->setDefine("pg_gate", "subscription/_api_pg_gate.html");
		//}else{
		//    $this->getView()->setDefine("pg_gate", "subscription/_pg_gate.html");
		//}
			
		
		
		/* 회원 정보 */
		$member = Session::get("member");
        $this->setData("member", $member);
		
		/* 정기결제 기본 설정 추출 */
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$conf = $subscription->getCfg();
		$this->setData("conf", $conf);
		
		/* 등록 카드 목록 */
		$list = $subscription->getCards();
		$this->setData("list", $list);

		//2024-09-23 wg-brown 주문서에서 카드등록페이지로 왔을때 장바구니 삭제x
		//wm_subCart 에 내용삭제
		$this->setData('myPageFl', 'y');
		$getValue = \Request::get()->all(); 
		$this->setData('orderFl', $getValue['orderFl']);
		if($getValue['orderFl'] != 'y') {
			// 2024-12-11 wg-eric 사용중인 쿠폰 취소 - start
			// 모듈 설정
			$cart = new Cart();
			$cartSub = new CartSub();
			$memNo = \Session::get('member.memNo');

			if($memNo) {
				// 정기결제 장바구니
				$cartIdx = $cartSub->setCartGoodsCnt($memNo, $cartIdx);
				$cartInfo = $cartSub->getCartGoodsData($cartIdx, null, null, false, true);
				$cartSno = $cartSub->cartSno;

				if($cartSno) {
					foreach($cartSno as $key => $val) {
						// 장바구니 쿠폰 초기화
						$cartSub->setMemberCouponDelete($val);
					}
				}
			}
			// 2024-12-11 wg-eric 사용중인 쿠폰 취소 - end

			$db = \App::load(\DB::class);
			$memNo = \Session::get("member.memNo");
			$sql="delete from wm_subCart where memNo=?";
			$db->bind_query($sql,['i',$memNo]);
		}
	}
}