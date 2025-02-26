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
namespace Controller\Admin\Policy;

use Component\Agreement\BuyerInform;
use Component\Agreement\BuyerInformCode;
use Exception;
use Framework\Debug\Exception\LayerException;
use Request;
/**
 * Class 약관/개인정보설정 처리 페이지
 * @package Bundle\Controller\Admin\Policy
 * @author  yjwee
 */
class BaseAgreementWithPrivatePsController extends \Bundle\Controller\Admin\Policy\BaseAgreementWithPrivatePsController
{
	public function index()
    {
        try {
            $buyerInform = new BuyerInform();
            /** @var \Bundle\Component\Policy\Policy $policy */
            $policy = \App::load('\\Component\\Policy\\Policy');
            switch (Request::post()->get('mode', '')) {
                // --- 개인정보수집 동의항목 설정
                case 'privateItem':
                    $removeSnoArray = json_decode(Request::post()->get('removeSno', []));
                    $mallSno = gd_isset(Request::post()->get('mallSno'), 1);
                    foreach ($removeSnoArray as $key => $val) {
                        $buyerInform->deletePrivateItem($val, $mallSno);
                    };
                    $buyerInform->saveInformData(BuyerInformCode::PRIVATE_APPROVAL, Request::post()->get('privateApproval'), $mallSno);
                    $buyerInform->saveInformData(BuyerInformCode::PRIVATE_GUEST_ORDER, Request::post()->get('privateGuestOrder'), $mallSno);
                    $buyerInform->saveInformData(BuyerInformCode::PRIVATE_GUEST_BOARD_WRITE, Request::post()->get('privateGuestBoardWrite'), $mallSno);
                    $buyerInform->saveInformData(BuyerInformCode::PRIVATE_GUEST_COMMENT_WRITE, Request::post()->get('privateGuestCommentWrite'), $mallSno);
                    $buyerInform->saveInformData(BuyerInformCode::PRIVATE_PROVIDER, Request::post()->get('privateProvider'), $mallSno);
                    
					#루딕스-brown 페이백 개인정보동의 내용저장
					$buyerInform->saveInformData('001012', Request::post()->get('paybackPrivateApproval'), $mallSno);
                    #루딕스-brown 페이백 개인정보동의 내용저장
					
					$buyerInform->savePrivateApprovalOption(Request::post()->all());
                    $buyerInform->savePrivateConsign(Request::post()->all());
                    $buyerInform->savePrivateOffer(Request::post()->all());
                    $buyerInform->saveInformData(BuyerInformCode::PRIVATE_MARKETING, Request::post()->get('privateMarketingWriteWrite'));
                    $this->json(
                        [
                            'message' => __('저장이 완료되었습니다.'),
                            'code'    => 200,
                        ]
                    );
                    break;


                default:
					parent::index();
				break;
            }
        } catch (Exception $e) {
            if (Request::isAjax()) {
                $this->json(['error' => $this->exceptionToArray($e)]);
            } else {
                throw new LayerException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }
}