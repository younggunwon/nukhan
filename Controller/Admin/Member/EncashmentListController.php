<?php

namespace Controller\Admin\Member;

use Framework\Debug\Exception\AlertOnlyException;

class EncashmentListController extends \Bundle\Controller\Admin\Controller
{
	public function index() {
		$this->callMenu('member', 'point', 'encashment_list');

		$encashment = \App::load('\\Component\\Member\\Encashment');
		$this->db = \App::load('DB');
		$getValue = \Request::get()->toArray();

		$getData = $encashment->getEncashmentList();
		$page = \App::load('\\Component\\Page\\Page');

		$combineSearch = [
			'all'       => '=통합검색=',
			'memId'     => '신청 ID ',
			'applicant' => '신청인',
			'cellPhone' => '휴대폰번호',
		];

		$this->setData('data', $getData['data']);
		$this->setData('search', $getData['search']);
		$this->setData('checked', $getData['checked']);
		$this->setData('page', $page);
		$this->setData('combineSearch', $combineSearch);

	}
}