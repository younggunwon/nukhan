<?php

namespace Component\Traits;

use App;
use Component\Sms\Sms;
use Component\Sms\SmsMessage;
use Component\Sms\LmsMessage;
use Framework\Security\Otp;

/**
* SMS 전송 관련 
*
* @author webnmobile
*/
trait SendSms
{
	/** 
	* SMS 전송 처리 
	* 
	* @param String $mobile 휴대전화번호 
	* @param String $contents 전송메세지
	* @param Array $changeCode 치환 코드
	* 
	* @return Boolean 전송성공여부
	*/
    public function sendSms($mobile, $contents, $changeCode = [])
    {
		$bool = false;
		$smsPoint = Sms::getPoint();
		if ($smsPoint >= 1) {
            foreach ($changeCode as $k => $v) {
                if (is_numeric($v))
                    $v = number_format($v);

                $contents = str_replace("{{$k}}", "{$v}", $contents);
            }

            $adminSecuritySmsAuthNumber = Otp::getOtp(8);
            $receiver[0]['cellPhone'] = $mobile;
            $smsSender = \App::load('Component\\Sms\\SmsSender');
            $smsSender->setSmsPoint($smsPoint);

            if(mb_strlen($contents, 'euc-kr')>90){
              $smsSender->setMessage(new LmsMessage($contents));
            }else{
              $smsSender->setMessage(new SmsMessage($contents));
            }

            $smsSender->validPassword(\App::load(\Component\Sms\SmsUtil::class)->getPassword());
            $smsSender->setSmsType('user');
            $smsSender->setReceiver($receiver);
            $smsSender->setLogData(['disableResend' => false]);
            $smsSender->setContentsMask([$adminSecuritySmsAuthNumber]);
            $smsResult = $smsSender->send();
            $smsResult['smsAuthNumber'] = $adminSecuritySmsAuthNumber;

            if ($smsResult['success'] === 1)
              $bool = true;
		}

		return $bool;
	}
}