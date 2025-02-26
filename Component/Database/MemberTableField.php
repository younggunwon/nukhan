<?php

namespace Component\Database;

class MemberTableField extends \Bundle\Component\Database\MemberTableField
{
	public function tableMember()
    {
		$arrField = parent::tableMember();
		$arrField[] = ['val' => 'recomMileagePayFl', 'typ' => 's', 'def' => 'n'];
		return $arrField;
	}
}