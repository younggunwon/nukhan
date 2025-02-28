<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link http://www.godo.co.kr
 */
namespace Component\Cart;


use Component\Goods\Goods;
use Component\Naver\NaverPay;
use Component\Payment\Payco\Payco;
use Component\GoodsStatistics\GoodsStatistics;
use Component\Policy\Policy;
use Component\Database\DBTableField;
use Component\Delivery\EmsRate;
use Component\Delivery\OverseasDelivery;
use Component\Mall\Mall;
use Component\Mall\MallDAO;
use Component\Member\Util\MemberUtil;
use Component\Member\Group\Util;
use Component\Validator\Validator;
use Cookie;
use Exception;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Debug\Exception\AlertRedirectCloseException;
use Framework\Debug\Exception\Except;
use Framework\Utility\ArrayUtils;
use Framework\Utility\NumberUtils;
use Framework\Utility\SkinUtils;
use Framework\Utility\StringUtils;
use Globals;
use Request;
use Session;
use Component\Godo\GodoCenterServerApi;

/**
 * 장바구니 class
 *
 * 상품과 추가상품을 분리하는 작업에서 추가상품을 기존과 동일하게 상품에 종속시켜놓은 이유는
 * 상품과 같이 배송비 및 다양한 조건들을 아직은 추가상품에 설정할 수 없어서
 * 해당 상품으로 부터 할인/적립등의 조건을 상속받아서 사용하기 때문이다.
 * 따라서 추후 추가상품쪽에 상품과 동일한 혜택과 기능이 추가되면
 * 장바구니 테이블에서 상품이 별도로 담길 수 있도록 개발되어져야 한다.
 *
 * @author Shin Donggyu <artherot@godo.co.kr>
 */
class Cart extends \Bundle\Component\Cart\Cart
{
    /*웹앤모바일 튜닝 2020-08-11 바로 주문으로 설정된 상품을 장바구니에 저장함 */
    public function setDeleteDirectCartCont()
    {
		//parent::setDeleteDirectCartCont();
        // 기존 바로 구매 상품 삭제
        //$this->setDeleteDirectCart();
    }
    /*웹앤모바일 튜닝 2020-08-11 바로 주문으로 설정된 상품을 장바구니에 저장함 */

