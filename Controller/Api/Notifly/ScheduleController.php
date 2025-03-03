<?php
namespace Controller\Api\Notifly;

class ScheduleController extends \Bundle\Controller\Api\Controller
{
	public function index() {
        // 정기결제 상태 업데이트
		$notifly = \App::load(\Component\Notifly\Notifly::class);
		$notifly->setUserSubscription();
        
	}
}

