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
namespace Controller\Mobile\Board;

use Component\Board\Board;
use Component\Board\BoardList;
use Component\Goods\GoodsCate;
use Component\Page\Page;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\RedirectLoginException;
use Framework\Debug\Exception\RequiredLoginException;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\Strings;
use Request;
use View\Template;

class ListController extends \Bundle\Controller\Mobile\Board\ListController
{

    public function pre()
    {
        if(!Request::get()->get('sort') && Request::get()->get('bdId') == 'event'){
            Request::get()->set('sort','b.regDt desc');
        }
    }
}