	public function getCartGoodsData($cartIdx = null, $address = null, $tmpOrderNo = null, $isAddGoodsDivision = false, $isCouponCheck = false, $postValue = [], $setGoodsCnt = [], $setAddGoodsCnt = [], $setDeliveryMethodFl = [], $setDeliveryCollectFl = [], $deliveryBasicInfoFl = false)
    {
        // 회원 로그인 체크
        // 로그인상태면 mergeCart처리
        if (Request::getFileUri() != 'order_ps.php') {
            if ($this->isLogin === true) {
                $this->setMergeCart($this->members['memNo']);
            } else {
                $this->setMergeCart();
            }
        }

        // 장바구니 상품수량 재정의
        if (Request::getFileUri() == 'order.php') {
            $cartIdx = $this->setCartGoodsCnt($this->members['memNo'], $cartIdx);
        }

        $mallBySession = SESSION::get(SESSION_GLOBAL_MALL);

        // 절사 정책 가져오기
        $truncGoods = Globals::get('gTrunc.goods');

        // 선택한 상품만 주문시
        $arrBind = [];
        if (empty($cartIdx) === false) {
            if (is_array($cartIdx)) {
                $tmpWhere = [];
                foreach ($cartIdx as $cartSno) {
                    if (is_numeric($cartSno)) {
                        $tmpWhere[] = $this->db->escape($cartSno);
                    }
                }
                if (empty($tmpWhere) === false) {
                    $tmpAddWhere = [];
                    foreach ($tmpWhere as $val) {
                        $tmpAddWhere[] = '?';
                        $this->db->bind_param_push($arrBind, 'i', $val);
                    }
                    $arrWhere[] = 'c.sno IN (' . implode(' , ', $tmpAddWhere) . ')';
                }
                unset($tmpWhere);
            } elseif (is_numeric($cartIdx)) {
                $arrWhere[] = 'c.sno = ?';
                $this->db->bind_param_push($arrBind, 'i', $cartIdx);
            }
        }

        // 해외배송비조건에 따라 국가코드가 있으면 배송비조건 일련번호 가져와 저장
        if ($this->isGlobalFront($address)) {
            $overseasDeliverySno = $this->getDeliverySnoForOverseas($address);
        }

        // 회원 로그인 체크
        // App::getInstance('ControllerNameResolver')->getControllerRootDirectory() != 'admin'
        if ($this->isLogin === true) {
            //수기주문시 회원인 경우 memNo, siteKey 로 동시 비교
            if($this->isWrite === true  && $this->useRealCart !== true){
                $arrWhere[] = 'c.memNo = \'' . $this->members['memNo'] . '\' AND  c.siteKey = \'' . $this->siteKey . '\'';
            }
            else {
                $arrWhere[] = 'c.memNo = \'' . $this->members['memNo'] . '\'';
            }
        } else {
            $arrWhere[] = 'c.siteKey = \'' . $this->siteKey . '\'';
        }
		
        // 바로 구매 설정
		//wg-brown pg결제 Request::post()->get('wgMode') == 'directOrderSave' 추가
        if (Cookie::has('isDirectCart')
            && $this->cartPolicy['directOrderFl'] == 'y'
            && Request::getFileUri() != 'cart.php'
            && (Request::getFileUri() != 'order_ps.php' || (Request::getFileUri() == 'order_ps.php' && in_array(Request::post()->get('mode'), ['set_recalculation']) === true) || Request::post()->get('wgMode') == 'directOrderSave')
            && $this->isWrite !== true
        ) {
            // 바로 구매시 로그인 여부에 따른 장바구니 조회
            $loginCheckSiteKey = $this->isLogin === true && $this->siteKey;
            $arrWhere[] = ($loginCheckSiteKey === true) ? "c.directCart = ? AND c.siteKey = ?" : "c.directCart = ?";
            $this->db->bind_param_push($arrBind, 's', 'y');
            if ($loginCheckSiteKey) $this->db->bind_param_push($arrBind, 's', $this->siteKey);
        } else {
            // 바로 구매 쿠키 삭제
            if (Cookie::has('isDirectCart')) {
                Cookie::del('isDirectCart');
            }
        }

        if ($tmpOrderNo !== null) {
            $arrWhere = [];
            $arrWhere[] = 'c.tmpOrderNo = \'' . $tmpOrderNo . '\'';
        }

        // 정렬 방식
        $strOrder = 'c.sno DESC';

        // 장바구니 디비 및 상품 디비의 설정 (필드값 설정)
        $getData = [];

        $arrExclude['cart'] = [];
        $arrExclude['option'] = [
            'goodsNo',
            'optionNo',
        ];
        $arrExclude['addOptionName'] = [
            'goodsNo',
            'optionCd',
            'mustFl',
        ];
        $arrExclude['addOptionValue'] = [
            'goodsNo',
            'optionCd',
        ];
        $arrInclude['goods'] = [
            'goodsNm',
            'commission',
            'scmNo',
            'purchaseNo',
            'goodsCd',
            'cateCd',
            'goodsOpenDt',
            'goodsState',
            'imageStorage',
            'imagePath',
            'brandCd',
            'makerNm',
            'originNm',
            'goodsModelNo',
            'goodsPermission',
            'goodsPermissionGroup',
            'goodsPermissionPriceStringFl',
            'goodsPermissionPriceString',
            'onlyAdultFl',
            'onlyAdultImageFl',
            'goodsAccess',
            'goodsAccessGroup',
            'taxFreeFl',
            'taxPercent',
            'goodsWeight',
            'goodsVolume',
            'totalStock',
            'stockFl',
            'soldOutFl',
            'salesUnit',
            'minOrderCnt',
            'maxOrderCnt',
            'salesStartYmd',
            'salesEndYmd',
            'mileageFl',
            'mileageGoods',
            'mileageGoodsUnit',
            'goodsDiscountFl',
            'goodsDiscount',
            'goodsDiscountUnit',
            'payLimitFl',
            'payLimit',
            'goodsPriceString',
            'goodsPrice',
            'fixedPrice',
            'costPrice',
            'optionFl',
            'optionName',
            'optionTextFl',
            'addGoodsFl',
            'addGoods',
            'deliverySno',
            'delFl',
            'hscode',
            'goodsSellFl',
            'goodsSellMobileFl',
            'goodsDisplayFl',
            'goodsDisplayMobileFl',
            'mileageGroup',
            'mileageGroupInfo',
            'mileageGroupMemberInfo',
            'fixedGoodsDiscount',
            'goodsDiscountGroup',
            'goodsDiscountGroupMemberInfo',
            'exceptBenefit',
            'exceptBenefitGroup',
            'exceptBenefitGroupInfo',
            'fixedSales',
            'fixedOrderCnt',
            'goodsBenefitSetFl',
            'benefitUseType',
            'newGoodsRegFl',
            'newGoodsDate',
            'newGoodsDateFl',
            'periodDiscountStart',
            'periodDiscountEnd',
            'regDt',
            'modDt'
        ];
        $arrInclude['image'] = [
            'imageSize',
            'imageName',
            'goodsImageStorage',
            'imageUrl',
        ];
        $arrExclude['deliverySchedule'] = [
            'goodsNo'
        ];

        $arrFieldCart = DBTableField::setTableField('tableCart', null, $arrExclude['cart'], 'c');
        $arrFieldGoods = DBTableField::setTableField('tableGoods', $arrInclude['goods'], null, 'g');
        $arrFieldOption = DBTableField::setTableField('tableGoodsOption', null, $arrExclude['option'], 'go');
        $arrFieldImage = DBTableField::setTableField('tableGoodsImage', $arrInclude['image'], null, 'gi');
        $arrDeliverySchedule = DBTableField::setTableField('tableGoodsDeliverySchedule', null, $arrExclude['deliverySchedule'], 'gds');
        unset($arrExclude);

        // 장바구니 상품 기본 정보
        $strSQL = "SELECT c.sno,
            " . implode(', ', $arrFieldCart) . ", c.regDt,
            " . implode(', ', $arrFieldGoods) . ",
            " . implode(', ', $arrFieldOption) . ",
            " . implode(', ', $arrFieldImage) . ",
            " . implode(', ', $arrDeliverySchedule) . "
        FROM " . $this->tableName . " c
        INNER JOIN " . DB_GOODS . " g ON c.goodsNo = g.goodsNo
        LEFT JOIN " . DB_GOODS_OPTION . " go ON c.optionSno = go.sno AND c.goodsNo = go.goodsNo
        LEFT JOIN " . DB_GOODS_IMAGE . " as gi ON g.goodsNo = gi.goodsNo AND gi.imageKind = 'list'
        LEFT JOIN " . DB_GOODS_DELIVERY_SCHEDULE . " as gds ON g.goodsNo = gds.goodsNo
        WHERE " . implode(' AND ', $arrWhere) . "
        ORDER BY " . $strOrder;

        /**해외몰 관련 **/
        if($mallBySession) {
            $arrFieldGoodsGlobal = DBTableField::setTableField('tableGoodsGlobal',null,['mallSno']);
            $strSQLGlobal = "SELECT gg." . implode(', gg.', $arrFieldGoodsGlobal) . " FROM ".$this->tableName." as c INNER JOIN ".DB_GOODS_GLOBAL." as gg ON  c.goodsNo = gg.goodsNo AND gg.mallSno = '".$mallBySession['sno']."'  WHERE " . implode(' AND ', $arrWhere) ;
            $tmpData = $this->db->query_fetch($strSQLGlobal, $arrBind);
            $globalData = array_combine (array_column($tmpData, 'goodsNo'), $tmpData);
        }

        $query = $this->db->getBindingQueryString($strSQL, $arrBind);
        $result = $this->db->query($query);
        unset($arrWhere, $strOrder);

        // 상품리스트가 없는 경우 주문서에서 강제로 빠져나감
        if ($result === false && Request::getFileUri() != 'cart.php') {
            throw new Exception(__('장바구니에 상품이 없습니다.'));
        }

        // 삭제 상품에 대한 cartNo
        $this->cartSno = [];
        $_delCartSno = [];

        // 해외배송시 박스무게 추가
        if (Globals::get('gGlobal.isFront') || $this->isAdminGlobal === true) {
            $this->totalDeliveryWeight['box'] = $this->overseasDeliveryPolicy['data']['boxWeight'];
            $this->totalDeliveryWeight['total'] += $this->totalDeliveryWeight['box'];
        }

        //매입처 관련 정보
        if(gd_is_plus_shop(PLUSSHOP_CODE_PURCHASE) === true) {
            $strPurchaseSQL = 'SELECT purchaseNo,purchaseNm FROM ' . DB_PURCHASE . ' g  WHERE delFl = "n"';
            $tmpPurchaseData = $this->db->query_fetch($strPurchaseSQL);
            $purchaseData = array_combine(array_column($tmpPurchaseData, 'purchaseNo'), array_column($tmpPurchaseData, 'purchaseNm'));
        }

        //상품 가격 노출 관련
        $goodsPriceDisplayFl = gd_policy('goods.display')['priceFl'];

        //품절상품 설정
        if(Request::isMobile()) {
            $soldoutDisplay = gd_policy('soldout.mobile');
        } else {
            $soldoutDisplay = gd_policy('soldout.pc');
        }

        // 제외 혜택 쿠폰 번호
        $exceptCouponNo = [];
        $goodsKey = [];
        $prevGoodsNo = [];
        $goods = new Goods();
        //상품 혜택 모듈
        $goodsBenefit = \App::load('\\Component\\Goods\\GoodsBenefit');
        $delivery = \App::load('\\Component\\Delivery\\Delivery');
        while ($data = $this->db->fetch($result)) {

            //상품혜택 사용시 해당 변수 재설정
            $data = $goodsBenefit->goodsDataFrontConvert($data);

            //복수배송지 사용 && 수량 안분처리시 상품수량 재설정
            if ((\Component\Order\OrderMultiShipping::isUseMultiShipping() == true || $postValue['isAdminMultiShippingFl'] === 'y') && empty($setGoodsCnt) === false) {
                $couponConfig = gd_policy('coupon.config');
                if($couponConfig['productCouponChangeLimitType'] == 'n' && $postValue['productCouponChangeLimitType'] == 'n') { // 상품쿠폰 주문서 수정 제한 안함일 때
                    $data['goodsCouponOriginGoodsCnt'] = $data['goodsCnt']; // 복수배송지 안분된 상품 갯수가 아닌 카트 상품갯수 파악
                }
                if (empty($setGoodsCnt[$data['sno']]['goodsCnt']) === false) {
                    $data['goodsCnt'] = $setGoodsCnt[$data['sno']]['goodsCnt'];
                } else {
                    $data['goodsCnt'] = $setGoodsCnt[$data['sno']];
                }
            }
            if ((\Component\Order\OrderMultiShipping::isUseMultiShipping() == true || $postValue['isAdminMultiShippingFl'] === 'y') && empty($setAddGoodsCnt) === false) {
                $addGoodsNo = json_decode(stripslashes($data['addGoodsNo']), true);
                $addGoodsCnt = json_decode(stripslashes($data['addGoodsCnt']), true);
                foreach ($setAddGoodsCnt[$data['sno']] as $aKey => $aVal) {
                    $tmpAddGoodsNoKey = array_search($aKey, $addGoodsNo);
                    $addGoodsCnt[$tmpAddGoodsNoKey] = $aVal;
                }
                $data['addGoodsCnt'] = json_encode($addGoodsCnt);
            }
            // 복수배송지 사용 && 배송방법 변경시 배송방법 재설정
            if (\Component\Order\OrderMultiShipping::isUseMultiShipping() == true || $postValue['isAdminMultiShippingFl'] === 'y') {
                if (empty($setDeliveryMethodFl[$data['sno']]['deliveryMethodFl']) === false) {
                    $data['deliveryMethodFl'] = $setDeliveryMethodFl[$data['sno']]['deliveryMethodFl'];
                }
                if (empty($setDeliveryCollectFl[$data['sno']]['deliveryCollectFl']) === false) {
                    $data['deliveryCollectFl'] = $setDeliveryCollectFl[$data['sno']]['deliveryCollectFl'];
                }
            }
            // stripcslashes 처리
            // json형태의 경우 json값안에 "이있는경우 stripslashes처리가 되어 json_decode에러가 나므로 json값중 "이 들어갈수있는경우 $aCheckKey에 해당 필드명을 추가해서 처리해주세요
            $aCheckKey = array('optionText');
            foreach ($data as $k => $v) {
                if (!in_array($k, $aCheckKey)) {
                    $data[$k] = gd_htmlspecialchars_stripslashes($v);
                }
            }

            // 전체상품 수량
            $this->cartGoodsCnt += $data['goodsCnt'];
            // 쿠폰사용이면
            if (!empty($data['memberCouponNo']) && $data['memberCouponNo'] != '') {
                // 쿠폰 기본설정값을 가져와서 회원등급만 사용설정이면 쿠폰정보를 제거 처리 & changePrice false처리
                $couponConfig = gd_policy('coupon.config');
                if ($couponConfig['chooseCouponMemberUseType'] == 'member') {
                    $this->setMemberCouponDelete($data['sno']);
                    $data['memberCouponNo'] = '';
                    $this->changePrice = false;
                }

                // 쿠폰 사용정보를 가져와서 쿠폰사용정보가 있으면 쿠폰설정에 따른 결제 방식 제한을 처리해준다
                $aTempMemberCouponNo = explode(INT_DIVISION, $data['memberCouponNo']);
                $coupon = \App::load('\\Component\\Coupon\\Coupon');
                foreach ($aTempMemberCouponNo as $val) {
                    if ($val != null) {
                        $aTempCouponInfo = $coupon->getMemberCouponInfo($val);
                        if ($aTempCouponInfo['couponUseAblePaymentType'] == 'bank') {
                            $data['payLimitFl'] = 'y';
                            if ($data['payLimit'] == '') {
                                $data['payLimit'] = 'gb';
                            } else {
                                $aTempPayLimit = explode(STR_DIVISION, $data['payLimit']);
                                $bankCheck = 'n';
                                foreach($aTempPayLimit as $limitVal) {
                                    if ($limitVal == 'gb') {
                                        $bankCheck = 'y';
                                    }
                                }
                                if ($bankCheck == 'n') {
                                    //$data['payLimit'] = STR_DIVISION . 'gb';
                                    $data['payLimit'] = array(false);
                                }
                            }
                        }
                    }
                }
            }

            // 기준몰 상품명 저장 (무조건 기준몰 상품명이 저장되도록)
            $data['goodsNmStandard'] = $data['goodsNm'];
            if($mallBySession && $globalData[$data['goodsNo']]) {
                $data = array_replace_recursive($data, array_filter(array_map('trim',$globalData[$data['goodsNo']])));
            }

            // 상품 카테고리 정보
            $goods = \App::load(\Component\Goods\Goods::class);
            $data['cateAllCd'] = $goods->getGoodsLinkCategory($data['goodsNo']);

            // 상품 배송일정 정보
            $data = $goods->deliveryScheduleDataConvert($data);

            //매입처 관련 정보
            if(gd_is_plus_shop(PLUSSHOP_CODE_PURCHASE) === false || (gd_is_plus_shop(PLUSSHOP_CODE_PURCHASE) === true && !in_array($data['purchaseNo'],array_keys($purchaseData))))  {
                unset($data['purchaseNo']);
            }

            // 상품 삭제 여부에 따른 처리
            if ($data['delFl'] === 'y') {
                $_delCartSno[] = $data['sno'];
                unset($data);
                continue;
            } else {
                unset($data['delFl']);
            }

            // 해외배송비 선택시 기본무게에 해외배송비 조건의 무게를 더한다
            if (Globals::get('gGlobal.isFront') || $this->isAdminGlobal === true) {
                // 상품의 경우 기본 무게단위 설정에 의해 계산되기때문에 해당 단위를 가져와 별도 계산해야 함
                // 배송은 KG 단위로 적용되어진다.
                $weightConf = gd_policy('basic.weight');
                $rateWeight = ($weightConf['unit'] == 'g' ? 1000 : 1);
                $data['goodsWeight'] = ($data['goodsWeight'] > 0 ? ($data['goodsWeight'] / $rateWeight) : $this->overseasDeliveryPolicy['data']['basicWeight']);
                $this->totalDeliveryWeight['goods'] += ($data['goodsWeight'] * $data['goodsCnt']);
                $this->totalDeliveryWeight['total'] += ($data['goodsWeight'] * $data['goodsCnt']);
            }

            // 텍스트옵션 상품 정보
            $goodsOptionText = $goods->getGoodsOptionText($data['goodsNo']);
            if (empty($data['optionText']) === false && gd_isset($goodsOptionText)) {
                $optionTextKey = array_keys(json_decode($data['optionText'], true));
                foreach ($goodsOptionText as $goodsOptionTextInfo) {
                    if (in_array($goodsOptionTextInfo['sno'], $optionTextKey) === true) {
                        $data['optionTextInfo'][$goodsOptionTextInfo['sno']] = [
                            'optionSno' => $goodsOptionTextInfo['sno'],
                            'optionName' => $goodsOptionTextInfo['optionName'],
                            'baseOptionTextPrice' => $goodsOptionTextInfo['addPrice'],
                        ];
                    }
                }

            }

            // 추가 상품 정보
            $data['addGoodsMustFl'] = $mustFl = json_decode(gd_htmlspecialchars_stripslashes($data['addGoods']),true);
            if ($data['addGoodsFl'] === 'y' && empty($data['addGoodsNo']) === false) {
                $data['addGoodsNo'] = json_decode($data['addGoodsNo'], true);
                $data['addGoodsCnt'] = json_decode($data['addGoodsCnt'], true);
                if ($isAddGoodsDivision !== false) {
                    foreach($mustFl as $_key=>$val){
                        $key = $_key;
                        break;
                    }
                    $data['addGoodsMustFl'] = $mustFl[$key]['mustFl'];
                }
            } else {
                $data['addGoodsNo'] = '';
                $data['addGoodsCnt'] = '';
                if ($isAddGoodsDivision !== false) {
                    $data['addGoodsMustFl'] = '';
                }
            }

            // 추가 상품 필수 여부
            if ($data['addGoodsFl'] === 'y' && empty($data['addGoods']) === false) {
                foreach ($mustFl as $k => $v) {
                    if ($v['mustFl'] == 'y') {
                        if (is_array($data['addGoodsNo']) === false) {
                            $data['addGoodsSelectedFl'] = 'n';
                            break;
                        } else {
                            $addGoodsResult = array_intersect($v['addGoods'], $data['addGoodsNo']);
                            if (empty($addGoodsResult) === true) {
                                $data['addGoodsSelectedFl'] = 'n';
                                break;
                            }
                        }
                    }
                }
                unset($mustFl);
            }

            // 텍스트 옵션 정보 (sno, value)
            $data['optionTextSno'] = [];
            $data['optionTextStr'] = [];
            if ($data['optionTextFl'] === 'y' && empty($data['optionText']) === false) {
                $arrText = json_decode($data['optionText']);
                foreach ($arrText as $key => $val) {
                    $data['optionTextSno'][] = $key;
                    $data['optionTextStr'][$key] = $val;
                    unset($tmp);
                }
            }
            //unset($data['optionText']);

            // 텍스트옵션 필수 사용 여부
            if ($data['optionTextFl'] === 'y') {
                if (gd_isset($goodsOptionText)) {
                    foreach ($goodsOptionText as $k => $v) {
                        if ($v['mustFl'] == 'y' && !in_array($v['sno'], $data['optionTextSno'])) {
                            $data['optionTextEnteredFl'] = 'n';
                        }
                    }
                }
            }
            unset($optionText);

            // 상품 구매 가능 여부
            $data = $this->checkOrderPossible($data);

            //구매불가 대체 문구 관련
            if($data['goodsPermissionPriceStringFl'] =='y' && $data['goodsPermission'] !='all' && (($data['goodsPermission'] =='member'  && $this->isLogin === false) || ($data['goodsPermission'] =='group'  && !in_array($this->_memInfo['groupSno'],explode(INT_DIVISION,$data['goodsPermissionGroup']))))) {
                $data['goodsPriceString'] = $data['goodsPermissionPriceString'];
            }

            //품절일경우 가격대체 문구 설정
            if (($data['soldOutFl'] === 'y' || ($data['soldOutFl'] === 'n' && $data['stockFl'] === 'y' && ($data['totalStock'] <= 0 || $data['totalStock'] < $data['goodsCnt']))) && $soldoutDisplay['soldout_price'] !='price'){
                if($soldoutDisplay['soldout_price'] =='text' ) {
                    $data['goodsPriceString'] = $soldoutDisplay['soldout_price_text'];
                } else if($soldoutDisplay['soldout_price'] =='custom' ) {
                    $data['goodsPriceString'] = "<img src='".$soldoutDisplay['soldout_price_img']."'>";
                }
            }

            $data['goodsPriceDisplayFl'] = 'y';
            if (empty($data['goodsPriceString']) === false && $goodsPriceDisplayFl =='n') {
                $data['goodsPriceDisplayFl'] = 'n';
            }

            // 정책설정에서 품절상품 보관설정의 보관상품 품절시 자동삭제로 설정한 경우
            if ($this->cartPolicy['soldOutFl'] == 'n' && $data['orderPossibleCode'] == self::POSSIBLE_SOLD_OUT) {
                $_delCartSno[] = $data['sno'];
                unset($data);
                continue;
            }

            // 상품결제 수단에 따른 주문페이지 결제수단 표기용 데이터
            if ($data['payLimitFl'] == 'y' && gd_isset($data['payLimit'])) {
                $payLimit = explode(STR_DIVISION, $data['payLimit']);
                $data['payLimit'] = $payLimit;

                if (is_array($payLimit) && $this->payLimit) {
                    $this->payLimit = array_intersect($this->payLimit, $payLimit);
                    if (empty($this->payLimit) === true) {
                        $this->payLimit = ['false'];
                    }
                } else {
                    $this->payLimit = $payLimit;
                }
            }

            // 비회원시 담은 상품과 회원로그인후 담은 상품이 중복으로 있는경우 재고 체크
            $data['duplicationGoods'] = 'n';
            if (isset($tmpStock[$data['goodsNo']][$data['optionSno']][$data['optionText']]) === false) {
                $tmpStock[$data['goodsNo']][$data['optionSno']][$data['optionText']] = $data['goodsCnt'];
            } else {
                $data['duplicationGoods'] = 'y';
                $chkStock = $tmpStock[$data['goodsNo']][$data['optionSno']][$data['optionText']] + $data['goodsCnt'];
                if ($data['stockFl'] == 'y' && $data['stockCnt'] < $chkStock) {
                    $this->orderPossible = false;
                    $data['stockOver'] = 'y';
                }
            }

            // 상품구분 초기화 (상품인지 추가상품인지?)
            $data['goodsType'] = 'goods';

            // 상품 이미지 처리 @todo 상품 사이즈 설정 값을 가지고 와서 이미지 사이즈 변경을 할것

            // 세로사이즈고정 체크
            $imageSize = SkinUtils::getGoodsImageSize('list');
            $imageConf = gd_policy('goods.image');

            if (Request::isMobile() || $imageConf['imageType'] != 'fixed') {
                $imageSize['size1'] = '40'; // 기존 사이즈
                $imageSize['hsize1'] = '';
            }

            // 상품 이미지 처리
            if ($data['onlyAdultFl'] == 'y' && gd_check_adult() === false && $data['onlyAdultImageFl'] =='n') {
                if (Request::isMobile()) {
                    $data['goodsImageSrc'] = "/data/icon/goods_icon/only_adult_mobile.png";
                } else {
                    $data['goodsImageSrc'] = "/data/icon/goods_icon/only_adult_pc.png";
                }

                $data['goodsImage'] = SkinUtils::makeImageTag($data['goodsImageSrc'], $imageSize['size1']);
            } else {
                $data['goodsImage'] = gd_html_preview_image($data['goodsImageStorage'] == 'obs' ? $data['imageUrl'] : $data['imageName'], $data['imagePath'], $data['imageStorage'], $imageSize['size1'], 'goods', $data['goodsNm'], 'class="imgsize-s"', false, false, $imageSize['hsize1']);
            }



            unset($data['imageStorage'], $data['imagePath'], $data['imageName'], $data['imageUrl'], $data['imagePath']);

            $data['goodsMileageExcept'] = 'n';
            $data['couponBenefitExcept'] = 'n';
            $data['memberBenefitExcept'] = 'n';

            //타임세일 할인 여부
            $data['timeSaleFl'] = false;
            if (gd_is_plus_shop(PLUSSHOP_CODE_TIMESALE) === true && Request::post()->get('mode') !== 'cartEstimate') {

                $timeSale = \App::load('\\Component\\Promotion\\TimeSale');
                $timeSaleInfo = $timeSale->getGoodsTimeSale($data['goodsNo']);
                if ($timeSaleInfo) {
                    $data['timeSaleFl'] = true;
                    if ($timeSaleInfo['mileageFl'] == 'n') {
                        $data['goodsMileageExcept'] = "y";
                    }
                    if ($timeSaleInfo['couponFl'] == 'n') {
                        $data['couponBenefitExcept'] = "y";

                        // 타임세일 상품적용 쿠폰 사용불가 체크
                        if (empty($data['memberCouponNo']) === false) {
                            $exceptCouponNo[$data['sno']] = $data['memberCouponNo'];
                        }
                    }
                    if ($timeSaleInfo['memberDcFl'] == 'n') {
                        $data['memberBenefitExcept'] = "y";
                    }
                    if ($data['goodsPrice'] > 0) {
                        // 타임세일 할인금액
                        $data['timeSalePrice'] = gd_number_figure((($timeSaleInfo['benefit'] / 100) * $data['goodsPrice']), $truncGoods['unitPrecision'], $truncGoods['unitRound']);

                        $data['goodsPrice'] = gd_number_figure($data['goodsPrice'] - (($timeSaleInfo['benefit'] / 100) * $data['goodsPrice']), $truncGoods['unitPrecision'], $truncGoods['unitRound']);
                    }
                    //상품 옵션가(일체형,분리형) 타임세일 할인율 적용 ( 텍스트 옵션가 / 추가상품가격 제외 )
                    if($data['optionFl'] === 'y'){
                        // 타임세일 할인금액
                        $data['timeSalePrice'] = gd_number_figure($data['timeSalePrice'] + (($timeSaleInfo['benefit'] / 100) * $data['optionPrice']), $truncGoods['unitPrecision'], $truncGoods['unitRound']);

                        $data['optionPrice'] = gd_number_figure($data['optionPrice'] - (($timeSaleInfo['benefit'] / 100) * $data['optionPrice']), $truncGoods['unitPrecision'], $truncGoods['unitRound']);
                    }
                }
            }

            // 혜택제외 체크 (쿠폰)
            $exceptBenefit = explode(STR_DIVISION, $data['exceptBenefit']);
            $exceptBenefitGroupInfo = explode(INT_DIVISION, $data['exceptBenefitGroupInfo']);
            if (in_array('coupon', $exceptBenefit) === true && ($data['exceptBenefitGroup'] == 'all' || ($data['exceptBenefitGroup'] == 'group' && in_array($this->_memInfo['groupSno'], $exceptBenefitGroupInfo) === true))) {
                if (empty($data['memberCouponNo']) === false) {
                    $exceptCouponNo[$data['sno']] = $data['memberCouponNo'];
                }
                $data['couponBenefitExcept'] = "y";
            }

            //배송방식에 관한 데이터
            $data['goodsDeliveryMethodFl'] = $data['deliveryMethodFl'];
            $data['goodsDeliveryMethodFlText'] = gd_get_delivery_method_display($data['deliveryMethodFl']);
            $data['deliveryMethodVisitArea'] = '';
            //if ($data['deliveryMethodFl'] == 'visit') {
            $deliveryData = $delivery->getSnoDeliveryBasic($data['deliverySno']);
            $data['deliveryMethodVisitArea'] = $delivery->getVisitAddress($data['deliverySno'], true);
            $data['goodsDeliveryFl'] = $deliveryData['goodsDeliveryFl'];
            $data['sameGoodsDeliveryFl'] = $deliveryData['sameGoodsDeliveryFl'];
            //}

            // 회원 추가 할인 여부 설정 (적용제외 대상이 있는 경우 적용 제외)
            $data = $this->getMemberDcFlInfo($data);

            // 해외배송의 배송비조건 일련번호 추출 후 기존 상품데이터에 배송지조건 일괄 변경
            if ($this->isGlobalFront($address)) {
                $data['deliverySno'] = $overseasDeliverySno;
            }

            $tmpOptionName = [];
            for ($optionKey = 1; $optionKey <= 5; $optionKey++) {
                if (empty($data['optionValue' . $optionKey]) === false) {
                    $tmpOptionName[] = $data['optionValue' . $optionKey];
                }
            }
            $data['optionNm'] = @implode('/', $tmpOptionName);
            unset($tmpOptionName);

            if (in_array($data['goodsNo'], $goodsKey) === false) {
                $goodsKey[] = $data['goodsNo'];
            }
            $data['goodsKey'] = array_search($data['goodsNo'], $goodsKey);

            // 현재 주문 중인 장바구니 SNO
            $this->cartSno[] = $data['sno'];

            // 쇼핑 계속하기 주소 처리
            if ($data['cateCd'] && empty($this->shoppingUrl) === true) {
                $this->shoppingUrl = $data['cateCd'];
            }

            if (\Component\Order\OrderMultiShipping::isUseMultiShipping() === true && $deliveryBasicInfoFl === true && empty($this->deliveryBasicInfo[$data['deliverySno']]) === true) {
                $this->deliveryBasicInfo[$data['deliverySno']] = $delivery->getSnoDeliveryBasic($data['deliverySno']);
                $this->deliveryBasicInfo[$data['deliverySno']]['deliveryMethodFl'] = array_filter(explode(STR_DIVISION, $this->deliveryBasicInfo[$data['deliverySno']]['deliveryMethodFl']));
            }

            if (in_array($data['goodsNo'], $prevGoodsNo) === false) {
                $data['equalGoodsNo'] = true;
                $prevGoodsNo[] = $data['goodsNo'];
            }

            $getData[] = $data;
            unset($data);
        }

        if ($isCouponCheck === true && empty($exceptCouponNo) === false && (MemberUtil::isLogin() || ($this->isWrite && empty($this->_memInfo) === false))) {
            if ($this->setCartCouponReset($exceptCouponNo) === true) {
                throw new AlertRedirectException(__('쿠폰 할인/적립 혜택이 변경된 상품이 포함되어 있으니 장바구니에서 확인 후 다시 주문해주세요.'), null, null, '../order/cart.php');
            }
        }

        // 삭제 상품이 있는 경우 해당 장바구니 삭제
        if (empty($_delCartSno) === false) {
            $this->setCartDelete($_delCartSno);
        }

        // 쇼핑계속하기 버튼
        if (empty($this->shoppingUrl) === true) {
            $this->shoppingUrl = URI_OVERSEAS_HOME;
        } else {
            $this->shoppingUrl = URI_OVERSEAS_HOME . 'goods/goods_list.php?cateCd=' . $this->shoppingUrl;
        }

        // 해외배송시 EMS조건에 30KG 이상인 경우 체크
        if ($this->overseasDeliveryPolicy['data']['standardFl'] === 'ems' && $this->totalDeliveryWeight['total'] > 30) {
            $this->emsDeliveryPossible = false;
        }

        //회원구매제한 체크 order.php 의 useSettleKind 함수에서도 한번 체크함
        if (!in_array('false', $this->payLimit)) { // 상품별 결제수단 체크에서 결제가능한 결제수단이 없는것으로 나왔을때는 처리필요없음
            if($this->isWrite === true){
                if (empty($this->_memInfo) === false && !in_array('gb', $this->_memInfo['settleGb'])) {
                    if (empty($this->payLimit)) {
                        $this->payLimit = $this->_memInfo['settleGb'];
                    } else {
                        if($this->_memInfo['settleGb'] != 'all') {
                            if (is_array($this->_memInfo['settleGb']) === false) {
                                $settleGb = Util::matchSettleGbDataToString($this->_memInfo['settleGb']);
                            } else {
                                $settleGb = $this->_memInfo['settleGb'];
                            }
                            $payLimit = array_intersect($settleGb, $this->payLimit);
                            if (count($this->payLimit) > 0 && !in_array('false', $this->payLimit) && count($payLimit) == 0) {
                                $this->payLimit = ['false'];
                            } else {
                                $this->payLimit = $payLimit;
                            }
                        }
                    }
                }
            }
            else {
                if (empty($this->_memInfo) === false) {
                    if (empty($this->payLimit)) {
                        $this->payLimit = $this->_memInfo['settleGb'];
                    } else {
                        $payLimit = array_intersect($this->_memInfo['settleGb'], $this->payLimit);
                        if (count($this->payLimit) > 0 && !in_array('false', $this->payLimit) && count($payLimit) == 0) {
                            $this->payLimit = ['false'];
                        } else {
                            $this->payLimit = $payLimit;
                        }
                    }
                }
            }
        }

        // 결제수단설정
        $settle = gd_policy('order.settleKind');
        //사용가능한 결제수단 체크
        $usefulSettleKind = array();
        // 마일리지 사용여부 설정
        $mileagePayUsableFl = gd_policy('member.mileageBasic.payUsableFl');
        // 예치금 사용여부 설정
        $depositPayUsableFl = gd_policy('member.depositConfig.payUsableFl');

        // 결제수단설정에따른 사용가능여부
        if($this->payLimit != false) {
            if(is_array($payLimit)) {
                foreach($this->payLimit as $key => $val){
                    switch ($val){
                        case 'gm' :
                            if($mileagePayUsableFl == 'y'){
                                $usefulSettleKind[] = $val;
                            }
                            break;
                        case 'gd' :
                            if($depositPayUsableFl == 'y'){
                                $usefulSettleKind[] = $val;
                            }
                            break;
                        default :
                            if($settle[$val]['useFl'] !== 'n'){
                                $usefulSettleKind[] = $val;
                            }
                            break;
                    }
                }
            } else {
                $usefulSettleKind[] = $this->payLimit;
            }

            if(!$usefulSettleKind){
                $this->payLimit = ['false'];
            }
        }

        // 장바구니 상품에 대한 계산된 정보
        $getCart = $this->getCartDataInfo($getData, $postValue);

        // 글로벌 해외배송 조건에 따라서 처리
        if (Globals::get('gGlobal.isFront') || $this->isAdminGlobal === true) {
            if ($address !== null) {
                $getCart = $this->getOverseasDeliveryDataInfo($getCart, array_column($getData, 'deliverySno'), $address);
            }
        } else {
            // 복수배송지 사용시 배송정보 재설정
            if ((\Component\Order\OrderMultiShipping::isUseMultiShipping() === true && $postValue['multiShippingFl'] == 'y') || $postValue['isAdminMultiShippingFl'] === 'y') {
                foreach ($postValue['orderInfoCdData'] as $key => $val) {
                    $tmpGetCart = [];
                    $tmpAllGetKey = [];
                    $tmpDeliverySnos = [];
                    foreach ($val as $tVal) {
                        $tmpScmNo = $this->multiShippingOrderInfo[$tVal]['scmNo'];
                        $tmpDeliverySno = $this->multiShippingOrderInfo[$tVal]['deliverySno'];
                        $tmpGetKey = $this->multiShippingOrderInfo[$tVal]['getKey'];
                        $tmpAllGetKey[] = $tmpGetKey;
                        $tmpDeliverySnos[] = $tmpDeliverySno;

                        $tmpGetCart[$tmpScmNo][$tmpDeliverySno][$tmpGetKey] = $getCart[$tmpScmNo][$tmpDeliverySno][$tmpGetKey];
                    }
                    if ($key > 0) {
                        $multiAddress = $postValue['receiverAddressAdd'][$key];
                    } else {
                        $multiAddress = $address;
                    }

                    $tmpGetCart = $this->getDeliveryDataInfo($tmpGetCart, $tmpDeliverySnos, $multiAddress, $postValue['multiShippingFl'], $key);
                    foreach ($tmpGetCart as $sKey => $sVal) {
                        foreach ($sVal as $dKey => $dVal) {
                            foreach ($dVal as $getKey => $getVal) {
                                if (empty($tmpGetCart[$sKey][$dKey][$getKey]) === false) {
                                    $getCart[$sKey][$dKey][$getKey] = $tmpGetCart[$sKey][$dKey][$getKey];
                                }
                            }

                        }
                    }
                    unset($tmpGetCart);
                }
            } else {
                $getCart = $this->getDeliveryDataInfo($getCart, array_column($getData, 'deliverySno'), $address);
            }
        }

        // 장바구니 SCM 정보
        if (is_array($getCart)) {
            $scmClass = \App::load(\Component\Scm\Scm::class);
            $this->cartScmCnt = count($getCart);
            $this->cartScmInfo = $scmClass->getCartScmInfo(array_keys($getCart));
        }

        // 회원 할인 총 금액
        if ($this->getChannel() != 'naverpay') {
            $this->totalSumMemberDcPrice = $this->totalMemberDcPrice + $this->totalMemberOverlapDcPrice;
        }
        // 총 부가세율
        $this->totalVatRate = gd_tax_rate($this->totalGoodsPrice, $this->totalPriceSupply);

        // 비과세 설정에 따른 세금계산서 출력 여부
        if ($this->taxInvoice === true && $this->taxGoodsChk === false) {
            $this->taxInvoice = false;
        }

        // 총 결제 금액 (상품별 금액 + 배송비 - 상품할인 - 회원할인 - 사용마일리지(X) - 상품쿠폰할인 - 주문쿠폰할인(X) - 마이앱할인 : 여기서는 장바구니에 있는 상품에 대해서만 계산하므로 결제 예정금액임)
        // 주문관련 할인금액 및 마일리지/예치금 사용은 setOrderSettleCalculation에서 별도로 계산됨

        $this->totalSettlePrice = $this->totalGoodsPrice + $this->totalDeliveryCharge - $this->totalGoodsDcPrice - $this->totalSumMemberDcPrice - $this->totalCouponGoodsDcPrice;

        // 마이앱 사용에 따른 분기 처리
        if ($this->useMyapp) {
            $this->totalSettlePrice -= $this->totalMyappDcPrice;
        }

        if($this->totalSettlePrice < 0 ) $this->totalSettlePrice = 0;

        // 총 적립 마일리지 (상품별 총 상품 마일리지 + 회원 그룹 총 마일리지 + 총 상품 쿠폰 마일리지 + 총 주문 쿠폰 적립 금액 : 여기서는 장바구니에 있는 상품에 대해서만 계산하므로 적립 예정금액임)
        $this->totalMileage = $this->totalGoodsMileage + $this->totalMemberMileage + $this->totalCouponGoodsMileage + $this->totalCouponOrderMileage;

        // 주문에 추가상품 분리데이터를 저장하기 위해 별도 생성 (추가상품 안분까지 적용된 데이터를 가지고와 처리)
        if ($isAddGoodsDivision !== false) {
            // 최종 반환할 $getCart 변수 재설정
            $tmpGetCart = [];

            foreach ($getCart as $sKey => $sVal) {
                foreach ($sVal as $dKey => $dVal) {
                    foreach ($dVal as $gKey => $gVal) {
                        // 기본 상품에 속해있던 추가상품 관련 데이터 정리
                        if ($gVal['price']['goodsPriceSubtotal'] > 0) {
                            $gVal['price']['goodsPriceSubtotal'] -= $gVal['price']['addGoodsPriceSum'];
                            if ($gVal['price']['goodsPriceSubtotal'] < 0) $gVal['price']['goodsPriceSubtotal'] = 0;
                            //$gVal['price']['addGoodsPriceSum'] = 0;
                        }

                        $gVal['price']['goodsPriceTotal'] = ($gVal['price']['goodsPriceSum'] + $gVal['price']['optionPriceSum'] + $gVal['price']['optionTextPriceSum']) - ($gVal['price']['goodsDcPrice'] + $gVal['price']['goodsMemberDcPrice'] + $gVal['price']['goodsMemberOverlapDcPrice'] + $gVal['price']['goodsCouponGoodsDcPrice']);

                        // 마이앱 사용에 따른 분기 처리
                        if ($this->useMyapp) {
                            $gVal['price']['goodsPriceTotal'] -= $gVal['price']['myappDcPrice'];
                        }

                        // 총 상품 무게 계산
                        $gVal['goodsWeight'] = $gVal['goodsWeight'] * $gVal['goodsCnt'];

                        // 총 상품 용량 계산
                        $gVal['goodsVolume'] = $gVal['goodsVolume'] * $gVal['goodsCnt'];

                        // 추가상품 변수에 담고 언셋
                        $addGoods = $gVal['addGoods'];
                        unset($gVal['addGoods']);

                        // 기존 상품정보에 추가 내용
                        $gVal['goodsType'] = self::CART_GOODS_TYPE_GOODS;

                        // 원래 상품정보 그대로 추가
                        $tmpGetCart[$sKey][$dKey][] = $gVal;

                        // 추가상품 배열화
                        if (empty($addGoods) === false) {
                            // 추가상품을 상품화시켜 담을 배열
                            foreach ($addGoods as $aKey => $aVal) {
                                // 초기화
                                $tmpAddGoods = [];

                                // 부모 상품의 기본 정보 초기화
                                $tmpAddGoods['goodsType'] = self::CART_GOODS_TYPE_ADDGOODS;
                                $tmpAddGoods['optionTextFl'] = 'n';
                                $tmpAddGoods['goodsDiscountFl'] = 'n';
                                $tmpAddGoods['goodsDiscount'] = 0;
                                $tmpAddGoods['goodsDiscountUnit'] = '';
                                $tmpAddGoods['couponBenefitExcept'] = 'y';

                                // 부모 상품의 설정을 상속받아 설정
                                $tmpAddGoods['sno'] = $gVal['sno'];
                                $tmpAddGoods['siteKey'] = $gVal['siteKey'];
                                $tmpAddGoods['directCart'] = $gVal['directCart'];
                                $tmpAddGoods['memNo'] = $gVal['memNo'];
                                $tmpAddGoods['deliveryCollectFl'] = $gVal['deliveryCollectFl'];
                                $tmpAddGoods['tmpOrderNo'] = $gVal['tmpOrderNo'];
                                $tmpAddGoods['goodsDisplayMobileFl'] = $gVal['goodsDisplayMobileFl'];
                                $tmpAddGoods['goodsSellFl'] = $gVal['goodsSellFl'];
                                $tmpAddGoods['goodsSellMobileFl'] = $gVal['goodsSellMobileFl'];
                                $tmpAddGoods['cateCd'] = $gVal['cateCd'];
                                $tmpAddGoods['deliverySno'] = $gVal['deliverySno'];
                                $tmpAddGoods['memberBenefitExcept'] = $gVal['memberBenefitExcept'];
                                $tmpAddGoods['goodsMileageExcept'] = $gVal['goodsMileageExcept'];
                                $tmpAddGoods['mileageFl'] = $gVal['mileageFl'];
                                $tmpAddGoods['mileageGoods'] = $gVal['mileageGoods'];
                                $tmpAddGoods['mileageGoodsUnit'] = $gVal['mileageGoodsUnit'];
                                $tmpAddGoods['addDcFl'] = $gVal['addDcFl'];
                                $tmpAddGoods['overlapDcFl'] = $gVal['overlapDcFl'];
                                $tmpAddGoods['orderPossible'] = $gVal['orderPossible'];
                                $tmpAddGoods['payLimitFl'] = $gVal['payLimitFl'];
                                $tmpAddGoods['payLimit'] = $gVal['payLimit'];
                                $tmpAddGoods['goodsPermission'] = $gVal['goodsPermission'];
                                $tmpAddGoods['goodsPermissionGroup'] = $gVal['goodsPermissionGroup'];
                                $tmpAddGoods['onlyAdultFl'] = $gVal['onlyAdultFl'];
                                $tmpAddGoods['goodsDeliveryFl'] = $gVal['goodsDeliveryFl'];
                                $tmpAddGoods['goodsDeliveryFixFl'] = $gVal['goodsDeliveryFixFl'];
                                $tmpAddGoods['goodsDeliveryMethod'] = $gVal['goodsDeliveryMethod'];
                                $tmpAddGoods['deliveryMethodFl'] = $gVal['deliveryMethodFl'];
                                $tmpAddGoods['goodsDeliveryCollectFl'] = $gVal['goodsDeliveryCollectFl'];
                                $tmpAddGoods['goodsDeliveryWholeFreeFl'] = $gVal['goodsDeliveryWholeFreeFl'];
                                $tmpAddGoods['goodsDeliveryTaxFreeFl'] = $gVal['goodsDeliveryTaxFreeFl'];
                                $tmpAddGoods['goodsDeliveryTaxPercent'] = $gVal['goodsDeliveryTaxPercent'];

                                // 추가상품에서 가져온 정보 저장
                                $tmpAddGoods['goodsNo'] = $tmpAddGoods['addGoodsNo'] = $aVal['addGoodsNo'];
                                $tmpAddGoods['goodsCnt'] = $aVal['addGoodsCnt'];
                                $tmpAddGoods['goodsNm'] = $aVal['addGoodsNm'];
                                if($mallBySession && $aVal['addGoodsNmStandard']) {
                                    $tmpAddGoods['goodsNmStandard'] = $aVal['addGoodsNmStandard'];
                                }
                                $tmpAddGoods['scmNo'] = $aVal['scmNo'];
                                $tmpAddGoods['purchaseNo'] = $aVal['purchaseNo'];
                                $tmpAddGoods['commission'] = $aVal['commission'];
                                $tmpAddGoods['goodsCd'] = $aVal['goodsCd'];
                                $tmpAddGoods['goodsModelNo'] = $aVal['goodsModelNo'];
                                $tmpAddGoods['brandCd'] = $aVal['brandCd'];
                                $tmpAddGoods['makerNm'] = $aVal['makerNm'];
                                $tmpAddGoods['goodsDisplayFl'] = $aVal['viewFl'];
                                $tmpAddGoods['stockUseFl'] = $aVal['stockUseFl'];
                                $tmpAddGoods['stockCnt'] = $aVal['stockCnt'];
                                $tmpAddGoods['soldOutFl'] = $aVal['soldOutFl'];
                                $tmpAddGoods['taxFreeFl'] = $aVal['taxFreeFl'];
                                $tmpAddGoods['taxPercent'] = $aVal['taxPercent'];
                                $tmpAddGoods['goodsImage'] = $aVal['addGoodsImage'];
                                $tmpAddGoods['parentMustFl'] = $gVal['addGoodsMustFl'];
                                $tmpAddGoods['parentGoodsNo'] = $gVal['goodsNo'];
                                $tmpAddGoods['price']['goodsPrice'] = $aVal['addGoodsPrice'];
                                $tmpAddGoods['price']['costPrice'] = $aVal['addCostGoodsPrice'];
                                $tmpAddGoods['price']['goodsMemberDcPrice'] = $aVal['addGoodsMemberDcPrice'];
                                $tmpAddGoods['price']['goodsMemberOverlapDcPrice'] = $aVal['addGoodsMemberOverlapDcPrice'];
                                $tmpAddGoods['price']['goodsCouponGoodsDcPrice'] = $aVal['addGoodsCouponGoodsDcPrice'];
                                $tmpAddGoods['price']['goodsPriceSum'] = ($aVal['addGoodsPrice'] * $aVal['addGoodsCnt']);
                                $tmpAddGoods['price']['goodsPriceSubtotal'] = $tmpAddGoods['price']['goodsPriceSum'];
								//                                $tmpAddGoods['price']['goodsPriceSubtotal'] = ($tmpAddGoods['price']['goodsPriceSum'] - $aVal['addGoodsMemberDcPrice'] - $aVal['addGoodsMemberOverlapDcPrice']);
                                $tmpAddGoods['price']['goodsPriceTotal'] = ($tmpAddGoods['price']['goodsPriceSum'] - $aVal['addGoodsMemberDcPrice'] - $aVal['addGoodsMemberOverlapDcPrice'] - $aVal['addGoodsCouponGoodsDcPrice']);
                                $tmpAddGoods['mileage']['goodsGoodsMileage'] = $aVal['addGoodsGoodsMileage'];
                                $tmpAddGoods['mileage']['goodsMemberMileage'] = $aVal['addGoodsMemberMileage'];
                                $tmpAddGoods['mileage']['goodsCouponGoodsMileage'] = $aVal['addGoodsCouponGoodsMileage'];

                                // 상품 옵션 처리
                                $tmpAddGoods['option'] = [];
                                if (empty($aVal['optionNm']) === false) {
                                    $tmp = explode(STR_DIVISION, $aVal['optionNm']);
                                    for ($i = 0; $i < 1; $i++) {
                                        $tmpAddGoods['option'][$i]['optionName'] = '';
                                        $tmpAddGoods['option'][$i]['optionValue'] = $aVal['optionNm'];
                                    }
                                    unset($tmp);
                                }
                                unset($tmpAddGoods['optionName']);

                                // 재정의 배열에 추가
                                $tmpGetCart[$sKey][$dKey][] = $tmpAddGoods;
                                unset($tmpAddGoods);
                            }
                        }
                    }
                }
            }
            unset($getCart);

            // 장바구니
            $getCart = $tmpGetCart;
        }

        unset($getData, $arrTmp);

        return $getCart;
    }

