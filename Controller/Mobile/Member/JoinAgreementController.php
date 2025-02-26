<?php
namespace Controller\Mobile\Member;

use Request;
use Session;

class JoinAgreementController extends \Bundle\Controller\Mobile\Member\JoinAgreementController
{
    public function post()
    {
        //페이지 이동 
        header("location:/member/join.php");
        exit;        
    }
}