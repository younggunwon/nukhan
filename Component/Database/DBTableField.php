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
 * @link      http://www.godo.co.kr
 */

namespace Component\Database;

use Component\Database\Traits\DBTableFieldSub; // 정기결제 테이블  

use Session;

/**
 * DB Table 기본 Field 클래스 - DB 테이블의 기본 필드를 설정한 클래스 이며, prepare query 생성시 필요한 기본 필드 정보임
 * @package Component\Database
 * @static  tableConfig
 */
class DBTableField extends \Bundle\Component\Database\DBTableField
{
	use DBTableFieldSub;

	public static function tableWowRecommend(){
		 // @formatter:off
        $arrField = [
            ['val' => 'sno', 'typ' => 'i', 'def' => null], // 고유번호
            ['val' => 'type', 'typ' => 's', 'def' => 'wowbio'], //추천종류
            ['val' => 'toMemNo', 'typ' => 'i', 'def' => null], // 추천받는 회원 코드
            ['val' => 'fromMemNo', 'typ' => 'i', 'def' => null], // 추천하는 회원 코드(회원가입시 업데이트)
            ['val' => 'rCode', 'typ' => 's', 'def' => null], // 추천고유코드
            ['val' => 'status', 'typ' => 'i', 'def' => 0], // 상태값 0 방문 1 회원가입 2 정기권구매 3 적립금 지급 99 기간만료 사용중지
            ['val' => 'request', 'typ' => 'i', 'def' => 0], // 상태값 0 요청안함 1 요청 2 지급완료 3 지급불가 
            ['val' => 'regDt', 'typ' => 's', 'def' => null], // 신청일
            
        ];
        // @formatter:on

        return $arrField;
	}


    public static function tableGoods($conf = null)
    {
		$arrField = parent::tableGoods($conf);
		$arrField[] = ["val" => "useSubscription", "typ" => "i", "def" => 0]; // 정기결제 사용여부
		$arrField[] = ["val" => "togetherGoodsNo", "typ" => "s", "def" => '']; // 함께구매상품
		$arrField[] = ["val" => "addViewFl", "typ" => "s", "def" => '']; // 추가상품보임여부-20220617민트웹
		//2023-08-30 루딕스-brown 상품별 추천인 
		$arrField[] = ["val" => "recomNoMileage", "typ" => "i", "def" => '']; //일반결제시 상품별 추천인 마일리지 지급
		$arrField[] = ["val" => "recomNoMileageUnit", "typ" => "s", "def" => ''];  //일반결제시 상품별 추천인 마일리지 단위
		$arrField[] = ["val" => "recomSubMileage", "typ" => "i", "def" => '']; //정기구독결제시 상품별 추천인 마일리지 지급
		$arrField[] = ["val" => "recomSubMileageUnit", "typ" => "s", "def" => '']; //정기구독결제시 상품별 추천인 마일리지 단위
		$arrField[] = ["val" => "recomMileageFl", "typ" => "s", "def" => 'c']; //추천인 마일리지 fl
		
		//2024-07-09 wg-brown 상품상세 텍스트 추가
		$arrField[] = ["val" => "goodsDisplayCustomHtmlFl", "typ" => "s", "def" => 'n'];  //노출상태
		$arrField[] = ["val" => "goodsViewTextCustomSameFl", "typ" => "s", "def" => 'y']; //pc, mo동일사용
		$arrField[] = ["val" => "goodsViewTextCustomHtml", "typ" => "s", "def" => null]; //pc상품상세 텍스트
		$arrField[] = ["val" => "goodsViewTextCustomHtmlMobile", "typ" => "s", "def" => null]; //mo상품상세 텍스트

		return $arrField;
	}
    public static function tableCart()
    {
		$arrField = parent::tableCart();
		$arrField[] = ['val' => 'cartType', 'typ' => 's', 'def' => 'cart'];
		$arrField[] = ['val' => 'isTogetherGoods', 'typ' => 'i', 'def' => 0]; // 함께 구매 상품 여부
		$arrField[] = ['val' => 'deliveryEa', 'typ' => 'i', 'def' => 0]; 
		$arrField[] = ['val' => 'isGift', 'typ' => 'i', 'def' => 0]; // 사은품 여부
		$arrField[] = ['val' => 'sPeriod', 'typ' => 's', 'def' => '']; // 배송주기
		return $arrField;
	}

