<?php

namespace Controller\Admin\Policy;

use Exception;
use Globals;
use Request;
use App;

/**
* 정기결제 상품설정 
*
* @author webnmobile
*/
class SubscriptionGoodsController extends \Controller\Admin\Controller
{
	public function index()
	{
		try {
			$this->callMenu("policy", "subscription", "goods");
			// --- 모듈 호출
            $cate = \App::load('\\Component\\Category\\CategoryAdmin');
            $brand = \App::load('\\Component\\Category\\CategoryAdmin', 'brand');
            $goods = \App::load('\\Component\\Goods\\GoodsAdmin');
			$db = \App::load(\DB::class);
			
            /* 운영자별 검색 설정값 */
            $searchConf = \App::load('\\Component\\Member\\ManagerSearchConfig');
            $searchConf->setGetData();

            //배송비관련
            $mode['fix'] = [
                'free'   => __('배송비무료'),
                'price'  => __('금액별배송'),
                'count'  => __('수량별배송'),
                'weight' => __('무게별배송'),
                'fixed'  => __('고정배송비'),
            ];

            $getData = $goods->getAdminListBatch('image');
            $getIcon = $goods->getManageGoodsIconInfo();
            $page = \App::load('\\Component\\Page\\Page'); // 페이지 재설정

            $this->getView()->setDefine('goodsSearchFrm',  Request::getDirectoryUri() . '/goods_list_search2.php');
			
			$this->addScript([
				'jquery/jquery.multi_select_box.js',
            ]);
			
			if ($getData['data']) {
				foreach ($getData['data'] as $k => $v) {
					$sql = "SELECT useSubscription, togetherGoodsNo, showSubscriptionLink, subLinkGoodsNo, subLinkButtonNm  FROM " . DB_GOODS . " WHERE goodsNo = ?";
					$row = $db->query_fetch($sql, ["i", $v['goodsNo']], false);
					$v = array_merge($v, $row);
					$getData['data'][$k] = $v;
				}
			} // endif
			
            //정렬 재정의
            $getData['search']['sortList'] = array(
                'g.goodsNo desc' => sprintf(__('등록일 %1$s'), '↓'),
                'g.goodsNo asc' => sprintf(__('등록일 %1$s'), '↑'),
                'goodsNm asc' => sprintf(__('상품명 %1$s'), '↓'),
                'goodsNm desc' => sprintf(__('상품명 %1$s'), '↑'),
                'companyNm asc' => sprintf(__('공급사 %1$s'), '↓'),
                'companyNm desc' => sprintf(__('공급사 %1$s'), '↑'),
                'fixedPrice asc' => sprintf(__('정가 %1$s'), '↓'),
                'fixedPrice desc' => sprintf(__('정가 %1$s'), '↑'),
                'costPrice asc' => sprintf(__('매입가 %1$s'), '↓'),
                'costPrice desc' => sprintf(__('매입가 %1$s'), '↑'),
                'goodsPrice asc' => sprintf(__('판매가 %1$s'), '↓'),
                'goodsPrice desc' => sprintf(__('판매가 %1$s'), '↑'),
            );

            $this->setData('goods', $goods);
            $this->setData('cate', $cate);
            $this->setData('brand', $brand);
            $this->setData('data', $getData['data']);
            $this->setData('search', $getData['search']);
            $this->setData('checked', $getData['checked']);
            $this->setData('selected', $getData['selected']);
            $this->setData('batchAll', gd_isset($getData['batchAll']));
            $this->setData('getIcon', $getIcon);
            $this->setData('page', $page);
            $this->setData('mode', $mode);
			$this->setData("isSubscription", true);
			
		} catch (Exception $e) {
            throw $e;
        }
	}
}