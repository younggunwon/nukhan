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

namespace Controller\Admin\Share;

/**
 * Class LayerMemberMileageHistoryController
 * @package Bundle\Controller\Admin\Share
 * @author  yjwee
 */
class LayerMemberMileageHistoryController extends \Bundle\Controller\Admin\Controller
{
    /**
     * @inheritdoc
     *
     */
    public function index()
    {
        $this->getView()->setDefine('layout', 'layout_layer.php');
		$getValue = \Request::get()->all();

		$history = \App::load('Component\\Member\\History');
		$mileageHistory = $history->getMemberMileageHistory($getValue['sno']); 
		$this->setData('mileageHistory', $mileageHistory);
	}
}