<?php

namespace Controller\Admin\Order;

use App;
use Exception;
use Request;
use Framework\Debug\Exception\LayerNotReloadException;
use Framework\Debug\Exception\LayerException;
 
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
							
							 $notifly = \App::load('Component\\Notifly\\Notifly');
							 $notifly->setUserOrderFl($post['orderNo']);

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
				
				$notifly = \App::load('Component\\Notifly\\Notifly');
				$notifly->setUserOrderFl($post['orderNo']);
			}

        /* 튜닝 END */
	}

	public function index() {
		// --- 모듈 호출
        $orderReorderCalculation = App::load(\Component\Order\ReOrderCalculation::class);
        $dbUrl = \App::load('\\Component\\Marketing\\DBUrl');
        $paycoConfig = $dbUrl->getConfig('payco', 'config');

        $postValue = Request::post()->toArray();
        switch ($postValue['mode']) {
			case "refund_complete" : // 환불 완료
				gd_debug($postValue);
				exit;
                try {
                    if (Request::get()->get('channel') == 'naverpay') {
                        $order = App::load(\Component\Order\OrderAdmin::class);
                        $orderGoodsData = $order->getOrderGoods(null,Request::get()->get('sno'),null,null,null)[0];
                        $checkoutData = $orderGoodsData['checkoutData'];
                        $naverPayApi = new NaverPayAPI();
                        $data = $naverPayApi->changeStatus($orderGoodsData['orderNo'],Request::get()->get('sno'),'r3');
                        if($data['result'] == false) {
                            throw new LayerNotReloadException($data['error']);
                        }
                        else {
							$notifly = \App::load('Component\\Notifly\\Notifly');
							$notifly->setUserOrderFl(Request::post()->get('orderNo'));
                            throw new LayerException(__('환불처리가 완료되었습니다.\n 자세한 환불내역은 네이버페이 센터에서 확인하시기 바랍니다.'),null,null,null,10000);
                        }
                    } else {
                        $smsAuto = \App::load('Component\\Sms\\SmsAuto');
                        $smsAuto->setUseObserver(true);
                        \DB::transaction(
                            function () use ($orderReorderCalculation, $paycoConfig) {
                                $orderReorderCalculation->setRefundCompleteOrderGoods(Request::post()->toArray());

                                if ($paycoConfig['paycoFl'] == 'y') {
                                    // 페이코쇼핑 결제데이터 전달
                                    $payco = \App::load('\\Component\\Payment\\Payco\\Payco');
                                    $payco->paycoShoppingRequest(Request::post()->get('orderNo'));
                                }
                            }
                        );
                        $smsAuto->notify();
                    }
					
					$notifly = \App::load('Component\\Notifly\\Notifly');
					$notifly->setUserOrderFl(Request::post()->get('orderNo'));

                    throw new LayerException(__('환불 완료 일괄 처리가 완료 되었습니다.'), null, null, 'parent.close();parent.opener.location.reload()', 2000);
                } catch (LayerException $e) {
                    throw $e;
                } catch (Exception $e) {
                    if (Request::isAjax()) {
                        $this->json([
                            'code' => 0,
                            'message' => $e->getMessage(),
                        ]);
                    } else {
                        throw new LayerNotReloadException($e->getMessage());
                    }
                }
                break;
				case "refund_complete_new" : // 환불 완료
					try {
						if (Request::get()->get('channel') == 'naverpay') {
							$order = App::load(\Component\Order\OrderAdmin::class);
							$orderGoodsData = $order->getOrderGoods(null,Request::get()->get('sno'),null,null,null)[0];
							$checkoutData = $orderGoodsData['checkoutData'];
							$naverPayApi = new NaverPayAPI();
							$data = $naverPayApi->changeStatus($orderGoodsData['orderNo'],Request::get()->get('sno'),'r3');
							if($data['result'] == false) {
								throw new LayerNotReloadException($data['error'], null, null, "parent.btnDisabledAction('F');");
							}
							else {
								$notifly = \App::load('Component\\Notifly\\Notifly');
								$notifly->setUserOrderFl(Request::post()->get('orderNo'));
								throw new LayerException(__('환불처리가 완료되었습니다.\n 자세한 환불내역은 네이버페이 센터에서 확인하시기 바랍니다.'),null,null,null,10000);
							}
						} else {
							$smsAuto = \App::load('Component\\Sms\\SmsAuto');
							$smsAuto->setUseObserver(true);
							\DB::transaction(
								function () use ($orderReorderCalculation, $paycoConfig) {
									$orderReorderCalculation->setRefundCompleteOrderGoodsNew(Request::post()->toArray());
	
									if ($paycoConfig['paycoFl'] == 'y') {
										// 페이코쇼핑 결제데이터 전달
										$payco = \App::load('\\Component\\Payment\\Payco\\Payco');
										$payco->paycoShoppingRequest(Request::post()->get('orderNo'));
									}
								}
							);
							$smsAuto->notify();
						}
						
						$notifly = \App::load('Component\\Notifly\\Notifly');
						$notifly->setUserOrderFl(Request::post()->get('orderNo'));
	
						throw new LayerException(__('환불 완료 일괄 처리가 완료 되었습니다.'), null, null, 'parent.close();parent.opener.location.reload()', 2000);
					} catch (LayerException $e) {
						throw $e;
					} catch (Exception $e) {
						if (Request::isAjax()) {
							$this->json([
								'code' => 0,
								'message' => $e->getMessage(),
							]);
						} else {
							throw new LayerNotReloadException($e->getMessage(), null, null, "parent.btnDisabledAction('F');");
						}
					}
					break;
			default:
				parent::index();
				break;
		}
		exit;
	}
}