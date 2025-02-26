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
namespace Component\Agreement;

use App;
use Component\Mall\Mall;
use Component\AbstractComponent;
use Component\Database\DBTableField;
use Component\Policy\BaseAgreementPolicy;
use Component\Validator\Validator;
use Exception;
use Framework\Database\DBTool;
use Framework\Debug\Exception\AlertBackException;
use Framework\Debug\Exception\Except;
use Framework\Debug\Exception\LayerException;
use Framework\StaticProxy\Proxy\FileHandler;
use Framework\Utility\ArrayUtils;
use Framework\Utility\StringUtils;
use Logger;
use Request;
use Session;
/**
 * Class 안내문 관리
 * @package Bundle\Component\Agreement
 * @author  yjwee
 */
class BuyerInform extends \Bundle\Component\Agreement\BuyerInform
{
	private $mall;
    private $saveMallSno = 1;

    public function __construct(DBTool $db = null)
    {
        parent::__construct($db);
        $this->tableFunctionName = 'tableMember';
        $this->mall = new Mall();
    }


	public function saveInformData($code, $content, $mallSno = DEFAULT_MALL_NUMBER)
    {
        // 테이블명 반환
        $tableName = $this->mall->getTableName(DB_BUYER_INFORM, $mallSno);
        $this->saveMallSno = $mallSno;

        $postValue = Request::post()->toArray();
        $newInformFl = gd_isset($postValue['newInformFl'], 'n');
        if ($newInformFl === 'y' && empty($postValue['informNm'])) {
            throw new LayerException(__('약관명을 입력해주세요.'));
        }

        $vo = new BuyerInformVo($code);
        $vo->setContent($content);
        $privateCodeFl = StringUtils::contains($code, BuyerInformCode::BASE_PRIVATE);
        if ($privateCodeFl) {
            if (empty($postValue['informNm']) === false) {
                $vo->setInformNm(strip_tags($postValue['informNm']));
            }
            $selectResult = $this->getInformDataArray(BuyerInformCode::BASE_PRIVATE, 'sno', false, $mallSno);
            $selectResult = ArrayUtils::getSubArrayByKey($selectResult, 'sno');
            $selectCount = count($selectResult);
            if ($newInformFl === 'y') {
                $selectCount++;
            }
            $informCount = str_pad($selectCount, 3, '0', STR_PAD_LEFT);
            $informCd = $informCount == '001' ? BuyerInformCode::BASE_PRIVATE : BuyerInformCode::BASE_PRIVATE . $informCount;
            $vo->setInformCd($informCd);
        }

        if($code == BuyerInformCode::PRIVATE_MARKETING) {
            $vo->setInformNm(strip_tags($postValue['privateMarketingWriteTitle']));
            $vo->setModeFl(gd_isset($postValue['privateMarketingFl'], 'n'));
        }
		
		//루딕스-brown 페이백 개인정보동의
		if($code == '001012') {
			$vo->setGroupCd('001');
            $vo->setInformNm('페이백 개인정보동의');
			$vo->setInformCd('001012');
        }

        $strSQL = "SELECT sno, displayShopFl FROM " . $tableName . " WHERE informCd='" . $code . "'";
        if ($mallSno > DEFAULT_MALL_NUMBER) {
            $strSQL .= " AND mallSno='" . $mallSno . "'";
        }
        $result = $this->db->fetch($strSQL);
        if ($this->db->num_rows(false) && $newInformFl !== 'y') {
            if ($privateCodeFl && empty($result['displayShopFl']) === false) {
                $vo->setDisplayShopFl($result['displayShopFl']);
            }
            $this->_update($vo);
        } else {
            if ($privateCodeFl) {
                $vo->setDisplayShopFl('y');
            }
            $this->_insert($vo);
        }
    }

	/**
     * _update
     *
     * @param BuyerInformVo $vo
     * @param array $includeField
     *
     * @throws Exception
     */
    private function _update(BuyerInformVo $vo, $includeField = null)
    {
        Logger::info(__METHOD__);
        BuyerInform::validateUpdate($vo);
        $excludeField = 'scmNo,informCd,groupCd,regDt';
        if (empty($vo->getInformNm())) {
            $excludeField .= ',informNm';
        }

        // 테이블명 반환
        $tableName = $this->mall->getTableName(DB_BUYER_INFORM, $this->saveMallSno);

        $bindArray = $this->db->get_binding(DBTableField::tableBuyerInform(), $vo->toArray(), 'update', $includeField, explode(',', $excludeField));
        if ($vo->getSno() === null) {
            $this->db->bind_param_push($bindArray['bind'], 'i', $vo->getInformCd());
            $whereIs = 'informCd = ?';
            if ($this->saveMallSno > DEFAULT_MALL_NUMBER) {
                $this->db->bind_param_push($bindArray['bind'], 'i', $this->saveMallSno);
                $whereIs .= ' AND mallSno = ?';
            }
            $this->db->set_update_db($tableName, $bindArray['param'], $whereIs, $bindArray['bind'], false);
        } else {
            $this->db->bind_param_push($bindArray['bind'], 'i', $vo->getSno());
            $whereIs = 'sno = ?';
            if ($this->saveMallSno > DEFAULT_MALL_NUMBER) {
                $this->db->bind_param_push($bindArray['bind'], 'i', $this->saveMallSno);
                $whereIs .= ' AND mallSno = ?';
            }
            $this->db->set_update_db($tableName, $bindArray['param'], $whereIs, $bindArray['bind'], false);
        }
    }

	/**
     * 등록
     *
     * @param BuyerInformVo $vo
     *
     * @throws Exception
     */
    private function _insert(BuyerInformVo $vo)
    {
        Logger::info(__METHOD__);
        BuyerInform::validateInsert($vo);

        // 테이블명 반환
        $tableName = $this->mall->getTableName(DB_BUYER_INFORM, $this->saveMallSno);

        $arrBind = $this->db->get_binding(DBTableField::tableBuyerInform(), $vo->toArray(), 'insert');
        if ($this->saveMallSno > DEFAULT_MALL_NUMBER) {
            $arrBind['param'][] = 'mallSno';
            $arrBind['bind'][0] .= 'i';
            $arrBind['bind'][] = $this->saveMallSno;
        }
        $this->db->set_insert_db($tableName, $arrBind['param'], $arrBind['bind'], 'y');
    }
}