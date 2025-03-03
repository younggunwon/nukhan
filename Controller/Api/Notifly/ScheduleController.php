<?php
namespace Controller\Api\Notifly;

class ScheduleController extends \Bundle\Controller\Api\Controller
{
	public function index() {
        // 정기결제 상태 업데이트
		$notifly = \App::load(\Component\Notifly\Notifly::class);
		$notifly->setUserSubscription();
        
        // 주문 수량 업데이트
        $notifly->setUserOrderCnt();

        // 등급 상태 업데이트
        $notifly->setUserGroup();

        // 성별 업데이트
        $notifly->setUserSex();

        // 주문 금액 업데이트
        $notifly->setUserOrderPrice();

        // 다음 결제일 업데이트
        $notifly->setUserNextPayDate();
        
        $notifly->setUsers($notifly->sendMemberData);

	}
}

