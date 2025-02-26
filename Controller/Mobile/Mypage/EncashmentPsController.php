<?php

namespace Controller\Mobile\Mypage;

use Exception;
use Framework\Debug\Exception\AlertReloadException;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\AlertRedirectException;
use Component\Database\DBTableField;
use DB;

class EncashmentPsController extends \Controller\Mobile\Controller
{
	public function index() {

		$this->db = \App::load('DB');
		$session = \App::getInstance('session')->get('member');
		$getValue = \Request::get()->toArray();
		$mileage = \App::load('\\Component\\Mileage\\Mileage');

		try {

			# 최소 요청 금액 설정
			if($getValue['encashmentPrice'] < 20000) {
				throw new exception('최소신청금액은 20,000원 입니다.');
			}
			
			# 페이백 신청은 월1회, 최대 30만원까지만 가능하도록
			if($getValue['encashmentPrice'] > 300000) {
				throw new exception('최대신청금액은 300,000원 입니다.');
			}
			$result = $mileage->encashmentCheck($session['memNo']);
			# 페이백 신청은 월1회, 최대 30만원까지만 가능하도록

			if(floatval(str_replace(",", "", $getValue['encashmentPrice'])) > floatval(str_replace(",", "", $getValue['mileage']))) {
				throw new exception('신청금액을 현재 적립금보다 같거나 낮게 입력해주세요.');
			}
			
			# 추천인 마일리지 지급건이 있을때만 신청가능
			$sql = 'SELECT sno FROM es_memberMileage WHERE (reasonCd = 01005506 OR reasonCd = 01005507) AND memNo='.$session['memNo'];
			$existData = $this->db->query_fetch($sql);
			if(count($existData) == 0) {
				throw new exception('추천인 적립금이 있을시에만 신청가능합니다.');
			}

			# 현금화 요청
			$getValue['memNo'] = $session['memNo'];
			$arrBind = $this->db->get_binding(DBTableField::tableMileageEncashment(), $getValue, 'insert', array_keys($getValue));
			$this->db->set_insert_db('wg_mileageEncashment', $arrBind['param'], $arrBind['bind'], 'y');
			# 현금화 요청
			
			# 금액만큼 마일리지 차감
			$arrData = [
				'mileageCheckFl' => 'remove',
				'mileageValue' => $getValue['encashmentPrice'],
				'reasonCd' => '01005508',
				'contents' => '적립금 현금화 요청',
				'removeMethodFl' => 'minus',
				'mode' => 'remove_mileage',
				'chk' => $session['memNo'],
			];
			$mileage->removeMileage($arrData);
			# 금액만큼 마일리지 차감

			$this->js("alert('마일리지 현금화 요청이 되었습니다.');  parent.location.href='../mypage/encashment.php'");
		} catch (Exception $e) {
			throw new AlertOnlyException($e->getMessage(), null, null, 'top');
		}	



	}
}