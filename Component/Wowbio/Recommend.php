<?php

/**
 * This is commercial software, only users who have purchased a valid license
 * and accept to the terms of the License Agreement can install and use this
 * program.
 *
 * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 * versions in the future.
 *
 * @copyright ⓒ 2016, NHN godo: Corp.
 * @link      http://www.godo.co.kr
 */

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

/**
 * Class Recommend
 * @package Bundle\Component\Wowbio
 * @author  hokim
 */
class Recommend extends \Bundle\Component\AbstractComponent
{

    public $db;
    public $post;
    public $arrBind = [];
    protected $arrWhere = [];
    protected $arrSort = [];
    const RECOMM_SUBSCRIPTION = 30000;
    const RECOMM_ORDER = 5; //5%

    public function __construct()
    {
        $this->db = App::load('DB');
    }
    

    /**
     * 추천인 정보 불러오기 리스트형
     * */
    public function getList($arrData, $offset=0, $pageNum){

        $arrField = "o.orderStatus,o.regdt, m.memNo, m.memNm, m.memId, g.goodsNm, rm.memNo as rmemNo, rm.memId as rmemId, rm.memNm as rmemNm";
        $this->db->strField = 'SQL_CALC_FOUND_ROWS ' . $arrField . ' ';

        $dbTable = "es_member";

        //회원 데이터와 연동
        $arrJoin[] = ' inner join es_member rm on rm.recommId = m.memId';
        $arrJoin[] = 'i nner join es_order as o on rm.memNo = o.memNo'
        $arrJoin[] = ' INNER JOIN wm_subSchedules ws ON o.orderNo=ws.orderNo';
        $arrJoin[] = ' inner join wm_subSchedulesGoods as wg on ws.idx = wg.idx and wg.goodsNo in (1000000020,1000000021)';
        $arrJoin[] = ' inner join es_goods g on g.goodsNo = wg.goodsNo';
        

        $arrWhere[] = " left(o.orderStatus,1) in ('d','s')";

        if($arrData['keyword']){
            if($arrData['key'] == 'all'){
                $arrWhere[] = 'concat(m.memId, m.memNm, rm.memId, rm.memNm, g.goodsNm) like concat(\'%\',?,\'%\')';
            }else{
                $arrWhere[] = $arrData['key'].' like concat(\'%\',?,\'%\')';
            }
            $this->db->bind_param_push($this->arrBind, 's', $arrData[keyword]);
        }
        if($arrData['recommId']){
            $arrWhere[] = 'rm.recommid like concat(\'%\',?,\'%\')';
            $this->db->bind_param_push($this->arrBind, 's', $arrData[recommId]);
        }

        $arrSort[] = " o.regDt desc";        

        $this->db->strWhere = implode(' AND ', gd_isset($arrWhere));        // 필드 설정
        $this->db->strJoin = implode('', $arrJoin);
        $this->db->strOrder = implode(',', gd_isset($arrSort));        
        $this->db->strLimit = ($offset - 1) * $pageNum . ', ' . $pageNum;

        $query = $this->db->query_complete(true,true);
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . $dbTable . 'as m ' . implode(' ', $query);

        $data = $this->db->query_fetch($strSQL, $this->arrBind);        
        
        return $data;
    }

    /**
     * 추천인 관리자페이지
     * */

    /**
     * 추천인 정기결제 금액
     * 정기결제 금액
     * 정기결제중 우먼팩, 맨즈팩만 포함
     * */
    public function getRecommSettle($memNo,$sDate, $eDate){

        $arrField = "o.settleprice, og.goodsPrice";
        $this->db->strField = 'SQL_CALC_FOUND_ROWS ' . $arrField . ' ';
        
        $sql = "select settlePrice, og.goodsPrice
                from es_member as em
                inner join wow_orderRecomm as recomm  on o.orderNo = recomm.orderNo
                inner join es_order as o on em.memNo = o.memNo
                inner join es_orderGoods as og on o.orderNo = og.orderNo and 
                
                where recomm.orderNo is NULL".$where;

        $where[] = " and m.memNo = $memNo";
        $where[] = " and o.regDt between $sDate and $eDate";

        return $data;

    }

    /**
     * 추천인 결제금액
     * 추천인을 넣은 경우 결제금액에 5% 반환
     * 주문조건 확인중
     * */
    public function getRecommOrder(){
        $sql = "select o.settleprice, p.goodsPrice";
    }
    
    /**
     * 추천인 포인트 신청하기
     * */
    public function getView(){
        $content = $this->db->query_fetch('select * from wow_recommend where sno = "'.$sno.'" ','',false);
        
        return $content;
    }    
    
}