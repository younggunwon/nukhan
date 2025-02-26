<?php

namespace Controller\Front\Order;

use Session;
use Request;
use Component\Cart\CartAdmin;

class LayerCouponApplyOrderController extends \Bundle\Controller\Front\Order\LayerCouponApplyOrderController
{
	public function post()
	{
		 if(Session::has('member')) {
            $post = Request::post()->toArray();
			$cart = new CartAdmin(Session::get('member.memNo'), true);
			// 장바구니(주문)의 상품 데이터
            $cartInfo = $cart->getCartGoodsData($post['cartSno']);
			

			/* 웹앤모바일 튜닝 - 2020-08-07, 배송비 없는 경우 배송비 쿠폰 미노출 */
			if (!$cart->totalDeliveryCharge) {
				$this->setData("deliveryCouponHide", 1);
			}
		 } // endif 
	}
}