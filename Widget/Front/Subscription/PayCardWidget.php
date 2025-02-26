<?php

namespace Widget\Front\Subscription;

use App;

/**
* 결제 카드 목록 
*
* @author webnmobile
*/
class PayCardWidget extends \Widget\Front\Widget
{
	public function index()
	{
		$subscripition = App::load(\Component\Subscription\Subscription::class);
		$list = $subscripition->getCards();

		$page=$this->getData("page");
		$this->setData("page",$page);
		
		//wg-brown 카카오/신용카드 분류
		$wgList = [];
		if($list) {
			foreach($list as $key => $val) {
				if($val['method'] == 'kakao') {
					$wgList['kakao'] = $val;
				}

				if($val['method'] == 'ini') {
					$wgList['ini'] = $val;
				}
			}
		}
		//wg-brown 카드번호 가공
		foreach($wgList as $method => $val) {
			if (preg_match("/카드번호\s*:\s*([0-9*]+)\s*/", $val['settleLog'], $matches)) {
				$wgList[$method]['cardNo'] = $matches[1];
			} else {
				$wgList[$method]['cardNo'] = '';
			}
		}
		$this->setData("list", $wgList);
	}
}