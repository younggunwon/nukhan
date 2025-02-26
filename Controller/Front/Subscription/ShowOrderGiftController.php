<?php

namespace Controller\Front\Subscription;

use App;
use Request;

/**
* 정기배송 사은품  
* 
* @author webnmobile
*/
class ShowOrderGiftController extends \Controller\Front\Controller
{
	public function index()
	{
		$goodsNo = Request::get()->get("goodsNo");
		if (!$goodsNo) {
			$this->js("alert('잘못된 접근입니다.');parent.wmLayer.close();");
		}
		
		$schedule = App::load(\Component\Subscription\Schedule::class);
		$gnos = $goodsNo?explode(",", $goodsNo):[];
		$list = $schedule->getGifts($gnos, 0, true);

		$this->setData("list", $list);
		
		$goodsCnt = Request::get()->get("goodsCnt");
		$this->setData("goodsCnt", $goodsCnt);
		
		$from = Request::get()->get("from");
		$this->setData("from", $from);
	}
}