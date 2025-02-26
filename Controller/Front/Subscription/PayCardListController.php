<?php
namespace Controller\Front\Subscription;

use App;
use Request;
class PayCardListController extends \Controller\Front\Controller
{

	public function index()
	{
		$subscripition = App::load(\Component\Subscription\Subscription::class);
		$list = $subscripition->getCards();

		$page=$this->getData("page");
		$this->setData("page",$page);
		
		$this->setData("list", $list);
		$this->getView()->setDefine("tpl", "subscription/_pay_card.html");
		
	}

}