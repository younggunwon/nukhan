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

namespace Controller\Admin\Share;

use Component\Excel\ExcelForm;
use Framework\Utility\ArrayUtils;
use Framework\Security\Token;

/**
 * 레이어 엑셀 다운로드
 * @package Bundle\Controller\Admin\Share
 * @author  atomyang
 */
class LayerEncashmentRejectController extends \Bundle\Controller\Admin\Controller
{


    public function index()
    {
        $request = \App::getInstance('request');
        $session = \App::getInstance('session');
        $getValue = $request->get()->toArray();

        try {
			//선택한 sno 가져오기
			$sno = $request->get()->all()['sno'];
            $this->setData('sno', $sno);
			$this->getView()->setDefine('layout', 'layout_layer.php');

            // 공급사와 동일한 페이지 사용
            $this->getView()->setPageName('share/layer_encashment_reject.php');
        } catch (\Exception $e) {
            throw $e;
        }

    }
}
