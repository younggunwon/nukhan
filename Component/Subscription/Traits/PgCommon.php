<?php

namespace Component\Subscription\Traits;

use App;

/**
* 정기결제 PG 공통 
*
* author webnmobile
*/
trait PgCommon
{
	/**
	* 빌링키 발급 모듈 실행을 위한 sign키 생성 
	*
	* @param String $uid 
	* @param Integer $price 결제표기 금액
	* @param Integer $timestamp 
	* @param $isMobile 모바일 여부
	*
	* @return String sha256 해시
	*/ 
	public function getPgSign($uid, $price = 0, $timestamp = 0, $isMobile = false)
	{
		$conf = $this->getCfg();
		 $timestamp = $timestamp?$timestamp:$conf['timestamp'];
		if ($isMobile) {
			$params = $conf['mid'] . $uid . $timestamp . $conf['lightKey'];
		} else {
			$params = "oid=" . $uid . "&price=" . $price . "&timestamp=" . $timestamp;
		}
		
		return hash('sha256', $params);
	}
	
	
	/**
	* 카드사 코드
	*
	*/
    public function getPgCards()
    {
        $pgCards	= array(
                 '01'	=> '하나(외환)카드',
				 '02'	=>'우리카드',
                 '03'	=> '롯데카드',
                 '04'	=> '현대카드',
                 '06'	=> '국민카드',
                 '11'	=> 'BC카드',
                 '12'	=> '삼성카드',
                 '13'	=> '(구)LG카드',
                 '14'	=> '신한카드',
                 '15'	=> '한미카드',
                 '16'	=> 'NH카드',
                 '17'	=> '하나SK카드',
                 '21'	=> '해외비자카드',
                 '22'	=> '해외마스터카드',
                 '23'	=> '해외JCB카드',
                 '24'	=> '해외아멕스카드',
                 '25'	=> '해외다이너스카드',
                 '98'	=> '페이코(포인트 100% 사용)',
            );

            return $pgCards;
         }

         /**
		 * 은행 코드
		 *
		 */
         public function getPgBanks()
         {
            $pgBanks	= array(
                 '02'	=> '한국산업은행',
                 '03'	=> '기업은행',
                 '04'	=> '국민은행',
                 '05'	=> '하나은행(구외환)',
                 '07'	=> '수협중앙회',
                 '11'	=> '농협중앙회',
                 '12'	=> '단위농협',
                 '16'	=> '축협중앙회',
                 '20'	=> '우리은행',
                 '21'	=> '신한은행',
                 '23'	=> '제일은행',
                 '25'	=> '하나은행',
                 '26'	=> '신한은행',
                 '27'	=> '한국씨티은행',
                 '31'	=> '대구은행',
                 '32'	=> '부산은행',
                 '34'	=> '광주은행',
                 '35'	=> '제주은행',
                 '37'	=> '전북은행',
                 '38'	=> '강원은행',
                 '39'	=> '경남은행',
                 '41'	=> '비씨카드',
                 '53'	=> '씨티은행',
                 '54'	=> '홍콩상하이은행',
                 '71'	=> '우체국',
                 '81'	=> '하나은행',
                 '83'	=> '평화은행',
                 '87'	=> '신세계',
                 '88'	=> '신한은행',
                 '98'	=> '페이코(포인트 100% 사용)',
          );

          return $pgBanks;
    }
	
	/**
	* Unique ID 생성 
	*
	* @return Integer
	*/
	public function getUid()
    {
        return round(microtime(true) * 1000);
    }
}