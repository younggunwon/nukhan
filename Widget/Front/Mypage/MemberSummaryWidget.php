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
namespace Widget\Front\Mypage;

use Session;
/**
 *
 * @author Lee Seungjoo <slowj@godo.co.kr>
 */
class MemberSummaryWidget extends \Bundle\Widget\Front\Mypage\MemberSummaryWidget
{
	public function index()
    {
        parent::index();

		//루딕스-brown 회원아이디 가져오기
		$session = \App::getInstance('session');
		$memId = $session->get('member.memId');
		$this->setData('memId' , $memId);
    }
}