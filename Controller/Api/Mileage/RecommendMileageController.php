<?php

namespace Controller\Api\Mileage;

use Request;
use Session;
use App;
use DB;

class RecommendMileageController extends \Controller\Api\Controller
{
	public function index()
	{
		set_time_limit(0);
		//루딕스-brown 추천인 마일리지 지급
		$order = \App::load('\\Component\\Order\\Order');
		$order->recomMileageSchedule();

		gd_debug($order->executeSql);
		$db = \App::load('DB');
		$sql = "INSERT INTO wg_apiLog(apiType, requestData, responseData, regDt) VALUES('recomMileage', 'end', '".implode('||', $order->executeSql)."', now())";
		$db->query($sql);
		
		exit;
	}
}