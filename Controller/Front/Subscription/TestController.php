<?php

namespace Controller\Front\Subscription;

use App;

class TestController extends \Controller\Front\Controller
{
	public function index()
	{
		//$subscription = App::load(\Component\Subscription\Subscription::class);
		///$subscription->pay(14);

		$server =\Request::server()->toArray();
		gd_debug($server);
		exit;
	}
}