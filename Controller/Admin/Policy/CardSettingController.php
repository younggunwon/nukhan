<?php

namespace Controller\Admin\Policy;

use App;

/**
* 카드 진열 설정 
* 
* @author webnmobile
*/
class CardSettingController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->callMenu("policy", "subscription", "card_setting");
		
		$db = App::load(\DB::class);
		$subscription = App::load(\Component\Subscription\Subscription::class);
		$cards = $subscription->getPgCards();
		
		$banks = $subscription->getPgBanks();
		$this->setData("cards", $cards);
		$this->setData("banks", $banks);
		
		$path = dirname(__FILE__) . "/../../../../data/cards/";
		$data = [];
		$sql = "SELECT * FROM wm_cardSet ORDER BY idx";
		$tmp = $db->query_fetch($sql);
		foreach ($tmp as $t) {
			$cardPath = $path . $t['cardType'] ."_".$t['cardCode'];
			if (file_exists($cardPath)) {
				$t['imagePath'] = $cardPath;
				$t['imageUrl'] = "/data/cards/".$t['cardType']."_".$t['cardCode'];
			}
			
			$data[$t['cardType']][$t['cardCode']] = $t;
		}
		
		$this->setData("data", $data);
	}
}