	//정기배송일때 절사정책을 적용하기위해 해당함수 local로 가져와서처리함-상속이안됨
    protected function getGoodsDcData($goodsDiscountFl, $goodsDiscount, $goodsDiscountUnit, $goodsCnt, $goodsPrice, $fixedGoodsDiscount = null, $goodsDiscountGroup = null, $goodsDiscountGroupMemberInfo = null)
    {
		///절삭기준 시작 2022.04.06민트웹
		$server=\Request::server()->toArray();
		$db=\App::load(\DB::class);

		$subscription=0;

		if(preg_match('/goodsNo/',$server['HTTP_REFERER'])){
			
			$params = parse_url($server['HTTP_REFERER']);
			parse_str($params['query'],$param);
			
			$goodsNo=$param['goodsNo'];

			$strSQL ="select useSubscription from ".DB_GOODS." where goodsNo=?";
			$rows = $db->query_fetch($strSQL,['i',$goodsNo],false);

			if($rows['useSubscription']==1){
				$subscription=1;
			}
		}

		if($this->tableName=="wm_subCart"){
			$subscription=1;
		}

		if($subscription==1){
			$memNo=\Session::get("member.memNo");
			$strSQL="SELECT * FROM wm_subCart where memNo='$memNo' ORDER BY sno DESC";
			$wmCartInfo = $db->query_fetch($strSQL);

			$ArrGoodsNo[]=$wmCartInfo[0]['goodsNo'];

			$subObj = new \Component\Subscription\Subscription($ArrGoodsNo);
			$subCfg = $subObj->getCfg();
			
		}
		///절삭기준 종료
			
        // 상품 할인 금액
        $goodsDcPrice = $goodsPriceTmp = 0;
        $fixedGoodsDiscountData = explode(STR_DIVISION, $fixedGoodsDiscount);
        $goodsPriceTmp = $goodsPrice['goodsPriceSum'];

        if (in_array('option', $fixedGoodsDiscountData) === true) $goodsPriceTmp +=$goodsPrice['optionPriceSum'];
        if (in_array('text', $fixedGoodsDiscountData) === true) $goodsPriceTmp +=$goodsPrice['optionTextPriceSum'];

        // 상품금액 단가 계산
        $goodsPrice['goodsPrice'] = ($goodsPriceTmp / $goodsCnt);

        // 상품 할인 기준 금액 처리
        $tmp['discountByPrice'] = $goodsPrice['goodsPrice'];

        // 절사 내용
        $tmp['trunc'] = Globals::get('gTrunc.goods');

		///절삭기준 변경시작2022.04.06민트웹
		if($subscription==1){
			$tmp['trunc']['unitRound']=$subCfg['unitRound'];
			$tmp['trunc']['unitPrecision']=$subCfg['unitPrecision'];
		}
		///절삭기준 변경종료

        // 상품 할인을 사용하는 경우 상품 할인 계산
        if ($goodsDiscountFl === 'y') {
            switch ($goodsDiscountGroup) {
                case 'group':
                    $goodsDiscountGroupMemberInfoData = json_decode($goodsDiscountGroupMemberInfo, true);
                    $discountKey = array_flip($goodsDiscountGroupMemberInfoData['groupSno'])[$this->_memInfo['groupSno']];

                    if ($discountKey >= 0) {
                        if ($goodsDiscountGroupMemberInfoData['goodsDiscountUnit'][$discountKey] === 'percent') {
                            $discountPercent = $goodsDiscountGroupMemberInfoData['goodsDiscount'][$discountKey] / 100;

                            // 상품할인금액
                            $goodsDcPrice = gd_number_figure($tmp['discountByPrice'] * $discountPercent, $tmp['trunc']['unitPrecision'], $tmp['trunc']['unitRound']) * $goodsCnt;
                        } else {
                            // 상품금액보다 상품할인금액이 클 경우 상품금액으로 변경
                            if ($goodsDiscountGroupMemberInfoData['goodsDiscount'][$discountKey] > $goodsPrice['goodsPrice']) $goodsDiscountGroupMemberInfoData['goodsDiscount'][$discountKey] = $goodsPrice['goodsPrice'];
                            // 상품할인금액 (정액인 경우 해당 설정된 금액으로)
                            $goodsDcPrice = $goodsDiscountGroupMemberInfoData['goodsDiscount'][$discountKey] * $goodsCnt;
                        }
                    }
                    break;
                case 'member':
                default:
                    if ($goodsDiscountUnit === 'percent') {
                        $discountPercent = $goodsDiscount / 100;

                        // 상품할인금액
                        $goodsDcPrice = gd_number_figure($tmp['discountByPrice'] * $discountPercent, $tmp['trunc']['unitPrecision'], $tmp['trunc']['unitRound']) * $goodsCnt;
                    } else {
                        // 상품금액보다 상품할인금액이 클 경우 상품금액으로 변경
                        if ($goodsDiscount > $goodsPrice['goodsPrice']) $goodsDiscount = $goodsPrice['goodsPrice'];
                        // 상품할인금액 (정액인 경우 해당 설정된 금액으로)
                        $goodsDcPrice = $goodsDiscount * $goodsCnt;
                    }
                    if ($goodsDiscountGroup == 'member' && empty($this->members['memNo']) === true) {
                        $goodsDcPrice = 0;
                    }
                    break;
            }
        }

        return $goodsDcPrice;
    }

