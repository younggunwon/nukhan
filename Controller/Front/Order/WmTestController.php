<?php
namespace Controller\Front\Order;


use Session;
use Request;

/**
 * 테스트용
 */
use App;

class WmTestController extends \Controller\Front\Controller
{
    public function index()
    {

		//$obj = App::load("\\Component\\Wm\\Wm_Util");
		$wmobj = App::load("\Component\Wm\Wm_Util");
		//$obj->nature_word("아름드리 나무");

		exit();
    }
}