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
namespace Component\Member;

use Component\Database\DBTableField;

/**
 * Class History
 * @package Bundle\Component\Member
 * @author  yjwee
 */
class History extends \Bundle\Component\Member\History
{

	public function insertMemberMileageHistory($arrData)
    {
        $arrBind = $this->db->get_binding(DBTableField::tableMemberMileageHistory(), $arrData, 'insert');
        $this->db->set_insert_db('wg_memberMileageHistroy', $arrBind['param'], $arrBind['bind'], 'y');
        unset($arrData);
			
        return $this->db->insert_id();
    }

	public function getMemberMileageHistory($sno)
	{ 
		$sql = 'SELECT * FROM wg_memberMileageHistroy WHERE memberMileageSno='.$sno.' ORDER BY regDt DESC';
		$historyData = $this->db->query_fetch($sql);
		return $historyData;
	}
}