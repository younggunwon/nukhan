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

namespace Controller\Admin\Member;

use Component\Member\Group\Util as GroupUtil;
use Exception;
use Request;
use Component\Sms\Code;
use Framework\Utility\ComponentUtils;
use App;
/**
 * 회원의 마일리지 지급 설정 관리 페이지
 *
 * @author Ahn Jong-tae <qnibus@godo.co.kr>
 * @author Shin Donggyu <artherot@godo.co.kr>
 */
class TestController extends \Controller\Admin\Controller
{
    public function index()
    {
		$db = \App::load('DB');


		gd_debug($sql);
	}
}