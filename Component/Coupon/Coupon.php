<?php

namespace Component\Coupon;

use App;

use Component\Database\DBTableField;
use Component\Member\Group\Util as GroupUtil;
use Component\Member\Util\MemberUtil;
use Component\Deposit\Deposit;
use Component\Mileage\Mileage;
use Component\Sms\Code;
use Component\Sms\SmsAutoCode;
use Component\Storage\Storage;
use Component\Cart\CartAdmin;
use Framework\Object\SimpleStorage;
use Framework\Utility\ArrayUtils;
use Globals;
use Request;
use Session;

class Coupon extends \Bundle\Component\Coupon\Coupon
{
    /****************** 카카오 채널 가입 쿠폰 추가 Function (2022.04.04) ******************/

    /**
     * 자동 쿠폰 발급
     *
     * @param string  $couponEventType 자동발급 이벤트 코드
     *                                 [첫구매(‘first’),구매감사(‘order’),생일축하(‘birth’),회원가입(‘join’),출석체크(‘attend’)]
     * @param integer $memNo           회원고유번호
     * @param integer $memGroupNo      회원그룹고유번호
     * @param integer $couponNo        쿠폰 고유번호
     * @param string  $eventType       회원정보 이벤트시 사용 (modify : 회원정보 수정 / life : 평생회원)
     *
     * @author su
     */
    public function setAutoCouponMemberSave($couponEventType, $memNo, $memGroupNo, $couponNo = null, $eventType = null)
    {
        $logger = \App::getInstance('logger');

        if (empty($memNo)) {
            $logger->info('cannot offer ' . $couponEventType . ' auto coupon because of empty memNo.' . __METHOD__);
            return;
        }

        if ($couponEventType == 'first') {
            $couponSaveAdminId = "첫구매 축하 쿠폰";
        } else if ($couponEventType == 'order') {
            $couponSaveAdminId = "구매 감사 쿠폰";
        } else if ($couponEventType == 'birth') {
            $couponSaveAdminId = gd_isset($this->birthDayCouponYear, date('Y')) . "년 생일 축하 쿠폰";
        } else if ($couponEventType == 'join') {
            $couponSaveAdminId = "회원가입 축하 쿠폰";
        } else if ($couponEventType == 'attend') {
            $couponSaveAdminId = "출석체크 감사 쿠폰";
        } else if ($couponEventType == 'cartRemind') {
            $couponSaveAdminId = "장바구니 알림 쿠폰";
        } else if ($couponEventType == 'plusReview') {
            $couponSaveAdminId = "플러스리뷰 전용 쿠폰";
        } else if ($couponEventType == 'memberModifyEvent') {
            $couponSaveAdminId = "회원정보수정 이벤트 쿠폰";
        } else if ($couponEventType == 'wake') {
            $couponSaveAdminId = "휴면회원 해제 감사 쿠폰";
        } else if ($couponEventType == 'kakaochannel') {
            $couponSaveAdminId = "카카오채널 가입 쿠폰";
        } else if ($couponEventType == 'joinEvent') {
            $couponSaveAdminId = "주문 간단 가입 쿠폰";
        }

        if ($couponEventType == 'attend' || $couponEventType == 'cartRemind' || $couponEventType == 'memberModifyEvent' || $couponEventType == 'joinEvent') { // 출석체크, 장바구니 알림, 회원정보수정 이벤트는 해당 설정에서 하나의 쿠폰을 설정하여 지급됨
            $autoCouponArrData = $this->getAutoCouponUsable($couponEventType, $memNo, $memGroupNo, $couponNo);
        } else { // 첫구매, 구매감사, 생일추가, 회원가입, 휴먼회원 해제는 등록되어 사용가능한 해당 이벤트 쿠폰이 모두 지급됨
            $autoCouponArrData = $this->getAutoCouponUsable($couponEventType, $memNo, $memGroupNo);
        }
        $logger->info(sprintf('Auto coupon data is not array. coupon event type[%s], memNo[%s], groupNo[%s]', $couponEventType, $memNo, $memGroupNo));
        if (is_array($autoCouponArrData)) {
            $logger->debug(__METHOD__, $autoCouponArrData);
            if (is_null($this->resultStorage)) {
                $this->resultStorage = new SimpleStorage();
                $this->resultStorage->set('total', count($autoCouponArrData));
                $this->resultStorage->set('success', 0);
            }

            $smsReceivers = [];
            foreach ($autoCouponArrData as $autoCouponKey => $autoCouponVal) {
                $arrData = [];
                $arrData['couponNo'] = $autoCouponVal['couponNo'];
                $arrData['couponSaveAdminId'] = $couponSaveAdminId;
                $arrData['memberCouponStartDate'] = $this->getMemberCouponStartDate($autoCouponVal['couponNo']);
                $arrData['memberCouponEndDate'] = $this->getMemberCouponEndDate($autoCouponVal['couponNo']);
                $arrData['memberCouponState'] = 'y';
                $arrData['memNo'] = $memNo;
                // 생일쿠폰 발급년도
                if ($couponEventType == 'birth' && empty($this->birthDayCouponYear) == false) {
                    $arrData['birthDayCouponYear'] = $this->birthDayCouponYear;
                }
                // 회원정보수정 이벤트 유형 (modify : 회원정보 수정 / life : 평생회원)
                if ($autoCouponVal['couponEventMemberModifySmsType'] === 'y') {
                    $autoCouponVal['couponEventMemberModifyEventType'] = ($eventType === 'modify') ? '회원정보 수정' : '평생회원';
                }
                $arrBind = $this->db->get_binding(DBTableField::tableMemberCoupon(), $arrData, 'insert', array_keys($arrData), ['memberCouponNo']);
                $this->db->set_insert_db(DB_MEMBER_COUPON, $arrBind['param'], $arrBind['bind'], 'y');
                $memCouponNo = $this->db->insert_id();

                if ($memCouponNo > 0) {
                    $this->resultStorage->increase('success');
                    $this->resultStorage->add('benefitSuccessMemNo', $memNo);
                    $smsReceivers[$autoCouponVal['couponNo'] . '_' . $memNo] = [
                        'memNo'                        => $memNo,
                        'couponNo'                     => $autoCouponVal['couponNo'],
                        'couponEventOrderSmsType'      => $autoCouponVal['couponEventOrderSmsType'],
                        'couponEventFirstSmsType'      => $autoCouponVal['couponEventFirstSmsType'],
                        'couponEventBirthSmsType'      => $autoCouponVal['couponEventBirthSmsType'],
                        'couponEventMemberSmsType'     => $autoCouponVal['couponEventMemberSmsType'],
                        'couponEventAttendanceSmsType' => $autoCouponVal['couponEventAttendanceSmsType'],
                        'couponEventMemberModifySmsType' => $autoCouponVal['couponEventMemberModifySmsType'],
                        'couponEventWakeSmsType'       => $autoCouponVal['couponEventWakeSmsType'],
                        'couponEventKakaoChannelSmsType' => $autoCouponVal['couponEventKakaoChannelSmsType'],
                    ];
                    $this->sendSms($memNo, $autoCouponVal);
                };

                unset($arrData);
                unset($arrBind);
                // 쿠폰 발급 카운트 증가
                $this->setCouponMemberSaveCount($autoCouponVal['couponNo']);
            }
            $this->resultStorage->set('smsReceivers', $smsReceivers);
        }
    }


