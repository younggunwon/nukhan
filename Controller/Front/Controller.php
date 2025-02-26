<?php

namespace Controller\Front;

use Request;
use Session;
use App;

class Controller extends \Bundle\Controller\Front\Controller
{
	public function pre() {
		parent::pre();
		
		// 2024-02-08 wg-eric 페이스북 광고로 들어오면 쿠키 저장
		$getValue = \request::get()->toArray();
		if($getValue['utm_source'] == 'facebook' && $getValue['fbclid']) {
			\Cookie::set('wgEnterFromFacebookFl', 'y');
		}

		if($getValue['utm_source'] == 'youtube' && $getValue['gclid']) {
			\Cookie::set('wgEnterFromGoogleFl', 'y');
		}

		//2024-01-17 루딕스-brown 초대회원이 있을때 session에 저장
		$session = \App::getInstance('session');
		$request = \App::getInstance('request');
		$recommendMemNo = $request->get()->all()['rcm'];
		if($recommendMemNo) {
			$session->set('recommendMemNo', $recommendMemNo);
		}
		//2024-01-17 루딕스-brown 초대회원이 있을때 session에 저장

		//2024-01-17 루딕스-brown 카카오 회원가입 최초1회 일때
		if($session->get('member.memNo') && !$session->get('member.loginCnt') && $session->get('kakaoLoginFl') == 'y') {
			$member = \App::load(\Component\Member\Member::class);
			$memData = $member->getMember($session->get('member.memNo'), 'memNo');
			if(!$memData['recommId']) {
				$recomRegisterFl = 'y';
			}
			$session->del('kakaoLoginFl');
		}
		$this->setData('recomRegisterFl', gd_isset($recomRegisterFl, 'n'));
		//2024-01-17 루딕스-brown 카카오 회원가입 최초1회 일때

		// 2023-12-15 wg-eric 회원가입 쿠키 가져오기
		$wgJoinFl = \Cookie::get('wgJoinFl');
		$this->setData('wgJoinFl', $wgJoinFl);

		$wgGoogleJoinFl = \Cookie::get('wgGoogleJoinFl');
		$this->setData('wgGoogleJoinFl', $wgGoogleJoinFl);

		$wgEnterFromGoogleFl = \Cookie::get('wgEnterFromGoogleFl');
		$this->setData('wgEnterFromGoogleFl', $wgEnterFromGoogleFl);
	}
}