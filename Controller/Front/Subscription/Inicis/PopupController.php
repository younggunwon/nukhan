<?php

namespace Controller\Front\Subscription\Inicis;

class PopupController extends \Controller\Front\Controller 
{
    public function index()
    {
         echo '<script language="javascript" type="text/javascript" src="https://stdpay.inicis.com/stdjs/INIStdPay_popup.js" charset="UTF-8"></script>';
        exit;
    }
}