	/**
	 * [COUPON] COUPON
	 *
	 * @author yg
	 * @return array COUPON
	 */
	public static function tableCoupon()
	{
		// @formatter:off
		$arrField = [['val' => 'couponNo', 'typ' => 'i', 'def' => ''], // 쿠폰고유번호
			['val' => 'couponKind', 'typ' => 's', 'def' => 'online'], // 종류–온라인쿠폰(‘online’),페이퍼쿠폰(‘offline’)
			['val' => 'couponType', 'typ' => 's', 'def' => 'y'], // 사용여부–사용(‘y’),정지(‘n’)
			['val' => 'couponUseType', 'typ' => 's', 'def' => 'product'], // 쿠폰유형–상품쿠폰(‘product’),주문쿠폰(‘order’),배송비쿠폰('delivery')
			['val' => 'couponSaveType', 'typ' => 's', 'def' => 'manual'], // 발급구분–회원다운로드(‘down’),자동발급(‘auto’),수동발급(‘manual’)
			['val' => 'couponNm', 'typ' => 's', 'def' => ''], // 쿠폰명
			['val' => 'couponDescribed', 'typ' => 's', 'def' => ''], // 쿠폰설명
			['val' => 'couponUsePeriodType', 'typ' => 's', 'def' => 'period'], // 사용기간–기간(‘period’),일(‘day’)
			['val' => 'couponUsePeriodStartDate', 'typ' => 's', 'def' => ''], // 사용기간-시작
			['val' => 'couponUsePeriodEndDate', 'typ' => 's', 'def' => ''], // 사용기간-끝
			['val' => 'couponUsePeriodDay', 'typ' => 'i', 'def' => ''], // 사용가능일
			['val' => 'couponUseDateLimit', 'typ' => 's', 'def' => null], // 사용 종료일
			['val' => 'couponKindType', 'typ' => 's', 'def' => 'sale'], // 혜택구분–상품할인(‘sale’),마일리지적립(‘add’),배송비할인('delivery')
			['val' => 'couponDeviceType', 'typ' => 's', 'def' => 'all'], // 사용범위–PC+모바일(‘all’),PC(‘pc’),모바일(‘mobile’)
			['val' => 'couponBenefit', 'typ' => 's', 'def' => '0'], // 혜택금액(할인,적립)액–소수점 2자리 가능
			['val' => 'couponBenefitType', 'typ' => 's', 'def' => 'p'], // 혜택금액종류-정율%(‘percent’),정액-원(‘fix’)–금액은 $등 가능
			['val' => 'couponBenefitFixApply', 'typ' => 's', 'def' => 'one'], // 정액쿠폰수량별적용-수량별로적용(‘all’),기존대로 상품에적용(‘one’)
			['val' => 'couponMaxBenefitType', 'typ' => 's', 'def' => ''], // 최대혜택금액여부–사용(‘y’)
			['val' => 'couponMaxBenefit', 'typ' => 's', 'def' => ''], // 최대혜택금액–소수점2자리가능
			['val' => 'couponDisplayType', 'typ' => 's', 'def' => 'n'], // 상세노출기간종류–즉시(‘n’),예약(‘y’)
			['val' => 'couponDisplayStartDate', 'typ' => 's', 'def' => ''], // 노출기간-시작
			['val' => 'couponDisplayEndDate', 'typ' => 's', 'def' => ''], // 노출기간-끝
			['val' => 'couponImageType', 'typ' => 's', 'def' => 'basic'], // 이미지종류–기본(‘basic’),직접(‘self’)
			['val' => 'couponImage', 'typ' => 's', 'def' => ''], // 이미지–직접등록
			['val' => 'couponLimitSmsFl', 'typ' => 's', 'def' => 'n'], // 사용기간만료시 SMS발송
			['val' => 'couponUseAblePaymentType', 'typ' => 's', 'def' => 'all'], // 결제수단 사용제한 - 제한없음( 'all' ), 무통장만 사용가능 ( 'bank' )
			['val' => 'couponAmountType', 'typ' => 's', 'def' => 'n'], // 발급수량종류–무제한(‘n’), 제한(‘y’)
			['val' => 'couponAmount', 'typ' => 'i', 'def' => ''], // 발급수량
			['val' => 'couponSaveDuplicateType', 'typ' => 's', 'def' => 'n'], // 중복발급제한여부–안됨(‘n’),중복가능(‘y’)
			['val' => 'couponSaveDuplicateLimitType', 'typ' => 's', 'def' => ''], // 중복발급최대제한여부–사용(‘y’)
			['val' => 'couponSaveDuplicateLimit', 'typ' => 'i', 'def' => ''], // 중복발급최대개수
			['val' => 'couponApplyMemberGroup', 'typ' => 's', 'def' => null], // 발급가능회원등급
			['val' => 'couponApplyMemberGroupDisplayType', 'typ' => 's', 'def' => ''], // 발급가능회원등급만노출–사용(‘y’)
			['val' => 'couponApplyProductType', 'typ' => 's', 'def' => 'all'], // 쿠폰적용상품–전체(‘all’),공급사(‘provider’),카테고리(‘category’),브랜드(‘brand’),상품(‘goods’)
			['val' => 'couponApplyProvider', 'typ' => 's', 'def' => null], // 쿠폰적용공급사
			['val' => 'couponApplyCategory', 'typ' => 's', 'def' => null], // 쿠폰적용카테고리
			['val' => 'couponApplyBrand', 'typ' => 's', 'def' => null], // 쿠폰적용브랜드
			['val' => 'couponApplyGoods', 'typ' => 's', 'def' => null], // 쿠폰적용상품
			['val' => 'couponExceptProviderType', 'typ' => 's', 'def' => ''], // 쿠폰제외공급사여부-사용(‘y’)
			['val' => 'couponExceptProvider', 'typ' => 's', 'def' => null], // 쿠폰제외공급사
			['val' => 'couponExceptCategoryType', 'typ' => 's', 'def' => ''], // 쿠폰제외카테고리여부-사용(‘y’)
			['val' => 'couponExceptCategory', 'typ' => 's', 'def' => null], // 쿠폰제외카테고리
			['val' => 'couponExceptBrandType', 'typ' => 's', 'def' => ''], // 쿠폰제외브랜드여부-사용(‘y’)
			['val' => 'couponExceptBrand', 'typ' => 's', 'def' => null], // 쿠폰제외브랜드
			['val' => 'couponExceptGoodsType', 'typ' => 's', 'def' => ''], // 쿠폰제외상품여부-사용(‘y’)
			['val' => 'couponExceptGoods', 'typ' => 's', 'def' => null], // 쿠폰제외상품
			//            ['val' => 'couponApplyGoodsAmountType', 'typ' => 's', 'def' => 'y'], // 상품수량별 적용방식-수량상관없이쿠폰1개할인(‘n’),수량*쿠폰할인(‘y’)
			['val' => 'couponMinOrderPrice', 'typ' => 's', 'def' => ''], // 쿠폰적용의 최소상품구매금액제한–소수점2자리가능
			['val' => 'couponProductMinOrderType', 'typ' => 's', 'def' => 'product'], // 쿠폰적용의 최소상품구매금액제한 기준 - product(상품), order(주문)
			//            ['val' => 'couponApplyOrderPayType', 'typ' => 's', 'def' => 'all'], // 쿠폰적용 결제방법-전체(‘all’),무통장입금만(‘bank’)
			['val' => 'couponApplyDuplicateType', 'typ' => 's', 'def' => 'y'], // 쿠폰적용 여부-중복가능(‘y’),안됨(‘n’)
			//            ['val' => 'couponAutoRecoverType', 'typ' => 's', 'def' => 'y'], // 쿠폰자동복원 여부-가능(‘y’),안됨(‘n’)
			['val' => 'couponEventType', 'typ' => 's', 'def' => 'first'], // 자동발급쿠폰 종류-첫구매(‘first’),구매감사(‘order’),생일축하(‘birth’),회원가입(‘join’),출석체크(‘attend’),회원정보수정(‘memberModifyEvent’)
			['val' => 'couponEventOrderFirstType', 'typ' => 's', 'def' => ''], // 구매감사쿠폰과 첫구매쿠폰의 중복안함-(‘y’)
			['val' => 'couponEventOrderSmsType', 'typ' => 's', 'def' => ''], // 구매감사쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventFirstSmsType', 'typ' => 's', 'def' => ''], // 첫구매쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventBirthSmsType', 'typ' => 's', 'def' => ''], // 생일쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventMemberSmsType', 'typ' => 's', 'def' => ''], // 회원가입쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventAttendanceSmsType', 'typ' => 's', 'def' => ''], // 출석체크쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventMemberModifySmsType', 'typ' => 's', 'def' => 'n'], // 회원정보수정쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventWakeSmsType', 'typ' => 's', 'def' => 'n'], // 휴면회원해제쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponEventKakaoChannelSmsType', 'typ' => 's', 'def' => 'n'], // 카카오채널가입쿠폰SMS발송여부-함(‘y’)
			['val' => 'couponAuthType', 'typ' => 's', 'def' => 'n'], // 쿠폰인증번호타입-1개의인증번호사용(‘n’),회원별로다른인증번호사용('y')
			['val' => 'couponInsertAdminId', 'typ' => 's', 'def' => ''], // 쿠폰등록자-아이디
			['val' => 'managerNo', 'typ' => 'i', 'def' => 0], //관리자 키
			['val' => 'couponSaveCount', 'typ' => 'i', 'def' => '0'] // 쿠폰발급수
		];
		// @formatter:on

		return $arrField;
	}
	public static function tableOrderGoods()
    {
        $arrField = parent::tableOrderGoods();
		$arrField[] = ["val" => "recomMileagePayFl", "typ" => "s", "def" => "n"]; // 루딕스-brown 추천마일리지 지급 fl

        return $arrField;
    }

