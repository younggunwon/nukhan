<?php

namespace Component\Subscription;

use App;
use Component\Sms\Code;

class OrderSms
{
	/**
	* 초기 주문 SMS 전송 
	*
	*/ 
	public function sendSmsAll($orderNo="")
	{

		$db = App::load(\DB::class);
		if(empty($orderNo))
			return false;
		
		//$sql = "SELECT oi.orderNo FROM " . DB_ORDER_INFO . " AS oi INNER JOIN " . DB_ORDER . " AS o ON oi.orderNo = o.orderNo WHERE oi.isSubscription = 1 AND oi.isSmsSent = 0 AND oi.regDt > '2019-12-30 17:00:00' ORDER BY oi.regDt";
		$sql="select * from wm_subSchedules where orderNo='$orderNo'";
		$list = $db->query_fetch($sql);
		if ($list) {
			$order = App::load(\Component\Order\Order::class);
			foreach ($list as $li) {
				$sendMailSmsFl = "<root><mail_ORDER>n</mail_ORDER><mail_INCASH>n</mail_INCASH><mail_DELIVERY>n</mail_DELIVERY><sms_ORDER>n</sms_ORDER><sms_INCASH>n</sms_INCASH><sms_ACCOUNT>n</sms_ACCOUNT><sms_DELIVERY>n</sms_DELIVERY><sms_INVOICE_CODE>n</sms_INVOICE_CODE><sms_DELIVERY_COMPLETED>n</sms_DELIVERY_COMPLETED><sms_CANCEL>n</sms_CANCEL><sms_REPAY>n</sms_REPAY><sms_REPAYPART>n</sms_REPAYPART><sms_SOLD_OUT>n</sms_SOLD_OUT></root>";
					
				 $param = [
					'sendMailSmsFl = ?',
				 ];
					
				 $bind = [
					'ss', 
					$sendMailSmsFl,
					$li['orderNo'],
				 ];
		
				$db->set_update_db(DB_ORDER, $param, "orderNo = ?", $bind);
         
				$order->sendOrderInfo(Code::ORDER, 'all', $li['orderNo']);
				$order->sendOrderInfo(Code::INCASH, 'sms', $li['orderNo']);
				
				/*$param = [
					'isSmsSent = ?',
				];
				
				$bind = [
					'is',
					1,
					$li['orderNo'],
				];
				$db->set_update_db(DB_ORDER_INFO, $param, "orderNo = ?", $bind);
				*/
			}
		}

	}
}