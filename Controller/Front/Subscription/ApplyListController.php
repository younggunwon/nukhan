<?php

namespace Controller\Front\Subscription;

use App;
use Request;

/**
* 정기결제 신청 관리 
* 
* @author webnmobile
*/
class ApplyListController extends \Controller\Front\Controller
{
	public function index()
	{
		$this->addCss(["mypage/mypage.css", "musign/mypage.css"]);
		
		if (!gd_is_login())
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		
		
		$get = Request::get()->all();
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$result = $subscription->getApplyList(null, [], true, $get['page']);

		//gd_debug($result);
		$this->setData($result);
		$this->setData("dateOpt", $get['dateOpt']);
		$this->setData("wDate", $get['wDate']);

		$subscriptionCfg=$subscription->getCfg();

		$this->setData("min_order",$subscriptionCfg['min_order']);
		
		$locale = \Globals::get('gGlobal.locale');
         // 날짜 픽커를 위한 스크립트와 스타일 호출
         $this->addCss([
			'plugins/bootstrap-datetimepicker.min.css',
            'plugins/bootstrap-datetimepicker-standalone.css',
         ]);
         
		 $this->addScript([
			 'moment/moment.js',
             'moment/locale/' . $locale . '.js',
             'jquery/datetimepicker/bootstrap-datetimepicker.min.js',
			 'wm/subscription/mypage.js',
         ]);
	}
}