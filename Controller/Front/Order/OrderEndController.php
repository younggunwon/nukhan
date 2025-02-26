<?php

namespace Controller\Front\Order;

use App;
use Request;
use Cookie;

class OrderEndController extends \Bundle\Controller\Front\Order\OrderEndController
{
	public function post()
	{
		$orderNo = Request::get()->get("orderNo");
		if ($orderNo) {
			$order = App::load(\Component\Order\Order::class);
			$orderItems = $order->getOrderGoods($orderNo);
			$this->setData("orderItems", $orderItems);

			$orderSms = App::load(\Component\Subscription\OrderSms::class);
			$orderSms->sendSmsAll($orderNo);
		}

		#  wg-john 추가 - 결제 카드정보 가져오기
		$this->db = \App::load('DB');
		$sql = "
			SELECT C.* 
			FROM wm_subSchedules A
			LEFT JOIN wm_subApplyInfo B
			ON A.idxApply = B.idx
			LEFT JOIN wm_subCards C
			ON B.idxCard = C.idx
			WHERE A.orderNo = '{$orderNo}'
		";
		$cardInfo = $this->db->query_fetch($sql)[0];
		if($cardInfo['cardNo']) {
			$cardInfo['cardNo'] = substr($cardInfo['cardNo'], 0, 4) . ' - ' . substr($cardInfo['cardNo'], 4, 4) . ' - ' . substr($cardInfo['cardNo'], 8, 4) . ' - ' . substr($cardInfo['cardNo'], 12, 4);
		}

		$this->setData('cardInfo', $cardInfo);

	}
	
	public function index()
    {
		parent::index();

		//wg-brown 바로구매로 인해 주문완료페이지에 왔을때 바로구매 쿠키에서 삭제
		$orderInfo = $this->getData('orderInfo');
		if($orderInfo['orderStatus'] != 'f') {
			if(Cookie::has('isDirectCart')){
				Cookie::del('isDirectCart');
			}
		}
		//wg-brown 바로구매로 인해 주문완료페이지에 왔을때 바로구매 쿠키에서 삭제
	}
	
}