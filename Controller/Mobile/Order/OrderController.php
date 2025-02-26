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

namespace Controller\Mobile\Order;

use Cookie;

/**
 * 주문서 작성
 * @author Shin Donggyu <artherot@godo.co.kr>
 */
class OrderController extends \Bundle\Controller\Mobile\Order\OrderController
{
    /**
     * {@inheritdoc}
     */
    public function index()
    {
        parent::index();
		
		//wg-brown 바로구매일시 플래그 추가
		if(Cookie::has('isDirectCart')){
			$this->setData('directCartFl', 'y');
		}
    }
}