    /**
     * 쿠폰 지급 시 발송 될 SMS 내역 추가
     *
     * @param       $memNo
     * @param array $autoCoupon
     */
    protected function sendSms($memNo, array $autoCoupon)
    {
        $logger = \App::getInstance('logger');
        $member = ['smsFl' => 'n'];
        if ($memNo > 1) {
            $member = \Component\Member\MemberDAO::getInstance()->selectMemberByOne($memNo);
        } else {
            $logger->info('Send coupon auto sms. not found member number.');
        }
        $smsAuto = \App::load(\Component\Sms\SmsAuto::class);
        ArrayUtils::unsetDiff($autoCoupon, explode(',', 'couponEventOrderSmsType,couponEventFirstSmsType,couponEventBirthSmsType,couponEventMemberSmsType,couponEventAttendanceSmsType,couponEventMemberModifySmsType,couponEventWakeSmsType,couponEventMemberModifyEventType,couponEventKakaoChannelSmsType'));
        foreach ($autoCoupon as $index => $item) {
            if ($item == 'y') {
                if ($member['smsFl'] == 'y' && $index != 'couponEventBirthSmsType') {   //생일축하쿠폰Sms 는 스케줄러에서 발송함
                    switch ($index) {
                        case 'couponEventOrderSmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_ORDER);
                            break;
                        case 'couponEventFirstSmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_ORDER_FIRST);
                            break;
                        case 'couponEventMemberSmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_JOIN);
                            break;
                        case 'couponEventAttendanceSmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_LOGIN);
                            break;
                        case 'couponEventMemberModifySmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_MEMBER_MODIFY);
                            break;
                        case 'couponEventWakeSmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_WAKE);
                            break;
                        case 'couponEventKakaoChannelSmsType':
                            $smsAuto->setSmsAutoCodeType(Code::COUPON_KAKAO_CHANNEL);
                            break;
                        default:
                            $logger->info(sprintf('Not found coupon sms smsAutoCodeType. couponEventSmsType[%s]', $index));
                            break;
                    }
                    $smsAuto->setSmsType(SmsAutoCode::PROMOTION);
                    $smsAuto->setReceiver($member);

                    // 회원정보수정 이벤트 추가
                    if ($autoCoupon['couponEventMemberModifySmsType'] === 'y') {
                        $smsAuto->setReplaceArguments(['eventType' => $autoCoupon['couponEventMemberModifyEventType']]);
                    }

                    $smsAuto->autoSend();
                } else {
                    $logger->info(sprintf('Disallow sms receiving. memNo[%s], smsFl [%s]', $member['memNo'], $member['smsFl']));
                }
            }
        }
    }

