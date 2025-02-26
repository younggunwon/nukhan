<?php
namespace Controller\Front\Member;

use Request;
use Session;

class JoinAgreementController extends \Bundle\Controller\Front\Member\JoinAgreementController
{
    public function post()
    {
        //페이지 이동 
        header("location:/member/join.php");
        exit;       
    }
}