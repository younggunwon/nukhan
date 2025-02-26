<?php

namespace Controller\Front\Subscription\Inicis;

class CloseController extends \Controller\Front\Controller 
{
    public function index()
    {
        echo '<script language="javascript" type="text/javascript" src="https://stdpay.inicis.com/stdjs/INIStdPay_close.js" charset="UTF-8"></script>';
        exit;
    }
}