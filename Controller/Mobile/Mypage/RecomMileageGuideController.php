<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link http://www.godo.co.kr
 */
namespace Controller\Mobile\Mypage;

use Bundle\Component\Deposit\Deposit;
use Component\Page\Page;
use Framework\Debug\Exception\AlertBackException;
use Framework\Utility\DateTimeUtils;
use Component\Validator\Validator;
use Component\Promotion\SocialShare;

/**
 * Class ShippingController
 *
 * @package Bundle\Controller\Front\Mypage
 * @author  Jong-tae Ahn <qnibus@godo.co.kr>
 */
class RecomMileageGuideController extends \Bundle\Controller\Mobile\Controller
{
	public function index()
	{
		if (!gd_is_login()) {
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		}

		$request = \App::getInstance('request');
        $session = \App::getInstance('session');
		$goods = \App::load('Component\Goods\Goods');
        if ($session->has(SESSION_GLOBAL_MALL)) {
            throw new AlertBackException(__('잘못된 접근입니다.'));
        }

		//전체상품 추천인 마일리지
		$getValue['page'] = gd_isset($request->get()->all()['page'], 1);
		$getValue['pageNum'] = 10;
		$allRecomGoodsView = $goods->recomGoodsMileageView($getValue);
		$this->setData('allRecomGoodsView' , $allRecomGoodsView);
		$page = \App::load('\\Component\\Page\\Page'); // 페이지 재설정
		$this->setData('page', gd_isset($page));
		
		//통합 추천인 마일리지 설정
		$recomMileageConfig = gd_policy('member.recomMileageGive');
		$this->setData('recomMileageConfig', $recomMileageConfig);
		//통합 추천인 마일리지 설정
		
		//맨즈팩 추천인 마일리지
		$goodsView = $goods->getGoodsView(1000000020);
		$man['recomMileageData']['recomNoMileage'] = $goodsView['recomNoMileage'];
		$man['recomMileageData']['recomNoMileageUnit'] = $goodsView['recomNoMileageUnit'];
		$man['recomMileageData']['recomSubMileage'] = $goodsView['recomSubMileage'];
		$man['recomMileageData']['recomSubMileageUnit'] = $goodsView['recomSubMileageUnit'];
		$man['recomMileageData']['recomMileageFl'] = $goodsView['recomMileageFl'];
		$this->setData('man' , $man);

		//우먼팩 추천인 마일리지
		$goodsView = $goods->getGoodsView(1000000021);
		$woman['recomMileageData']['recomNoMileage'] = $goodsView['recomNoMileage'];
		$woman['recomMileageData']['recomNoMileageUnit'] = $goodsView['recomNoMileageUnit'];
		$woman['recomMileageData']['recomSubMileage'] = $goodsView['recomSubMileage'];
		$woman['recomMileageData']['recomSubMileageUnit'] = $goodsView['recomSubMileageUnit'];
		$woman['recomMileageData']['recomMileageFl'] = $goodsView['recomMileageFl'];
		$this->setData('woman' , $woman);

		$memId = $session->get('member.memId');
		$memNo = $session->get('member.memNo');
		$memNm = $session->get('member.memNm');
		
		$this->setData('memId' , $memId);
		$this->setData('memNo' , $memNo);
		$this->setData('memNm' , $memNm);
		$this->setData('gPageName' , '추천인 적립금');
	}
}