<?php

namespace Component\Subscription;

use App;
use Request;
use Globals;

/**
* 수기주문 전문 정기결제 장바구니 class
* 
* @package Component\SubscriptionNew
* @author webnmobile
*/
class CartSubAdmin extends \Bundle\Component\Cart\CartAdmin
{
	/**
     * 생성자
     *
     * @param mixed $memberNo 회원번호
     * @param boolean $useRealCart 수기주문 시 회원장바구니 상품 적용  ---> admin 에서의 db_cart 사용을 위한 flag 값
     */
    public function __construct($memberNo = 0, $useRealCart=false, $mallSno=null)
    {
		parent::__construct($memberNo, $useRealCart, $mallSno);
		
		// !중요! 장바구니 구현시 사용되는 테이블 명
        if($useRealCart === true){
            //관리모드 수기주문 - 회원 장바구니 추가시 노출되는 상품을 위해 wm_subCart 사용
            $this->tableName = "wm_subCart";

            //실제 테이블 사용시
            $this->useRealCart = true;
        }
        else {
            //상품추가시엔 wm_subCartAdmin 사용
            $this->tableName = "wm_subCartAdmin";
        }
		
		 // 해외배송 기본 정책
        if($mallSno !== null && $mallSno > DEFAULT_MALL_NUMBER){
            $this->isAdminGlobal = true;

            $overseasDelivery = new OverseasDelivery();
            $this->overseasDeliveryPolicy = $overseasDelivery->getBasicData($mallSno, 'mallSno');
        }


        // 수기주문 체크
        $this->isWrite = true;

        // 정해진 회원번호로 처리 (null인 경우 로그인한 회원의 번호로 처리한다)
        if ($memberNo > 0) {
            $this->isLogin = true;

            // 회원 DB 추출
            $member = App::load('\\Component\\Member\\Member');
            $memberInfo = $member->getMember($memberNo, 'memNo');

            // 추출한 데이터로 값 설정
            $this->members['memNo'] = $memberInfo['memNo'];
            $this->members['adultFl'] = $memberInfo['adultFl'];
            $this->members['groupSno'] = $memberInfo['groupSno'];

            // 회원설정
            $this->_memInfo = $member->getMemberInfo($memberNo);

            $this->deliveryFreeByMileage = $this->_memInfo['deliveryFree'];
        } else {
            $this->isLogin = false;

            $this->members = [
                'memNo'    => 0,
                'adultFl'  => 'n',
                'groupSno' => 0,
            ];

            // 회원설정
            $this->_memInfo = false;

            $this->deliveryFreeByMileage = '';
        }
	}
	
	/**
     * 장바구니 담기 (단품)
     * 한개의 상품을 장바구니에 담습니다.
     *
     * @param array $arrData 상품 정보 [goodsNo, optionSno, goodsCnt, addGoodsNo, addGoodsCnt, optionText,
     *                       deliveryCollectFl, memberCouponNo, scmNo, cartMode]
     *
     * @return integer 중복상품 sno
     * @throws Exception
     */
	public function saveGoodsToCart($arrData)
    {
		$arrData['cartType'] = 'cartSubAdmin';
		
		return parent::saveGoodsToCart($arrData);
	}
	
	/**
     * 장바구니 상품 정보 - 상품별 상품 할인 설정
     * 상품금액의 총합이 아닌 순수 상품판매 단가의 할인율을 먼저 구한 뒤 반환할때 상품수량 곱함
     *
     * @param string $goodsDiscountFl 상품 할인 여부
     * @param int $goodsDiscount 상품 할인 금액 or Percent
     * @param string $goodsDiscountUnit Percent or Price
     * @param int $goodsCnt 상품 수량
     * @param array $goodsPrice 상품 가격 정보
     * @param string $fixedGoodsDiscount 상품할인금액기준
     * @param string $goodsDiscountGroup 상품할인대상
     * @param json $goodsDiscountGroupMemberInfo 상품할인 회원 정보
     *
     * @return int 상품할인금액
     */
    protected function getGoodsDcData($goodsDiscountFl, $goodsDiscount, $goodsDiscountUnit, $goodsCnt, $goodsPrice, $fixedGoodsDiscount = null, $goodsDiscountGroup = null, $goodsDiscountGroupMemberInfo = null)
    {

		///절삭기준 시작 2022.04.06민트웹
		$server=\Request::server()->toArray();
		$db=\App::load(\DB::class);

		$subscription=0;

		//gd_debug($this->tableName);

		if(preg_match('/goodsNo/',$server['HTTP_REFERER']) || preg_match('/subscription/',$server['HTTP_REFERER'])){
			
			$params = parse_url($server['HTTP_REFERER']);
			parse_str($params['query'],$param);
			
			$goodsNo=$param['goodsNo'];

			$strSQL ="select useSubscription from ".DB_GOODS." where goodsNo=?";
			$rows = $db->query_fetch($strSQL,['i',$goodsNo],false);

			if($rows['useSubscription']==1){
				$subscription=1;
			}
		}

		if($this->tableName=="wm_subCart" || $this->tableName=="wm_subCartAdmin"){
			$subscription=1;
		}

		if($subscription==1){
			$subObj = new \Component\Subscription\Subscription();
			$subCfg = $subObj->getCfg();
			
		}
		///절삭기준 종료

        // 상품 할인 금액
        $goodsDcPrice = $goodsPriceTmp = 0;
        $fixedGoodsDiscountData = explode(STR_DIVISION, $fixedGoodsDiscount);
        $goodsPriceTmp = $goodsPrice['goodsPriceSum'];

        if (in_array('option', $fixedGoodsDiscountData) === true) $goodsPriceTmp +=$goodsPrice['optionPriceSum'];
        if (in_array('text', $fixedGoodsDiscountData) === true) $goodsPriceTmp +=$goodsPrice['optionTextPriceSum'];
		if ($goodsPrice['addGoodsPriceSum']) $goodsPriceTmp += $goodsPrice['addGoodsPriceSum']; // 추가 상품 할인 금액 반영 
		
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

		//gd_debug($subCfg);
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
	
	public function setMergeCart($memNo)
    {
		if ($this->notMerge)
			return;
		
		return parent::setMergeCart($memNo);
	}
}