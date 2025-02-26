<?php

namespace Controller\Front\Subscription;

use App;
use Request;
use Exception;

/**
* 정기결제 ajax 처리 관련 
*
* @author webnmobile
*/
class AjaxController extends \Controller\Front\Controller
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			
			switch ($in['mode']) {
				/* 정기배송 상세정보 HTML */
				case "getScheduleHtml" : 
					$this->setData($in);
					$this->getView()->setDefine("tpl", "subscription/schedule_info.html");
					break;
			}
		} catch (Exception $e) {
			$this->json([
				'error' => 1,
				'message' => $e->getMessage(),
			]);
		}
		
		if ($in['mode'] != 'getScheduleHtml') {
			exit;
		}
	}
}