    /**
     * 장바구니 담기 (상품코드/옵션코드/상품수량/적용쿠폰 배열)
     * 상품을 장바구니에 담습니다.
     *
     * @param array $arrData 상품 정보 [mode, scmNo, cartMode, goodsNo[], optionSno[], goodsCnt[], couponApplyNo[]]
     *
     * @return array
     * @throws Exception
     */
    public function saveInfoCart($arrData, $tempCartPolicyDirectOrder = 'n', $channel = '')
    {
        parent::saveInfoCart($arrData, $tempCartPolicyDirectOrder, $channel);
        
        // 장바구니 상품 담았을 때 노티플리 이벤트 전송
        $member = \Session::get('member');
        if($member) {
            $sql = "
                SELECT goodsNo 
                FROM es_cart 
                WHERE memNo = {$member['memNo']}";
            $cartGoodsNo = $this->db->query_fetch($sql);
            $cartGoodsNo = array_column($cartGoodsNo, 'goodsNo');
            $cartGoodsNo = implode(',', $cartGoodsNo);

            $notifly = \App::load('Component\\Notifly\\Notifly');
            
            $memberInfo = [];
            $memberInfo['memId'] = $member['memId'];
            $memberInfo['$email'] = $member['email'];
            $memberInfo['$phone_number'] = $member['cellPhone'];
            $memberInfo['cartGoodsNo'] = $cartGoodsNo;
            $notifly->setUser($memberInfo);
        }
        // 장바구니 상품 담았을 때 노티플리 이벤트 전송
        
    }
}