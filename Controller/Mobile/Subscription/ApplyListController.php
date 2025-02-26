<?php

namespace Controller\Mobile\Subscription;

use App;
use Request;
use Framework\Utility\StringUtils;

/**
* 정기결제 신청 관리 
* 
* @author webnmobile
*/
class ApplyListController extends \Controller\Mobile\Controller
{
	public function index()
	{
		$this->addCss(["mypage/mypage.css"]);
		
		if (!gd_is_login())
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		
		
		$get = Request::get()->all();
		
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$result = $subscription->getApplyList(null, [], true, $get['page']);
		$this->setData($result);

		$subscriptionCfg=$subscription->getCfg();
		$this->setData("min_order",$subscriptionCfg['min_order']);

		$layout = [
			'current_page' => 'y',
			'page_name' => '정기주문 신청목록',
		];
		$this->setData("layout", $layout);
		
		// 기간 조회
		$searchDate = [
                '1'   => __('오늘'),
                '7'   => __('최근 %d일', 7),
                '15'  => __('최근 %d일', 15),
                '30'  => __('최근 %d개월', 1),
                '90'  => __('최근 %d개월', 3),
                '180' => __('최근 %d개월', 6),
                '365' => __('최근 %d년', 1),
           ];
        $this->setData('searchDate', $searchDate);		
		
		 if (is_numeric(Request::get()->get('searchPeriod')) === true && Request::get()->get('searchPeriod') >= 0) {
                $selectDate = Request::get()->get('searchPeriod');
            } else {
                $selectDate = 7;
            }
            $startDate = date('Y-m-d', strtotime("-$selectDate days"));
            $endDate = date('Y-m-d', strtotime("now"));
            $wDate = Request::get()->get(
                'wDate',
                [
                    $startDate,
                    $endDate,
                ]
            );

		//	gd_debug($wDate);
            foreach ($wDate as $searchDateKey => $searchDateValue) {
                $wDate[$searchDateKey] = StringUtils::xssClean($searchDateValue);
            }

         $this->setData('selectDate', $selectDate);
		 $this->setData("isAjax", Request::get()->get("isAjax"));
	}
}