	public static function tableMileageEncashment()
    {
		$arrField = [
			['val' => 'memNo', 'typ' => 'i', 'def' => null], // 회원번호
            ['val' => 'applicant', 'typ' => 's', 'def' => null], 
			['val' => 'zonecode', 'typ' => 's', 'def' => null],
			['val' => 'zipcode', 'typ' => 's', 'def' => null], 
			['val' => 'address', 'typ' => 's', 'def' => null], 
			['val' => 'addressSub', 'typ' => 's', 'def' => null], 
			['val' => 'normalNum', 'typ' => 's', 'def' => null], 
			['val' => 'cellPhone', 'typ' => 's', 'def' => null], 
			['val' => 'bankNm', 'typ' => 's', 'def' => null], 
			['val' => 'accountNum', 'typ' => 's', 'def' => null], 
			['val' => 'reReNum', 'typ' => 's', 'def' => null], 
			['val' => 'encashmentPrice', 'typ' => 's', 'def' => null], 
			['val' => 'applyFl', 'typ' => 's', 'def' => 'w'], 
				
		];

		return $arrField;
	}

	public static function tableMemberMileageHistory()
	{
		$arrField = [
			['val' => 'memberMileageSno', 'typ' => 's', 'def' => null], 
            ['val' => 'beforeReasonCd', 'typ' => 's', 'def' => null], 
			['val' => 'beforeContents', 'typ' => 's', 'def' => null],
			['val' => 'afterReasonCd', 'typ' => 's', 'def' => null], 
			['val' => 'afterContents', 'typ' => 's', 'def' => null],
		];

		return $arrField;
	}
	
