<?php

namespace Controller\Mobile\Subscription;

use App;
use Request;
use Exception;
use Framework\Utility\StringUtils;
/**
* 정기배송 신청상세 
*
* @author webnmobile
*/
class ApplyViewController extends \Controller\Mobile\Controller
{
	public function index()
	{
		
		if (!gd_is_login())
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		
		try {
			$idx = Request::get()->get("idx");
			if (!$idx)
				throw new Exception("잘못된 접근입니다.");
			
			
			$subscription = App::load(\Component\Subscription\Subscription::class);
			$info = $subscription->getApplyInfo($idx);

			
			if (!$info)
				throw new Exception("신청내역이 존재하지 않습니다.");

			//gd_debug($info);
			
			$this->setData($info);
			

			$db=\App::load(\DB::class);			
			//$r=$db->fetch("select status from wm_subSchedules where idxApply='$idx' order by idx DESC limit 0,1");
			$sql = "SELECT COUNT(a.idx) as cnt FROM wm_subApplyInfo a INNER JOIN wm_subSchedules b ON a.idx=b.idxApply WHERE idxCard = ? and a.status='ready' and a.autoExtend='1'";
			$r = $db->query_fetch($sql, ["i", $idx], false);

			if(empty($r['cnt'])){
				$delivery_count=-1;
			}
			$this->setData("delivery_count",$delivery_count);


		
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
            foreach ($wDate as $searchDateKey => $searchDateValue) {
                $wDate[$searchDateKey] = StringUtils::xssClean($searchDateValue);
            }

			$this->setData('selectDate', $selectDate);
			
			$layout = [
				'current_page' => 'y',
				'page_name' => '상세 내역',
			];
			$this->setData("layout", $layout);
			
			$this->addScript([
				'subscription/mypage.js',
			]);
		} catch (Exception $e) {
			return $this->js("alert('".$e->getMessage() . "');window.history.back();");
		}
		
	}
}