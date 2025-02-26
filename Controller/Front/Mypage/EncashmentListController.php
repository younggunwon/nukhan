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
 * @link      http://www.godo.co.kr
 */

namespace Controller\Front\Mypage;

use Bundle\Component\Mileage\Mileage;
use Component\Mileage\MileageUtil;
use Component\Page\Page;
use Framework\Debug\Exception\AlertBackException;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\StringUtils;

/**
 * 마이페이지-혜택 마일리지
 * @package Bundle\Controller\Front\Mypage
 * @author  yjwee
 */
class EncashmentListController extends \Controller\Front\Controller
{
    /**
     * @inheritdoc
     */
    public function index()
    {
		if (!gd_is_login()) {
			return $this->js("alert('로그인이 필요한 페이지 입니다.');window.location.href='../member/login.php';");
		}

        $request = \App::getInstance('request');
        $session = \App::getInstance('session');
        if ($session->has(SESSION_GLOBAL_MALL)) {
            throw new AlertBackException(__('잘못된 접근입니다.'));
        }

        if (is_numeric($request->get()->get('searchPeriod')) === true && $request->get()->get('searchPeriod') >= 0) {
            $selectDate = $request->get()->get('searchPeriod');
        } else {
            $selectDate = 999;
        }
        $regDt = DateTimeUtils::getBetweenDateString('-' . ($selectDate) . 'days');
		if($selectDate == 999) {
			$regDt[0] = '0000-00-00';
		}

        // 기간 조회
        if ($request->isMobile() === true) {
            $searchDate = [
                '1'   => __('오늘'),
                '7'   => __('최근 %d일', 7),
                '15'  => __('최근 %d일', 15),
                '30'  => __('최근 %d개월', 1),
                '90'  => __('최근 %d개월', 3),
                '180' => __('최근 %d개월', 6),
                '365' => __('최근 %d년', 1),
                '999' => __('전체'),
            ];
            $this->setData('searchDate', $searchDate);
            $this->setData('selectDate', $selectDate);
        }

        $regTerm = $request->get()->get('regTerm', 999);
        $regDt = $request->get()->get('regDt', $regDt);

        $active['regTerm'][$regTerm] = 'active';

        /**
         * 페이지 데이터 설정
         */
        $page = $request->get()->get('page', 1);
        $pageNum = $request->get()->get('pageNum', 10);

        // 보안취약점 요청사항 추가
        $regDt[0] = preg_replace("/([^0-9\-])/", "", $regDt[0]);
        $regDt[1] = preg_replace("/([^0-9\-])/", "", $regDt[1]);

        /**
         * 요청처리
         * @var \Bundle\Component\Mileage\Mileage $mileage
         */
        $mileage = \App::load('\\Component\\Mileage\\Mileage');
        $list = $mileage->getPaybackList($regDt, $page, $pageNum);

        /**
         * 페이징 처리
         */
        $p = new Page($page, $mileage->foundPaybackByListSession(), null, $pageNum);
        $p->setPage();
        $p->setUrl($request->getQueryString());

        /**
         * View 데이터
         */
        $this->setData('list', $list);
        $this->setData('regTerm', $regTerm);
        $this->setData('regDt', $regDt);
        $this->setData('active', $active);
        $this->setData('page', $p);

        /**
         * css 추가
         */
        $this->addCss(
            [
                'plugins/bootstrap-datetimepicker.min.css',
                'plugins/bootstrap-datetimepicker-standalone.css',
            ]
        );

        /**
         * js 추가
         */
        $locale = \Globals::get('gGlobal.locale');
        $this->addScript(
            [
                'moment/moment.js',
                'moment/locale/' . $locale . '.js',
                'jquery/datetimepicker/bootstrap-datetimepicker.min.js',
            ]
        );
    }
}
