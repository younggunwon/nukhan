<?php
namespace Controller\Front\Mypage;

use Session;
use Cookie;
use Request;
use Component\Page\Page;
use Component\Wowbio\Recommend;

/**
 * Class 추천인 리스트
 * @package Bundle\Controller\Front\Mypage
 */
class RecommendListController extends \Bundle\Controller\Front\Controller
{
    public function index()
    {
        $recom = new Recommend();
        $post = Request::request()->all();
        $page = Request::get()->get('page', 1);
        $pageNum = Request::get()->get('pageNum', 10);
        
        $data = $recom->getList($post,$page,$pageNum);

        $page = new Page($page, $recom->foundRows(), null, $pageNum);
        $page->setPage();
        $page->setUrl(Request::getQueryString());          

        $this->setData('page', $page);
        $this->setData('data', $data);
    }
}
