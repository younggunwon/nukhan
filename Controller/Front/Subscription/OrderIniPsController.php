<?php

namespace Controller\Front\Subscription;

use App;
use Component\GoodsStatistics\GoodsStatistics;
use Component\Bankda\BankdaOrder;
use Component\SubscriptionNew\CartSub as Cart;
use Component\Member\Member;
use Component\Order\Order;
use Component\Order\OrderMultiShipping;
use Exception;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Debug\Exception\AlertCloseException;
use Globals;
use Request;

/**
* 정기결제 주문 DB 처리 관련 
*
* @author webnmobile
*/
//카드등록 하지 않고 바로결제-이니시스심사용
class OrderIniPsController extends \Controller\Front\Controller
{
	public function pre()
	{
		$postValue = Request::post()->toArray();
		$db = App::load(\DB::class);
	}
	
	/**
     * index
     *
     */
    public function index()
    {
        // POST 데이터 수신
        $postValue = Request::post()->toArray();
        // 모듈 설정
        $cart = App::load(\Component\Subscription\CartSub::class);
        $order = App::load(\Component\Order\Order::class);
        $db = App::load('DB');
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		
        switch ($postValue['mode']) {
            // 지역별 배송비 계산하기
            // TODO 2016-08-30 스킨패치를 하지 않는 고객들의 레거시때문에 남겨 놓음 이후 상황봐서 제거 처리 해야 함
            case 'check_area_delivery':
                try {
                    // 장바구니내 지역별 배송비 처리를 위한 주소 값
                    $address = $postValue['receiverAddress'];

                    // 장바구니 정보 (해당 프로퍼티를 가장 먼저 실행해야 계산된 금액 사용 가능)
                    $cart->getCartGoodsData($postValue['cartSno'], $address);

                    // 주문서 작성시 발생되는 금액을 장바구니 프로퍼티로 설정하고 최종 settlePrice를 산출 (사용 마일리지/예치금/주문쿠폰)
                    $orderPrice = $cart->setOrderSettleCalculation($postValue);

                    $this->json(['areaDelivery' => array_sum($orderPrice['totalGoodsDeliveryAreaCharge'])]);

                } catch (Exception $e) {
                    $this->json([
                        'error' => 1,
                        'message' => $e->getMessage(),
                    ]);
                }

                break;

            // 주문쿠폰 사용시 회원추가/중복 할인 금액 / 마일리지 지급 재조정
            case 'set_recalculation':
                try {
                    $memInfo = $this->getData('gMemberInfo');

                    if (empty($postValue['cartIdx']) === false) {
                        $cartIdx = $postValue['cartIdx'];
                    }
                    $cart->totalCouponOrderDcPrice = $postValue['totalCouponOrderDcPrice'];
                    $cart->totalUseMileage = $postValue['useMileage'];
                    $cart->deliveryFree = $postValue['deliveryFree'];

                    $cart->getCartGoodsData($cartIdx);

                    // 마일리지 정책
                    // '마일리지 정책에 따른 주문시 사용가능한 범위 제한' 에 사용되는 기준금액 셋팅
                    $setMileagePriceArr = [
                        'totalDeliveryCharge' => $postValue['totalDeliveryCharge'] + $postValue['deliveryAreaCharge'],
                        'totalGoodsDeliveryAreaPrice' => $postValue['deliveryAreaCharge'],
                    ];
                    $mileagePrice = $cart->setMileageUseLimitPrice($setMileagePriceArr);
                    // 마일리지 정책에 따른 주문시 사용가능한 범위 제한
                    $mileageUse = $cart->getMileageUseLimit(gd_isset($memInfo['mileage'], 0), $mileagePrice);

                    $setData = [
                        'cartCnt' => $cart->cartCnt,
                        'totalGoodsPrice' => $cart->totalGoodsPrice,
                        'totalGoodsDcPrice' => $cart->totalGoodsDcPrice,
                        'totalGoodsMileage' => $cart->totalGoodsMileage,
                        'totalMemberDcPrice' => $cart->totalMemberDcPrice,
                        'totalMemberOverlapDcPrice' => $cart->totalMemberOverlapDcPrice,
                        'totalMemberMileage' => $cart->totalMemberMileage,
                        'totalCouponGoodsDcPrice' => $cart->totalCouponGoodsDcPrice,
                        'totalMyappDcPrice' => $cart->totalMyappDcPrice,
                        'totalCouponGoodsMileage' => $cart->totalCouponGoodsMileage,
                        'totalDeliveryCharge' => $cart->totalDeliveryCharge,
                        'totalSettlePrice' => $cart->totalSettlePrice,
                        'totalMileage' => $cart->totalMileage,
                        'totalMemberDcPriceAdd' => gd_global_add_money_format($cart->totalGoodsDcPrice + $cart->totalMemberDcPrice + $cart->totalMemberOverlapDcPrice + $cart->totalCouponGoodsDcPrice),
                        'mileageUse' => $mileageUse,
                    ];

                    $this->json($setData);
                    exit;
                } catch (Exception $e) {
                    $this->json([
                        'error' => 1,
                        'message' => $e->getMessage(),
                    ]);
                }

                break;

            // id-상품별 구매 수량 체크
            case 'check_memberOrderGoodsCount':
                try {
                    $aMemberOrderGoodsCountData = $order->getMemberOrderGoodsCountData(\Session::get('member.memNo'), $postValue['goodsNo']);

                    if ($aMemberOrderGoodsCountData) {
                        $this->json([
                            'count' => $aMemberOrderGoodsCountData['orderCount'],
                        ]);
                    } else {
                        $this->json([
                            'count' => 0,
                        ]);
                    }
                } catch (Exception $e) {
                    $this->json([
                        'error' => 1,
                        'message' => $e->getMessage(),
                    ]);
                }

                break;

            // 주문서 저장하기
            default:
                try {
					
                    $orderMultiShipping = new OrderMultiShipping();

                    // 주문서 정보 체크
                    $postValue = $order->setOrderDataValidation($postValue, true);

                    // 결제수단이 없는 경우 PG창이 열리기 때문에 강제로 무통장으로 처리
                    if (empty($postValue['settleKind']) === true) {
                        $postValue['settleKind'] = 'gb';
//                        throw new Exception(__('결제수단을 선택 해주세요.'));
                    }

                    //페이코 관련 데이터 설정
                    if ($postValue['orderChannelFl'] == 'payco') {
                        $acecounterCommonScript = \App::load('\\Component\\Nhn\\AcecounterCommonScript');
                        $acecounterUse = $acecounterCommonScript->getAcecounterUseCheck();

                        if ($postValue['paycoOrderType'] == 'CHECKOUT') {
                            //checkoutData에 상품상세 or 장바구니 여부 필요
                            $paycoData = json_decode(urldecode($postValue['paycoOrderData']));
                            $checkoutData['mode'] = $paycoData->mode;

                            if ($acecounterUse) {
                                if (\Cookie::has('_ACU' . $acecounterCommonScript->config['aceCode'])) {
                                    $checkoutData['aceCid'] = \Cookie::get('_ACU' . $acecounterCommonScript->config['aceCode']);
                                }
                                if ($acecounterCommonScript->hasAceAet()) {
                                    $checkoutData['aceAet'] = $acecounterCommonScript->getAceAet();
                                } else {
                                    $checkoutData['aceAet'] = $acecounterCommonScript->setAceAet();
                                }
                            }
                            unset($paycoData);

                            $postValue['checkoutData'] = json_encode($checkoutData, JSON_UNESCAPED_UNICODE);
                        } else {
                            $fintechData['mode'] = '1';
                            if ($acecounterUse) {
                                if (\Cookie::has('_ACU' . $acecounterCommonScript->config['aceCode'])) {
                                    $fintechData['aceCid'] = \Cookie::get('_ACU' . $acecounterCommonScript->config['aceCode']);
                                }
                                if ($acecounterCommonScript->hasAceAet()) {
                                    $fintechData['aceAet'] = $acecounterCommonScript->getAceAet();
                                } else {
                                    $fintechData['aceAet'] = $acecounterCommonScript->setAceAet();
                                }
                            }
                            $postValue['fintechData'] = json_encode($fintechData, JSON_UNESCAPED_UNICODE);
                        }

                        // settleKind 값이 없거나 페이코 settleKind 값이 아닌경우
                        if (empty($postValue['settleKind']) === true || substr($postValue['settleKind'], 0, 1) !== 'f') {
                            // 결제수단이 없는 경우 페이코 기본 결제 수단으로 처리 - fu 처리
                            $postValue['settleKind'] = Order::SETTLE_KIND_FINTECH_UNKNOWN;
                        }
                    }

                    // 배송비 산출을 위한 주소 및 국가 선택
                    if (Globals::get('gGlobal.isFront')) {
                        // 주문서 작성페이지에서 선택된 국가코드
                        $address = $postValue['receiverCountryCode'];
                    } else {
                        // 장바구니내 해외/지역별 배송비 처리를 위한 주소 값
                        $address = $postValue['receiverAddress'];
                    }

                    $cart->totalCouponOrderDcPrice = $postValue['totalCouponOrderDcPrice'];
                    $cart->totalUseMileage = $postValue['useMileage'];
                    $cart->deliveryFree = $postValue['deliveryFree'];
                    $cart->couponApplyOrderNo = $postValue['couponApplyOrderNo'];
					
                    try {
                        $db->begin_tran();
                        if ($orderMultiShipping->isUseMultiShipping() === true && \Globals::get('gGlobal.isFront') === false && $postValue['multiShippingFl'] == 'y') {
                            $resetCart = $orderMultiShipping->resetCart($postValue);
                            $postValue['cartSno'] = $resetCart['setCartSno'];
                            $postValue['orderInfoCdData'] = $resetCart['orderInfoCd'];
                            $postValue['orderInfoCdBySno'] = $resetCart['orderInfoCdBySno'];
                            $cart->goodsCouponInfo = $resetCart['goodscouponInfo'];
                        }

                        // 장바구니 정보 (해당 프로퍼티를 가장 먼저 실행해야 계산된 금액 사용 가능)
                        $cartInfo = $cart->getCartGoodsData($postValue['cartSno'], $address, null, true, false, $postValue);
                        $postValue['multiShippingOrderInfo'] = $cart->multiShippingOrderInfo;

                        $couponUsableFl = true;
                        $goodsEachSaleCountAbleFl = true;
                        $goodsEachSaleCheckArr = null;
                        if (($postValue['totalCouponGoodsDcPrice'] > 0 || $postValue['totalCouponGoodsMileage'] > 0 || $postValue['couponApplyOrderNo']) || $goodsEachSaleCountAbleFl) {
                            $coupon = \App::load('\\Component\\Coupon\\Coupon');
                            if ($postValue['couponApplyOrderNo']) {
                                $couponUsableFl = $coupon->getCouponMemberSaveFl($postValue['couponApplyOrderNo']);
                            }
                            if ($couponUsableFl || $goodsEachSaleCountAbleFl) {
                                $checkDuplMemberCouponNo = [];
                                foreach ($cartInfo as $sKey => $sVal) {
                                    foreach ($sVal as $dKey => $dVal) {
                                        foreach ($dVal as $gKey => $gVal) {
                                            if ($gVal['memberCouponNo'] && $couponUsableFl) {
                                                $couponUsableFl = $coupon->getCouponMemberSaveFl($gVal['memberCouponNo']);
                                            }
                                            if (empty($gVal['memberCouponNo']) === false){
                                                $tmpApplyMemberCouponList = explode(INT_DIVISION, $gVal['memberCouponNo']);
                                                foreach ($tmpApplyMemberCouponList as $tmpApplyMemberCouponNo) {
                                                    if (array_key_exists($tmpApplyMemberCouponNo, $checkDuplMemberCouponNo) === true) {
                                                        throw new AlertRedirectException(__('이미 사용중인 쿠폰이 적용되어 있습니다.'));
                                                    }
                                                    $checkDuplMemberCouponNo[$tmpApplyMemberCouponNo] = $tmpApplyMemberCouponNo;
                                                }
                                            }
                                            // 상품별 수량체크 한번 더
                                            if ($gVal['minOrderCnt'] > 1 || $gVal['maxOrderCnt'] > '0') {
                                                if ($gVal['fixedOrderCnt'] == 'option' ) {
                                                    if ($gVal['goodsCnt'] < $gVal['minOrderCnt']) {
                                                        $goodsEachSaleCountAbleFl = false;
                                                    }
                                                    if ($gVal['goodsCnt'] > $gVal['maxOrderCnt'] && $gVal['maxOrderCnt'] > 0) {
                                                        $goodsEachSaleCountAbleFl = false;
                                                    }
                                                }

                                                if ($gVal['fixedOrderCnt'] == 'goods' || $gVal['fixedOrderCnt'] == 'id') {
                                                    if ($gVal['fixedOrderCnt'] == 'id' && \Session::get('member.memNo') !== null) {
                                                        $goodsEachSaleCheckArr[$gVal['goodsNo']]['fixedOrderCnt'] = 'id';
                                                    } else {
                                                        $goodsEachSaleCheckArr[$gVal['goodsNo']]['fixedOrderCnt'] = 'goods';
                                                    }
                                                    $goodsEachSaleCheckArr[$gVal['goodsNo']]['count'] += $gVal['goodsCnt'];
                                                    $goodsEachSaleCheckArr[$gVal['goodsNo']]['max'] = $gVal['maxOrderCnt'];
                                                    $goodsEachSaleCheckArr[$gVal['goodsNo']]['min'] = $gVal['minOrderCnt'];
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if (is_array($goodsEachSaleCheckArr)) {
                            foreach ($goodsEachSaleCheckArr as $k => $v) {
                                if ($v['fixedOrderCnt'] == 'id' && \Session::get('member.memNo') !== null) {
                                    $aMemberOrderGoodsCountData = $order->getMemberOrderGoodsCountData(\Session::get('member.memNo'), $k);
                                    $thisGoodsCount = gd_isset($aMemberOrderGoodsCountData['orderCount'], 0) + $v['count'];
                                    if ($thisGoodsCount < $v['min'] || ($thisGoodsCount > $v['max'] && $v['max'] > 0)) {
                                        $goodsEachSaleCountAbleFl = false;
                                    }
                                } else {
                                    if (($v['count'] > $v['max'] && $v['max'] > 0) || $v['count'] < $v['min']) {
                                        $goodsEachSaleCountAbleFl = false;
                                    }
                                }
                            }
                        }

                        if (!$couponUsableFl) {
                            throw new AlertRedirectException(__('사용할 수 없는 쿠폰입니다. 장바구니에서 확인 후 다시 주문해주세요.'), null, null, '../subscription/cart.php', 'top');
                        }

                        if (!$goodsEachSaleCountAbleFl) {
                            throw new AlertRedirectException(__('구매 불가 상품이 포함되어 있으니 장바구니에서 확인 후 다시 주문해주세요.'), null, null, '../subscription/cart.php', 'top');
                        }
                        // 주문불가한 경우 진행 중지
                        if (!$cart->orderPossible) {
                            if(trim($cart->orderPossibleMessage) !== ''){
                                throw new AlertRedirectException(__($cart->orderPossibleMessage), null, null, '../subscription/cart.php', 'top');
                            } else {
                                throw new AlertRedirectException(__('구매 불가 상품이 포함되어 있으니 장바구니에서 확인 후 다시 주문해주세요.'), null, null, '../subscription/cart.php', 'top');
                            }
                        }

                        // EMS 배송불가
                        if (!$cart->emsDeliveryPossible) {
                            throw new AlertRedirectException(__('무게가 %sg 이상의 상품은 구매할 수 없습니다. (배송범위 제한)', '30k'), null, null, '../subscription/cart.php', 'top');
                        }

                        // 개별결제수단이 설정되어 있는데 모두 다른경우 결제 불가
                        if (empty($cart->payLimit) === false && in_array('false', $cart->payLimit)) {
                            throw new AlertRedirectException(__('주문하시는 상품의 결제 수단이 상이 하여 결제가 불가능합니다.'), null, null, '../subscription/cart.php', 'top');
                        }

                        // 설정 변경등으로 쿠폰 할인가등이 변경된경우
                        if (!$cart->changePrice) {
                            throw new AlertRedirectException(__('할인/적립 금액이 변경되었습니다. 상품 결제 금액을 확인해 주세요!'), null, null, '../subscription/cart.php', 'top');
                        }

                        // 주문서 작성시 발생되는 금액을 장바구니 프로퍼티로 설정하고 최종 settlePrice를 산출 (사용 마일리지/예치금/주문쿠폰)
                        $orderPrice = $cart->setOrderSettleCalculation($postValue);

                        // 설정 변경등으로 쿠폰 할인가등이 변경된경우 - 주문쿠폰체크
                        if (!$cart->changePrice) {
                            throw new AlertRedirectException(__('할인/적립 금액이 변경되었습니다. 상품 결제 금액을 확인해 주세요!'), null, null, '../subscription/cart.php', 'top');
                        }

                        // 마일리지/예치금 전용 구매상품인 경우 찾아내기
                        if (empty($cart->payLimit) === false) {
                            $isOnlyMileage = true;
                            foreach ($cart->payLimit as $val) {
                                if (!in_array($val, [Order::SETTLE_KIND_MILEAGE, Order::SETTLE_KIND_DEPOSIT])) {
                                    $isOnlyMileage = false;
                                }
                            }

                            // 마일리지/예치금 결제 전용인 경우
                            if ($isOnlyMileage) {
                                // 예치금/마일리지 복합결제 구매상품인 경우 결제금액이 0원이 아닌 경우
                                if (in_array(Order::SETTLE_KIND_DEPOSIT, $cart->payLimit) && in_array(Order::SETTLE_KIND_MILEAGE, $cart->payLimit) && $orderPrice['settlePrice'] != 0) {
                                    throw new Exception(__('결제금액보다 예치금/마일리지 사용 금액이 부족합니다.'));
                                }

                                // 예치금 전용 구매상품이면서 결제금액이 0원이 아닌 경우
                                if (in_array(Order::SETTLE_KIND_DEPOSIT, $cart->payLimit) && $orderPrice['settlePrice'] != 0) {
                                    throw new Exception(__('결제금액보다 예치금이 부족합니다.'));
                                }

                                // 마일리지 전용 구매상품이면서 결제금액이 0원이 아닌 경우
                                if (in_array(Order::SETTLE_KIND_MILEAGE, $cart->payLimit) && $orderPrice['settlePrice'] != 0) {
                                    throw new Exception(__('결제금액보다 마일리지가 부족합니다.'));
                                }
                            }
                        }
                        $db->commit();

                    } catch (Exception $e) {
                        $db->rollback();
                        throw new Exception($e->getMessage());
                    }

                    // 결제금액이 0원인 경우 전액할인 수단으로 강제 변경 및 주문 채널을 shop 으로 고정
                    if ($orderPrice['settlePrice'] == 0) {
						$postValue['settleKind'] = Order::SETTLE_KIND_ZERO;
                        $postValue['orderChannelFl'] = 'shop';
                    } else {
						$postValue['settleKind'] = "pc";
					}
                    /*
                     * 주문정보 발송 시점을 트랜잭션 종료 후 진행하기 위한 로직 추가
                     */
                    $smsAuto = \App::load('Component\\Sms\\SmsAuto');
                    $smsAuto->setUseObserver(true);
                    $mailAuto = \App::load('Component\\Mail\\MailMimeAuto');
                    $mailAuto->setUseObserver(true);
                    // 주문 저장하기 (트랜젝션)
                    $result = \DB::transaction(function () use ($order, $cartInfo, $postValue, $orderPrice, $cart) {
                        // 장바구니에서 계산된 전체 과세 비율 필요하면 추후 사용 -> $cart->totalVatRate
                        return $order->saveOrderInfo($cartInfo, $postValue, $orderPrice);
                    });

					
                    // 주문 저장 후 처리
                    if ($result) {
                       // PG에서 정확한 장바구니 제거를 위해 주문번호 저장 처리
						if (empty($cart->cartSno) === false) {
							if (is_array($cart->cartSno)) {
								$tmpWhere = [];
								foreach ($cart->cartSno as $sno) {
									$tmpWhere[] = $db->escape($sno);
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
							$db->set_update_db("wm_subCart", 'tmpOrderNo = ?', implode(' AND ', $arrWhere), $arrBind);
						}

                        // 장바구니 통계 구매여부 저장 처리
                        $eventConfig = \App::getConfig('event')->toArray();
                        if ($eventConfig['cartOrderStatistics'] !== 'n') {
                            $cartStatistics = new GoodsStatistics();
                            $cartStatistics->setCartOrderStatistics($cart->cartSno, $order->orderNo);
                        }
               
                        // 무통장 입금 및 결제금액이 0원인 경우 처리
                        if (in_array($postValue['settleKind'], [Order::SETTLE_KIND_ZERO])) {
                            // 주문 모듈
                            $orderData = $order->getOrderData($order->orderNo);
                            $orderGoodsData = $order->getOdrerGoods($order->orderNo);

                            // 주문 데이타가 없는 경우
                            if (empty($orderData) === true) {
                                throw new AlertRedirectException(__('주문중 오류가 발생했습니다. 다시 시도해 주세요.'), null, null, '../subscription/cart.php', 'top');
                            }

                            // 주문 상품 데이터가 없는 경우
                            if (empty($orderGoodsData) === true) {
                                throw new AlertRedirectException(__('주문중 오류가 발생했습니다. 다시 시도해 주세요.'), null, null, '../subscription/cart.php', 'top');
                            }

                            // 장바구니 비우기
                            $cart->setCartRemove($order->orderNo);

                            // sms notify위치변경
                            $smsAuto->notify();
                            $mailAuto->notify();
							
							/* 정기결제 스케줄 생성 S */
							$scheduleList = $subscription->createSchedule($postValue, null, $postValue['deliveryPeriod'], $postValue['deliveryEa'], true);
							

							if (empty($scheduleList)) {
								throw new Exception("정기결제 스케줄 생성에 실패하였습니다.");
							}
							/* 정기결제 스케줄 생성 E */
    
                            // 결제 완료 페이지 이동
                            throw new AlertRedirectException(null, null, null, '../order/order_end.php?orderNo=' . $order->orderNo, 'parent');
                        } else {
                            // sms notify위치변경
                            $smsAuto->notify();
                            $mailAuto->notify();
							
							/* 정기결제 스케줄 생성 S */
							$scheduleList = $subscription->createSchedule($postValue, null, $postValue['deliveryPeriod'], $postValue['deliveryEa'], true);
							
							if (empty($scheduleList)) {
								throw new Exception("정기결제 스케줄 생성에 실패하였습니다.");
							}
							/* 정기결제 스케줄 생성 E */
							
							/* PG 결제 처리 S */
							$idx = $scheduleList[0]['idx'];

							
							//빌링키받고 바로결제 시작
							$schedule = \App::load(\Component\Subscription\Schedule::class);
							$subConf = $schedule->getCfg();
							$this->setData("subConf", $subConf);

							$conf = $subscription->getCfg();
							$this->setData("conf", $conf);
							$member = \Session::get("member");
							$this->setData("member", $member);

							

							
							
							$tmpGoodsNm=[];
							foreach($postValue['priceInfo'] as $tmpKey=>$tmpVal){
								$tmpRow = $db->fetch("select goodsNm from ".DB_GOODS." where goodsNo='{$tmpKey}'");

								$tmpGoodsNm[]=$tmpRow['goodsNm'];
							}

							$goodsNm=implode(",",$tmpGoodsNm);

							$this->setData("goodsNm",$goodsNm);
							$this->setData("orderNo",$order->orderNo);
							$this->setData("settlePrice",$postValue['settlePrice']);

							$merchantData=$member['memNo']."||".$order->orderNo."||".$idx;
							$this->setData("merchantData",$merchantData);

							
							 $this->getView()->setPageName('subscription/inicis/inipay_gate');
							 //빌링키받고 바로결제 종료
							
							/* PG 결제 처리 E */
							
							// 장바구니 비우기
							$cart->setCartRemove($order->orderNo);
							
							// 결제 완료 페이지 이동
							//throw new AlertRedirectException(null, null, null, '../order/order_end.php?orderNo=' . $order->orderNo, 'parent');
							
                        }
                    }
                } catch (Exception $e) {
                    if (get_class($e) == Exception::class) {
                        throw new AlertOnlyException($e->getMessage(), null, null, "window.parent.callback_not_ordable();");
                    } else {
                        throw $e;
                    }
                }
				
				// 결제 완료 페이지 이동
				//throw new AlertRedirectException(null, null, null, '../order/order_end.php?orderNo=' . $order->orderNo, 'parent');
                break;
        }
    }
}