<?php

namespace Controller\Mobile\Subscription;

use Framework\Debug\Exception\Except;
use Framework\Debug\Exception\AlertOnlyException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Debug\Exception\AlertReloadException;
use Component\Subscription\CartSub as Cart;
use Exception;
use Message;
use Request;
use Session;

/**
*  정기결제 장바구니 처리 페이지 
*
* @author webnmobile
*/
class CartPsController extends \Controller\Front\Subscription\CartPsController
{
    /**
     * index
     *
     * @throws Except
     */
    public function index()
    {
        parent::index();
    }
}