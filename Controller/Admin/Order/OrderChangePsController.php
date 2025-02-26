<?php

namespace Controller\Admin\Order;

 use Request;
 use App;
 
class OrderChangePsController extends \Bundle\Controller\Admin\Order\OrderChangePsController
{
    public function pre()
	{
		/** 웹앤모바일 튜닝 - 2020-01-16, 정기결제 취소 처리 */
		$post = Request::post()->toArray();
        $server = Request::server()->toArray();

		 if ($post['orderNo'] && in_array($post['mode'], ['refund_complete_new']) && $post['info']['refundMethod'] == 'PG환불') {

			$db = App::load('DB');
				$tmp2 = $db->fetch("SELECT COUNT(*) as cnt FROM " . DB_ORDER_GOODS . " WHERE orderNo='{$post['orderNo']}'");
				$cnt2 = $tmp2['cnt'];
				
				$od = $db->fetch("SELECT * FROM " . DB_ORDER . " WHERE orderNo='{$post['orderNo']}'");

				if ($od) {
					if ($od['orderStatus'] == 'r1' && $od['pgName'] == 'sub') {
						
						$subObj = App::load(\Component\Subscription\Subscription::class);
						
						$orderReorderCalculation = App::load(\Component\Subscription\ReOrderCalculation::class);
						
						$cancle_return = $subObj->cancel($post['orderNo'], false);
						

						 if($cancle_return==true){
							 \DB::transaction(
								function () use ($orderReorderCalculation, $paycoConfig) {
								   $orderReorderCalculation->setRefundCompleteOrderGoodsNew(Request::post()->toArray());
								}
							 );

							 echo"<script>alert('정기결제 주문건에 대한 환불처리가 완료되었습니다.');parent.opener.location.reload();parent.close();</script>";
						 }
						 exit();
					}
				}
				
			}

			 if ($post['orderNo'] && in_array($post['mode'], ['cancel', 'refund']) && $post['refund']['refundMethod'] == 'PG환불') {
				
				$cnt = count($post['refund']['statusCheck']);
				$db = App::load('DB');
				$tmp2 = $db->fetch("SELECT COUNT(*) as cnt FROM " . DB_ORDER_GOODS . " WHERE orderNo='{$post['orderNo']}'");
				$cnt2 = $tmp2['cnt'];

				$od = $db->fetch("SELECT * FROM " . DB_ORDER . " WHERE orderNo='{$post['orderNo']}'");
				if ($cnt == $cnt2 && $od) {
					$orderStatus = substr($od['orderStatus'], 0, 1);
					if ($orderStatus != 'r' && $od['pgName'] == 'sub') {
						$subObj = App::load(\Component\Subscription\Subscription::class);
						$subObj->cancel($post['orderNo'], false);
					}
				}
			}

        /* 튜닝 END */
	}
}