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
namespace Component\Member\HackOut;

use Component\Database\DBTableField;
use Exception;
use Framework\Database\DBTool;
use Framework\Utility\ArrayUtils;
use Framework\Utility\StringUtils;

/**
 * 회원탈퇴 DAO 클래스
 * @package Bundle\Component\Member\HackOut
 * @author  yjwee
 */
class HackOutDAO extends \Bundle\Component\Member\HackOut\HackOutDAO
{
	public function getExcelMemberHackOutInfo(array $params, string $searchField = '*', int $start)
    {
        $this->wheres = $this->binders = [];
        $this->db->bindKeywordByTables(self::COMBINE_SEARCH, $params, $this->binders, $this->wheres, ['tableMemberHackout'], ['mh']);
        $this->db->bindParameterByDateTimeRange('regDt', $params, $this->binders, $this->wheres, $this->tableFunctionName, 'mh');
        $this->wheres[] = "mh.mileage != ''";

        $this->db->strField = $searchField;
        $this->db->strWhere = implode(' AND ', $this->wheres);
        $this->db->strOrder = 'regDt desc';
        //$this->db->strLimit = '?,?';
        //$this->db->bind_param_push($this->binders, 'i', $start);
        //$this->db->bind_param_push($this->binders, 'i', $limit);

        $query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_MEMBER_HACKOUT . ' as mh ' . implode(' ', $query);
        $getData = $this->db->query_fetch($strSQL, $this->binders);
		
        // 탈퇴 회원 아이디 및 마일리지 복호화
        $encryptor = \App::getInstance('encryptor');
        foreach ($getData as $key => $val) {
            $getData[$key]['memId'] = $encryptor->mysqlAesDecrypt($val['memId']);
            $getData[$key]['mileage'] = '-' . $encryptor->mysqlAesDecrypt($val['mileage']);
            $getData[$key]['hackOutFl'] = 'y';
        }

        return $getData;
    }
}