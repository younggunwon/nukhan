<?php
namespace Controller\Front\Subscription;

use App;
use Request;

use Component\Subscription\KakaoPay;

class ApiAddController extends \Controller\Front\Controller
{

	public function index()
	{
		
		$in = Request::request()->all();

		$pass=$in['pass'];
		$regiserType=$in['regiserType'];

		if(empty($regiserType))
			$regiserType="order";


		if($in['method']=="kakao"){
			$kakao = new KakaoPay();

			
			$return_data = $kakao->KakaoRegister($pass,$regiserType);
			echo $return_data;
		}
		
		exit();
	
	}

}