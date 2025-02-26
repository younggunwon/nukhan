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
namespace Controller\Admin\Goods;

use Exception;
use Framework\Utility\ImageUtils;
use Component\Member\Group\Util;
use Globals;
use Request;
use Session;

/**
 * 상품 등록 / 수정 페이지
 */
class GoodsRegisterOptionController extends \Bundle\Controller\Admin\Goods\GoodsRegisterOptionController
{

    /**
     * index
     *
     * @throws Except
     */
    public function index()
    {
		parent::index();
        $this->getView()->setPageName('goods/goods_register_option_test.php');

    }
}