	public function getProductCouponUsableCheck($memberCouponNo, $dataCartSno, $cartInfo, $cartGoodsPrice)
    {
        if($dataCartSno) {
            $memberCouponArrNo = explode(INT_DIVISION, $memberCouponNo);
            $memberCouponUsable = true;
            // 카트 데이터 호출 후 상품정보 가져오기(Sno에 해당되는 상품 것만)

			$thisCallController = \App::getInstance('ControllerNameResolver')->getControllerName();

			//wg-brown 정기구독일때 cart변경
			if($thisCallController == 'Controller\Front\Subscription\CartPsController' || $thisCallController == 'Controller\Mobile\Subscription\CartPsController') {
				$cart = \App::load('\\Component\\Subscription\\CartSub');
			}else {
				$cart = \App::load('\\Component\\Cart\\Cart');
			}
            
            $cartData = $cart->getCartGoodsData($dataCartSno);
            $scmCartInfo = array_shift($cartData);
            $goodsCartInfo = array_shift($scmCartInfo)[0];
            foreach ($memberCouponArrNo as $val) {
                $memberCouponState = $this->getMemberCouponInfo($val, 'mc.memberCouponState,mc.memberCouponStartDate,mc.memberCouponEndDate,c.couponMinOrderPrice,c.couponProductMinOrderType,c.couponKindType');

                if ($memberCouponState['memberCouponState'] == 'y' || $memberCouponState['memberCouponState'] == 'cart') {
                    // 쿠폰 사용 시작일
                    if (strtotime($memberCouponState['memberCouponStartDate']) > 0) {
                        if (strtotime($memberCouponState['memberCouponStartDate']) > time()) { // 쿠폰 사용 시작일이 지금 시간 보다 크다면 제한
                            $usable = 'EXPIRATION_START_PERIOD';
                        } else {
                            $usable = 'YES';
                        }
                        // 쿠폰 사용 만료일
                    } else if (strtotime($memberCouponState['memberCouponEndDate']) > 0) {
                        if (strtotime($memberCouponState['memberCouponEndDate']) < time()) { // 쿠폰 사용 만료일이 지금 시간 보다 작다면 제한
                            $usable = 'EXPIRATION_END_PERIOD';
                        } else {
                            $usable = 'YES';
                        }
                    } else {
                        $usable = 'YES';
                    }
                    // 상품쿠폰 주문에서 적용 시
					//wg-brown 정기구독에서 쿠폰 검사 
                    $thisCallController = \App::getInstance('ControllerNameResolver')->getControllerName();
                    if (($this->couponConfig['productCouponChangeLimitType'] == 'n') && ($thisCallController == 'Controller\Front\Order\CartPsController' || $thisCallController == 'Controller\Mobile\Order\CartPsController' || $thisCallController == 'Controller\Front\Subscription\CartPsController' || $thisCallController == 'Controller\Mobile\Subscription\CartPsController')) {
                        // 최소 상품구매금액 제한
                        if($memberCouponState['couponProductMinOrderType'] == 'order') {
                            // Sno상관없이 주문 시 생성된 cart 상품가격 정보 가져오기
                            // 카트 데이터 호출 후 상품정보 가져오기(Sno에 해당되는 상품 것만)
                            $goodsCouponForTotalPrice = $cart->getProductCouponGoodsAllPrice($cartInfo, $memberCouponNo, 'front', '2');
                            $goodsCartInfo['price'] = $goodsCouponForTotalPrice;
                        }

                        // 할인/적립 기준금액
                        $totalGoodsPrice = $goodsCartInfo['price']['goodsPriceSum'];
                        if ($this->couponConfig['couponOptPriceType'] == 'y') {
                            $totalGoodsPrice += $goodsCartInfo['price']['optionPriceSum'];
                        }
                        if ($this->couponConfig['couponTextPriceType'] == 'y') {
                            $totalGoodsPrice += $goodsCartInfo['price']['optionTextPriceSum'];
                        }
                        if ($this->couponConfig['couponAddPriceType'] == 'y') {
                            $totalGoodsPrice += $goodsCartInfo['price']['addGoodsPriceSum'];
                        }

                        // 금액 체크
                        if ($memberCouponState['couponMinOrderPrice'] > $totalGoodsPrice) {
                            $usable = 'NO';
                        } else {
                            $usable = 'YES';
                        }

                        /* 타임 세일 관련 */
                        if (gd_is_plus_shop(PLUSSHOP_CODE_TIMESALE) === true) {
                            if($goodsCartInfo[0]['timeSaleFl'] == true) {
                                $strScmSQL = 'SELECT ts.couponFl as timeSaleCouponFl,ts.sno as timeSaleSno,ts.goodsNo as goodsNo FROM ' . DB_TIME_SALE . ' as ts WHERE FIND_IN_SET(' . $goodsCartInfo[0]['goodsNo'] . ', REPLACE(ts.goodsNo,"' . INT_DIVISION . '",",")) AND ts.startDt < NOW() AND  ts.endDt > NOW() AND ts.pcDisplayFl="y"';
                                $tmpScmData = $this->db->query_fetch($strScmSQL, null, false);
                                if ($tmpScmData) {
                                    if ($tmpScmData['timeSaleCouponFl'] == 'n') {
                                        $usable = 'NO';
                                    }
                                }
                                unset($tmpScmData);
                                unset($strScmSQL);
                            }
                        }
                    }
                    if($memberCouponState['couponKindType'] == 'add') {
                        $mileageGive = gd_policy('member.mileageGive');
                        if($mileageGive['giveFl'] == 'n') {
                            $usable = 'NO';
                        }
                    }
                } else {
                    $usable = 'NO';
                }
                if ($usable == 'YES' && $memberCouponUsable) {
                    $memberCouponUsable = true;
                } else {
                    $memberCouponUsable = false;
                }
            }
            return $memberCouponUsable;
        }
    }
}
