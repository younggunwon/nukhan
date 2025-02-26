<?php
namespace Controller\Front\Goods;


class TestController extends \Controller\Front\Controller
{
	public function index()
	{
		$notifly = \App::load('Component\\Notifly\\Notifly');
		// $userInfo = [
		// 	'memId'	 => 'testUser',
		// 	'$email' => 'email@email.com',
		// 	'$phone_number' => '010101010',
		// 	'testField3' => 'testValue3',
		// ];
		// $notifly->setUser($userInfo);

		// $userId = 'nukhan';
		// $notifly->deleteUser($userId);
		exit;
	}
}