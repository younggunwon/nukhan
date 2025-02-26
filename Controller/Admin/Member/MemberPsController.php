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
namespace Controller\Admin\Member;

use Component\Member\HackOut\HackOutService;
use Component\Member\Manager;
use Component\Member\MemberVO;
use Component\Member\Util\MemberUtil;
use Component\Policy\JoinItemPolicy;
use Component\Policy\MileagePolicy;
use Component\Storage\Storage;
use Exception;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\LayerException;
use App;
use Component\Mileage\MileageUtil;

/**
 * Class 회원 처리
 * @package Bundle\Controller\Admin\Member
 * @author  yjwee
 */
class MemberPsController extends \Bundle\Controller\Admin\Member\MemberPsController
{
	public function index()
    {
        $request = \App::getInstance('request');

        /**
         * @var \Bundle\Component\Member\Member      $memberService
         * @var \Bundle\Component\Member\MemberAdmin $memberAdminService
         * @var \Bundle\Component\Policy\Policy      $policy
         * @var \Bundle\Component\Admin\AdminLogDAO  $logAction
         */
        $memberService = \App::load('\\Component\\Member\\Member');
        $memberAdminService = \App::load('\\Component\\Member\\MemberAdmin');
        $policy = \App::load('\\Component\\Policy\\Policy');
        $logAction = \App::load('Component\\Admin\\AdminLogDAO');

        $requestPostParams = $request->post()->xss()->all();
        $requestAllParams = array_merge($request->get()->toArray(), $request->post()->toArray());

        try {
            if(($request->getReferer() == $request->getDomainUrl()) || empty($request->getReferer()) === true){
                \Logger::error(__METHOD__ .' Access without referer');
                throw new Exception(__("요청을 찾을 수 없습니다."));
            }

            switch ($requestAllParams['mode']) {
				case 'addRecomBlackList':
					try {
						$mileagePolicy = new MileagePolicy();
                        $mileagePolicy->addRecomBlackList($requestPostParams);
						$this->layer(__('저장이 완료되었습니다.'));
                    } catch (Exception $e) {
                        $item = ($e->getMessage() ? ' - ' . str_replace("\n", ' - ', $e->getMessage()) : '');
                        throw new LayerException(__('처리중에 오류가 발생하여 실패되었습니다.') . $item);
                    }
					break;
				case 'mileageExcelDown':
					try {
						$excel = \App::load('Component\\Excel\\ExcelGoodsConvert');
						$this->streamedDownload('마일리지 검색.xls');

						//리스트 가져오기
						$arrData = $request->get()->all();
						$memberAdmin = \App::load('Component\\Member\\MemberAdmin');
						$arrData['mode'] = gd_isset($arrData['mode'], 'all');
						$arrData['regDtPeriod'] = gd_isset($arrData['regDtPeriod'], '7');
						$arrData['listType'] = gd_isset($arrData['listType'], 'member');
						$getData = $memberAdmin->getMemberMileageExcelPageList($arrData);
						
						$getData['data'] = MileageUtil::changeDeleteScheduleDt($getData['data'], true);
						//리스트 가져오기

                        $excel->memberMileageExcelDown($getData);
						
                    } catch (Exception $e) {
                        $item = ($e->getMessage() ? ' - ' . str_replace("\n", ' - ', $e->getMessage()) : '');
                        throw new LayerException(__('처리중에 오류가 발생하여 실패되었습니다.') . $item);
                    }
					break;
				case 'recom_mileage':
					try {
                        $order = \App::load(\Component\Order\Order::class);
                        $order->recomMileageSchedule();
						//test
						//$db = \App::load('DB');
						//gd_debug($order->excuteSql);
						//$sql = "INSERT INTO wg_apiLog(apiType, requestData, responseData, regDt) VALUES('recomMileage', 'end', '".json_encode($order->excuteSql)."', now())";
						//$db->query($sql);
                        $this->layer(__('추천인 마일리지 지급테스트 완료.'));
                    } catch (Exception $e) {
                        $item = ($e->getMessage() ? ' - ' . str_replace("\n", ' - ', $e->getMessage()) : '');
                        throw new LayerException(__('처리중에 오류가 발생하여 실패되었습니다.') . $item);
                    }
                    break;
                // --- 회원의 마일리지 지급 설정
                case 'recom_mileage_give':
                    try {
                        $mileagePolicy = new MileagePolicy();
                        $mileagePolicy->saveRecomMileageGive($requestPostParams);
                        $this->layer(__('저장이 완료되었습니다.'));
                    } catch (Exception $e) {
                        $item = ($e->getMessage() ? ' - ' . str_replace("\n", ' - ', $e->getMessage()) : '');
                        throw new LayerException(__('처리중에 오류가 발생하여 실패되었습니다.') . $item);
                    }
                    break;
					default:
						parent::index();
					break;


            }
        } catch (Exception $e) {
            if ($request->isAjax() === true) {
                switch ($e->getCode()) {
                    case self::ERR_CODE_REQUIRE_BIRTHDAY :
                        $arrayErr = array_merge($this->exceptionToArray($e), ['isReload' => true, 'isClose' => false, 'title' => __('경고')]);
                        $this->json($arrayErr);
                        break;
                    default :
                        $this->json($this->exceptionToArray($e));
                        break;
                }
            } else {
                switch ($e->getCode()) {
                    case self::ERR_CODE_MEMBER_AUTH :
                        $this->js('parent.dialog_alert("' . addslashes(__($e->getMessage())) . '","' . __('경고') . '" ,{isReload:true});');
                        break;
                    case self::ERR_CODE_JOIN_EVENT :
                        throw new AlertOnlyException($e->getMessage());
                        break;
                    case self::ERR_CODE_MEMBER_AUTH_BIRTHDAY:
                        $this->js('parent.dialog_alert("' . addslashes(__($e->getMessage())) . '","' . __('경고') . '" ,{isReload:false});');
                        break;
                    default :
                        throw new LayerException($e->getMessage(), $e->getCode(), $e);
                        break;
                }
            }
        }
    }
}