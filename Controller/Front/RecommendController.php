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
namespace Controller\Front;
use Request;
use Cookie;


/**
 * 추천인 로그 기록용
 *
 */
class RecommendController extends \Bundle\Controller\Front\Controller
{
    /**
     * 추천인 로그인시 아이디를 쿠키에 넣는다 (2일간 유지)
     */
    
    public function index()
    {
        
        $get = Request::request()->all();
        $cookieNm = 'wowbioRecommend';

        if(!Cookie::has($cookieNm)){
            Cookie::set($cookieNm, $sno, 84600 * 2, '/');
        }

        //페이지 이동 
        if(Request::isMobileDevice()){
            header("location:https://m.re4day.co.kr/board/view.php?&bdId=event&sno=6");
            exit;
        }else{
            header("location:https://re4day.co.kr/board/view.php?&bdId=event&sno=6");
            exit;
        }   
    }
}
