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

		// $userId = 'nukhan1';
		// $notifly->deleteUser($userId);

		// $this->db = \App::load('DB');
		// $sql = "SELECT * FROM wg_apiLog WHERE sno = 24239";
		// $apiLog = $this->db->query_fetch($sql);
		// gd_debug($apiLog);
		// exit;

		$notifly->setUserNextPayDate();
		exit;
	}
}