<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall to newer
 * versions in the future.
 *
 * @copyright ⓒ 2022, NHN COMMERCE Corp.
 */
namespace Component\Excel;

use Component\Mileage\MileageUtil;
use Component\Mileage\Mileage;
use Component\Mileage\MileageDAO;
use Vendor\Spreadsheet\Excel\Reader as SpreadsheetExcelReader;

/**
 * Class ExcelGoodsConvert
 * @package Bundle\Component\Excel
 * @author  yjwee <yeongjong.wee@godo.co.kr>
 * @method doGoodsnoCheckWrapper($goodsNo = null)
 * @method doGoodsNoInsertWrapper()
 * @method setGoodsDataWrapper($goodsData, $goodsNo, $goodsInsert, $tableField, $tableName, $strOrderBy = null)
 * @method setGoodsLinkCategoryWrapper($linkData, $goodsNo, $goodsInsert)
 * @method setGoodsLinkBrandWrapper($brandData, $goodsNo, $goodsInsert)
 */
class ExcelGoodsConvert extends \Bundle\Component\Excel\ExcelGoodsConvert
{

	public function memberMileageExcelDown($excelList)
	{

		header( "Content-type: application/vnd.ms-excel" );   
		header( "Content-type: application/vnd.ms-excel; charset=utf-8");  
		header( "Content-Disposition: attachment; filename = 카테고리코.xls" );   
		header( "Content-Description: PHP4 Generated Data" );

		$memberMasking = \App::load('Component\\Member\\MemberMasking');
		$mileageReasons = MileageUtil::getReasons();
        if (empty($mileageReasons[Mileage::REASON_CODE_GROUP . Mileage::REASON_CODE_REGISTER_RECOMMEND]) === false) {
            $mileageReasons[Mileage::REASON_CODE_GROUP . Mileage::REASON_CODE_REGISTER_RECOMMEND] = '추천인 등록';
        }

		// 테이블 상단 만들기
		$EXCEL_STR = "  
		<table border='1'>
		<tr> 
			<td>아이디</td>  
			<td>이름</td>  
		   <td>등급</td>  
		   <td>지급액</td>
		   <td>차감액</td>  
		   <td>지급/차감일</td>  
		   <td>잔액</td>
		   <td>소멸예정일</td>
		   <td>처리자</td>
		   <td>사유</td>
		</tr>";
		foreach($excelList['data'] as $key => $val) {			
			$isMinusSign = strpos($val['mileage'], '-') === 0;

			if($val['reasonCd'] == '01005001' && $val['contents'] == '상품구매 시 마일리지 사용 (취소복원)') {
				$reasonContents = $val['contents'];
			}else{
				$reasonContents = gd_isset($mileageReasons[$val['reasonCd']]);
			}
			switch ($val['reasonCd']) {
				case '01005001':
				case '01005002':
				case '01005003':
				case '01005004':
				case '01005008':
				case '01005501':
				case '01005504':
				case '01005505':
				case '01005506':
				case '01005507':
					if ($val['reasonCd'] == '01005504' || $val['reasonCd'] == '01005505') {
						$reasonContents = $val['contents'];
					}
					if (empty($val['handleCd']) === false) {
						$reasonContents .= '<br/>(주문번호 : ';
						$reasonContents .= '<a href="#" class="js-link-order" data-order-no="' . $val['handleCd'] . '">' . $val['handleCd'] . '</a>)';
					}
					break;
				case '01005006':
				case '01005007':
					$reasonContents = $val['contents'];
					if (empty($val['handleCd']) === false) {
						$reasonContents .= '<br/>(' . $val['handleCd'] . ')';
					}
					break;
				case '01005009':
				case '01005010':
					if($val['handleCd'] == 'plusReview'){
						$reasonContents = $val['contents'];
					}else{
						if (empty($val['handleCd']) === false) {
							$reasonContents .= '<br/>(' . $boards[$val['handleCd']] . ')';
						}
					}
					break;
				case '01005011':
				case '01005502':
					$reasonContents = $val['contents'];
					if($val['reasonCd'] == '01005502' && empty($val['modifyEventNm']) === false){
						$reasonContents .= '<div>(' . $val['modifyEventNm'] . ')</div>';
					}
					break;
				case '01005005':
					$reasonContents = $val['contents'];
					if ($val['handleNo'] == 'smsFl') {
						$reasonContents .= '<br/>Sms 수신동의';
					} elseif ($val['handleNo'] == 'mailingFl') {
						$reasonContents .= '<br/>이메일 수신동의';
					}
					break;
			}

			// 탈퇴 회원 소멸 마일리지 내역추가
			if ($val['hackOutFl'] === 'y') {
				$reasonContents = '탈퇴 회원 마일리지 소멸';
			}
			
			if (!$isMinusSign){
				$deleteScheduleDt = gd_date_format('Y-m-d', $val['deleteScheduleDt'])."<br/>".gd_date_format('H:i', $val['deleteScheduleDt']);
			}else {
				$deleteScheduleDt = '';
			}

			$plus = $isMinusSign ? '-' : '(+)' . gd_money_format($val['mileage']) . gd_display_mileage_unit();
			$Minus = $isMinusSign ? '(-)' . gd_money_format(substr($val['mileage'], 1)) . gd_display_mileage_unit() : '-';
			$EXCEL_STR .= "  
				<tr>  
					<td>".$memberMasking->masking('member','id',$val['memId'])."</td>  
					<td>".$memberMasking->masking('member','name',$val['memNm'])."</td>  
					<td>".gd_isset($excelList['groups'][$val['groupSno']])."</td>  
					<td>".$plus."</td>  
					<td>".$Minus."</td>  
					<td>".gd_date_format('Y-m-d', $val['regDt'])."</td>  
					<td>".gd_money_format($val['afterMileage']).gd_display_mileage_unit()."</td>  
					<td>".$deleteScheduleDt."</td>  
					<td>".$val['managerId'].$val['deleteText']."</td>  
					<td>".$reasonContents."</td>  
			   </tr>  
			   "; 
		}
		$EXCEL_STR .= "</table>";  
		echo "<meta content=\"application/vnd.ms-excel; charset=UTF-8\" name=\"Content-type\"> ";  
		echo $EXCEL_STR;  
		
	}

}