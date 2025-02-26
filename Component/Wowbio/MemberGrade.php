<?php

namespace Component\Wowbio;

use App;
use Exception;
use Component\Database\DBTableField;
use Component\Page\Page;
use Framework\Debug\Exception\AlertCloseException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Utility\ArrayUtils;
use Framework\Utility\ComponentUtils;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\StringUtils;
use Framework\Security\Digester;
use Framework\Utility\GodoUtils;
use Globals;
use Cookie;
use Logger;
use Request;
use Session;
use UserFilePath;

class Recommend extends \Bundle\Component\AbstractComponent
{
	
	public $db;
    public $post;
    public $memNo;
    
    public function __construct()
    {
        $this->db = App::load('DB');
        $memNo = Session::get('member.memNo');
    }

    /**
	 * 회원등급 표시를 위한 정보
	 * */
    public function memberGradeInfo(){		
		
    	/**
    	 * 최근 3개월간 구매금액
    	 * 환불, 취소 제외 구매완료
    	 * 현재등급, 예상등급, 다음등급까지 필요한 금액
    	 * */


		$strSQL = 
		"select sum(settleprice) as nPrice  from es_order
		where memNo > 0 and left(orderStatus,1) in ('d','s')
		and date_format(regdt,'%Y-%m') between date_format(date_add(now(),interval -3 month),'%Y-%m') and date_format(date_add(now(),interval -1 month),'%Y-%m')
		and left(orderStatus,1) in ('s')
		and memNO = '".$memNo."'";

		$data = $this->db->query_fetch($strSQL, null);

		$rDate[nPrice] = $data[nPrice];

		/**
		 * 등급별 구매금액 
		 * es_memberGroup 
		 * apprFigureOrderPriceBelow 실적수치제구매액미만
		 * 등급 4개 편의상 A, B, C, D
		 * */

		$strSQL = "select (apprFigureOrderPriceBelow - ".$data[nPrice].") as xPrice, groupNm, groupSno from es_memberGroup
					where apprFigureOrderPriceMore <= ".$data[nPrice]." AND apprFigureOrderPriceBelow > ".$data[nPrice]." "		

		$data = $this->db->query_fetch($strSQL, null);		

		$rDate[xPrice] = $data[xPrice];
		$rDate[nGrdade] = $data[nGrade];
		$rDate[xGrdade] = $data[xGrade];

		return $rData;
    }
}