<?php
namespace Controller\Front\Mypage;

use Session;
use Cookie;
use Component\Wowbio\Recommend;
use Component\Agreement\BuyerInform;
use Component\Agreement\BuyerInformCode;

/**
 * Class 페이백 신청
 * @package Bundle\Controller\Front\Mypage
 */
class EncashmentController extends \Bundle\Controller\Front\Controller
{
    public function index()
    {
		if (!gd_is_login()) {
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		}

		$encashment = \App::load('\\Component\\Member\\Encashment');
		$bank = $encashment->bankCode;
		$this->setData('bank', $bank);

		#2024-01-17 루딕스-brown 개인정보동의 가져오기
		$inform = new BuyerInform();
        $paybackPrivateApproval = $inform->getInformData('001012');
		$this->setData('paybackPrivateApproval', $paybackPrivateApproval);
		
		#2024-01-17 루딕스-brown 전에 신청했던 페이백 정보가져오기
		$session = \App::getInstance('session');
		$mileage = \App::load('\\Component\\Mileage\\Mileage');
		$historyData = $mileage->getHistoryPayback($session->get('member.memNo'));
		$this->setData('historyData', $historyData);
	}
}
