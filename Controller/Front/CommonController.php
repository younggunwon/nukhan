<?php
namespace Controller\Front;

use Request;

class CommonController
{

	public function index($controller)
	{
		$memId=\Session::get("member.memId");
		if($memId=="mintweb"){
			$controller->setData("remote",1);
		}
	}
}