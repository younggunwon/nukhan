<?php

namespace Controller\Admin\Policy;

use App;

/**
* 셀프취소 설정 
*
* @author webnmobile
*/ 
class SelfCancelController extends \Controller\Admin\Controller 
{
	public function index()
	{
		$this->callMenu("policy", "order", "self_cancel");
		
		$selfCancel = App::load(\Component\SelfCancel\SelfCancel::class);
		$conf = $selfCancel->getCfg();
		$this->setData($conf);
	}
}