	/**
     * 상품옵션 등록 임시 테이블
     *
     * @static
     * @return array
     */
    public static function tableGoodsOptionTemp()
    {
        $arrField = parent::tableGoodsOptionTemp();
		$arrField[] = ['val' => 'optionNoRecomMileage', 'typ' => 'i', 'def' => '']; //옵션 일반구매시 추천인 적립금
		$arrField[] = ['val' => 'optionNoRecomMileageUnit', 'typ' => 's', 'def' =>	'']; //옵션 일반구매시 추천인 적립금 단위
		$arrField[] = ['val' => 'optionSubRecomMileage', 'typ' => 'i', 'def' => '']; //옵션 정기구독시 추천인 적립금
		$arrField[] = ['val' => 'optionSubRecomMileageUnit', 'typ' => 's', 'def' => '']; //옵션 정기구독시 추천인 적립금 단위
		return $arrField;
    }

	public static function tableGoodsOption()
    {
		$arrField = parent::tableGoodsOption();
		$arrField[] = ['val' => 'optionNoRecomMileage', 'typ' => 'i', 'def' => '']; //옵션 일반구매시 추천인 적립금
		$arrField[] = ['val' => 'optionNoRecomMileageUnit', 'typ' => 's', 'def' =>	'']; //옵션 일반구매시 추천인 적립금 단위
		$arrField[] = ['val' => 'optionSubRecomMileage', 'typ' => 'i', 'def' => '']; //옵션 정기구독시 추천인 적립금
		$arrField[] = ['val' => 'optionSubRecomMileageUnit', 'typ' => 's', 'def' => '']; //옵션 정기구독시 추천인 적립금 단위
		return $arrField;
	}
}