<?php
namespace Controller\Front\Subscription\Kakao;

use Component\Subscription\KakaoPay;
use Request;
use App;

class OrderPsController extends \Controller\Front\Controller
{
	protected $db;
	public function pre()
	{
		if(!is_object($this->db))
			$this->db=App::load(\DB::class);	
	}
	
	public function index()
	{
		$memNo=\Session::get("member.memNo");

		$in = \Request::request()->all();

		$pg_token = $in['pg_token'];
		$mode = $in['mode'];

		$orderNo = $in['orderNo'];
		
		$sql="select * from wm_subscription_tmp where memNo=? and orderNo=?";
		$row = $this->db->query_fetch($sql,['ii',$memNo,$orderNo],false);

		if($mode=="success"){

			if(!empty($pg_token)){//Ä«Ä«¿À

				$kakao = new KakaoPay();
				$return_data = $kakao->kakaoSid($pg_token,$orderNo,$row['tid'],$row['pass']);		

			}
		
		}else{
		
		}
		

		exit;
	
	}
}