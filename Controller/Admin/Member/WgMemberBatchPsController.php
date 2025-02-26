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

use App;
use Component\Member\Group\Util as GroupUtil;
use Exception;
use Framework\Debug\Exception\LayerException;
use Logger;
use Message;
use Request;
use Framework\Debug\Exception\AlertRedirectException;

/**
 * Class 회원일괄 처리
 * @package Bundle\Controller\Admin\Member
 * @author  yjwee
 */
class WgMemberBatchPsController extends \Bundle\Controller\Admin\Controller
{
    public function index()
    {
        /**
         * @var  \Bundle\Component\Member\MemberAdmin $admin
         * @var  \Bundle\Component\Mileage\Mileage    $mileage
         * @var  \Bundle\Component\Deposit\Deposit    $deposit
         */
        $admin = App::load('\\Component\\Member\\MemberAdmin');
        $mileage = App::load('\\Component\\Mileage\\Mileage');
        $deposit = App::load('\\Component\\Deposit\\Deposit');
        try {
            $mode = Request::post()->get('mode');
            $post = Request::post()->toArray();
            $searchJson = Request::post()->get('searchJson');
            $memberNo = Request::post()->get("chk");
            $groupSno = Request::post()->get('newGroupSno');

            switch ($mode) {
				# 2022-09-26 wg-brown 현금화 승인
				case 'encashmentApprove':
					foreach($post['sno'] as $sno) {
						$mileage->approveEncashment($sno);
					}
					throw new LayerException(__('현금화가 승인되었습니다.'), null, null, 'parent');
					break;
				case 'encashmentReject':
					foreach($post['sno'] as $sno) {
						$mileage->rejectEncashment($sno);
					}
					throw new LayerException(__('현금화가 거절되었습니다.'), null, null, 'parent');
					break;
                default:
                    throw new Exception(__('요청을 처리할 페이지를 찾을 수 없습니다.') . ', ' . $mode, 404);
                    break;
            }
        } catch (\Throwable $e) {
            \Logger::error(__METHOD__ . ', ' . $e->getFile() . '[' . $e->getLine() . '], ' . $e->getMessage(), $e->getTrace());
            if (Request::isAjax()) {
                $this->json($this->exceptionToArray($e));
            } else {
                throw new LayerException($e->getMessage(), $e->getCode(), $e);
            }
        } 
    }
}
