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
namespace Controller\Front\Mypage;
use Exception;
use Request;
/**
 * Class 프론트-마이페이지 컨트롤러
 * @package Bundle\Controller\Front\Mypage
 * @author  yjwee
 */
class MyPagePsController extends \Bundle\Controller\Front\Mypage\MyPagePsController
{
    public function index()
    {

			//2022.09.02민트웹 회원정보 변경시 정기결제 신청내역 주소도 변경시작
			$db = \App::load(\DB::class);
			$in = \Request::request()->all();

			$memNo=\Session::get("member.memNo");

			if($in['chk']==1){
				
				$receiverCellPhone=$in['cellPhone'];
				$receiverZipcode=$in['zipcode'];
				$receiverZonecode=$in['zonecode'];
				$receiverAddress=$in['address'];
				$receiverAddressSub=$in['addressSub'];

				$preSQL="select * from wm_subDeliveryInfo where memNo='$memNo'";
				$preRow=$db->fetch($preSQL);

				$strSQL="update wm_subSchedules set receiverCellPhone='$receiverCellPhone',receiverZipcode='$receiverZipcode',receiverZonecode='$receiverZonecode',receiverAddress='$receiverAddress',receiverAddressSub='$receiverAddressSub' where memNo='$memNo'";
				gd_debug($strSQL);
				$db->query($strSQL);

				$strSQL="update wm_subDeliveryInfo set receiverCellPhone='$receiverCellPhone',receiverZipcode='$receiverZipcode',receiverZonecode='$receiverZonecode',receiverAddress='$receiverAddress',receiverAddressSub='$receiverAddressSub' where memNo='$memNo'";
				$db->query($strSQL);

				$preAddress="[".$preRow['receiverZonecode']."]".$preRow['receiverAddress']."/".$preRow['receiverAddressSub'];
				$newAddress="[".$receiverZonecode."]".$receiverAddress."/".$receiverAddressSub;

				
				$sql="select idxApply from wm_subSchedules where memNo='$memNo' group by idxApply";
				$rows=$db->query_fetch($sql);
				
				
				foreach($rows as $k =>$t){
					$logSQL="insert into wm_subAddressChangeLog set memNo='$memNo',managerId='',prevAddress='{$preAddress}',newAddress='{$newAddress}',idxApply='{$t['idxApply']}',regDt=sysdate(),modDt=sysdate()";

					$db->query($logSQL);
				}
			
			}
			//2022.09.02민트웹 회원정보 변경시 정기결제 신청내역 주소도 변경종료
			
			parent::index();
		

		
	}
}