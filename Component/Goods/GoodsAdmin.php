<?php

namespace Component\Goods;

use Component\Member\Group\Util as GroupUtil;
use Component\Member\Manager;
use Component\Page\Page;
use Component\Storage\Storage;
use Component\Database\DBTableField;
use Component\Validator\Validator;
use Framework\Debug\Exception\HttpException;
use Framework\Debug\Exception\AlertBackException;
use Framework\File\FileHandler;
use Framework\Utility\ImageUtils;
use Framework\Utility\ProducerUtils;
use Framework\Utility\StringUtils;
use Framework\Utility\ArrayUtils;
use Encryptor;
use Globals;
use LogHandler;
use UserFilePath;
use Request;
use Exception;
use Session;
use App;

class GoodsAdmin extends \Bundle\Component\Goods\GoodsAdmin 
{
	public function setSearchGoods($getValue = null, $list_type = null)
    {
		parent::setSearchGoods($getValue, $list_type);
		
		/** 정기결제 검색 처리 S */
		$this->search['isSubscription'] = $getValue['isSubscription'];
		if ($getValue['isSubscription']) {
			$this->goodsTable = DB_GOODS;
			$this->arrWhere[] = "g.useSubscription = 1";
		}
		/** 정기결제 검색 처리 E */
	}

	//2022.06.17민트웹
    public function saveInfoGoods($arrData)
    {	
		$result = parent::saveInfoGoods($arrData);
		$managerSno=\Session::get('manager.sno');
		if(!empty($managerSno)){
			$db=\App::load(\DB::class);

			$goodsNo=0;

			if($result){

				if($arrData['mode']=="register"){
					$GoodsRow = $db->fetch("select * from ".DB_GOODS." order by regDt DESC limit 0,1");
				}else if($arrData['mode']=="modify"){
					$strSQL="select * from ".DB_GOODS." where goodsNo=?";
					$GoodsRow = $db->query_fetch($strSQL,['i',$arrData['goodsNo']],false);
				}

				$addViewFl=addslashes(json_encode($arrData['addViewFl']));

				$sql="update ".DB_GOODS." set addViewFl='{$addViewFl}' where goodsNo='{$GoodsRow['goodsNo']}'";
				$db->query($sql);
			
				$sql="update ".DB_GOODS_SEARCH." set addViewFl='{$addViewFl}' where goodsNo='{$GoodsRow['goodsNo']}'";
				$db->query($sql);
			}

			
		}

		return $result;
	}

	//2022.06.17민트웹
    public function getDataGoods($goodsNo = null, $taxConf, $applyFl = false)
    {
		$result = parent::getDataGoods($goodsNo, $taxConf, $applyFl);

		$managerSno=\Session::get('manager.sno');
		if(!empty($managerSno)){


			if(!empty($result['data']['addViewFl'])){
			
				$addViewFl = json_decode(stripslashes($result['data']['addViewFl']));

				$addView=[];
				foreach($addViewFl as $key =>$v){
				
					$addView[$key]=$v;

					
				}
				
				$result['data']['addViewFlList']=$addView;
			}
			
		}
		
		//wg-brown 상품 상세 문구 노출
		$result['checked']['goodsDisplayCustomHtmlFl'][$result['data']['goodsDisplayCustomHtmlFl']] = "checked='checked'";
		$result['checked']['goodsViewTextCustomSameFl'][$result['data']['goodsViewTextCustomSameFl']] = "checked='checked'";
		//wg-brown 상품 상세 문구 노출

		return $result;
	}

	public function goodsOptionTempRegister($data)
    {
        gd_isset($this->goodsTable, DB_GOODS);

        //임시 세션값이 없다면 생성
        if (empty($data['sess'])) {
            do {
                //임의의 32자리 값 생성
                $sessionString = '';
                for ($i = 0; $i < 32; $i++) {
                    $tmpChar = '';
                    switch (rand(0, 2)) {
                        case 0:
                            $tmpChar = chr(rand(48, 57));
                            break;
                        case 1:
                            $tmpChar = chr(rand(65, 90));
                            break;
                        case 2:
                            $tmpChar = chr(rand(97, 122));
                            break;
                    }
                    $sessionString .= $tmpChar;

                }
                //중복값 검색
                $arrBind = [];
                $strWhere = 'session = ?';
                $this->db->bind_param_push($arrBind['bind'], 's', $sessionString);
                $strSQL = "SELECT COUNT(`session`) cnt FROM " . DB_GOODS_OPTION_TEMP . " WHERE " . $strWhere;
                $getData = $this->db->query_fetch($strSQL, $arrBind['bind'], false);
                unset($arrBind);
                $dataOption = $getData;
            } while ($dataOption['cnt'] != '0');
        }else{
            $sessionString = $data['sess'];
        }

        $arrBind = [];
        //이전 값은 삭제 할 것
        $strSQL = 'DELETE FROM '.DB_GOODS_OPTION_TEMP.' WHERE session = ?';
        $this->db->bind_param_push($arrBind['bind'], 's', $sessionString);
        $this->db->bind_query($strSQL, $arrBind['bind']);
        unset ($arrBind);

        $stocked = false;
        $stock = 0;
        foreach ($data['optionY']['optionValueText'] as $k => $v) {
            $optionData['session'] = $sessionString;
            $optionData['optionValue1'] = $data['optionY']['optionName'][0];
            $optionData['optionValue2'] = $data['optionY']['optionName'][1];
            $optionData['optionValue3'] = $data['optionY']['optionName'][2];
            $optionData['optionValue4'] = $data['optionY']['optionName'][3];
            $optionData['optionValue5'] = $data['optionY']['optionName'][4];
            $optionData['optionDisplayFl'] = $data['optionY']['optionDisplayFl'];
            $optionData['optionImagePreviewFl'] = $data['optionImagePreviewFl'];
            $optionData['optionImageDisplayFl'] = $data['optionImageDisplayFl'];
            $optionData['optionValueText'] = $v;
            $optionData['optionCostPrice'] = $data['optionY']['optionCostPrice'][$k];
            $optionData['optionPrice'] = $data['optionY']['optionPrice'][$k];
            $optionData['stockCnt'] = $data['optionY']['stockCnt'][$k];
            if($data['optionY']['stockCnt'][$k] > 0 && !empty($data['optionY']['stockCnt'][$k])){
                $stocked = true;
                if($data['optionY']['optionSellFl'][$k] == 'y'){
                    $stock += $data['optionY']['stockCnt'][$k];
                }
            }
            $optionData['sellStopFl'] = $data['optionY']['optionStopFl'][$k];
            $optionData['sellStopStock'] = $data['optionY']['optionStopCnt'][$k];
            $optionData['confirmRequestFl'] = $data['optionY']['optionRequestFl'][$k];
            $optionData['confirmRequestStock'] = $data['optionY']['optionRequestCnt'][$k];
            $optionData['optionViewFl'] = $data['optionY']['optionViewFl'][$k];
            $optionData['optionStockFl'] = $data['optionY']['optionSellFl'][$k];
            if($optionData['optionStockFl'] != 'y' && $optionData['optionStockFl'] != 'n'){
                $optionData['optionStockFl'] = 't';
                $optionData['optionStockCode'] = $data['optionY']['optionSellFl'][$k];
            }
            $optionData['optionDeliveryFl'] = $data['optionY']['optionDeliveryFl'][$k];
            if($optionData['optionDeliveryFl'] != 'normal'){
                $optionData['optionDeliveryFl'] = 't';
                $optionData['optionDeliveryCode'] = $data['optionY']['optionDeliveryFl'][$k];
            }
            $optionData['optionCode'] = $data['optionY']['optionCode'][$k];
            $optionData['optionMemo'] = $data['optionY']['optionMemo'][$k];

			//2024-01-23 루딕스-brown 옵션 추천인 적립금
			$optionData['optionNoRecomMileage'] = $data['optionY']['optionNoRecomMileage'][$k];
			$optionData['optionNoRecomMileageUnit'] = $data['optionY']['optionNoRecomMileageUnit'][$k];
			$optionData['optionSubRecomMileage'] = $data['optionY']['optionSubRecomMileage'][$k];
            $optionData['optionSubRecomMileageUnit'] = $data['optionY']['optionSubRecomMileageUnit'][$k];
			//2024-01-23 루딕스-brown 옵션 추천인 적립금

            $optionData['regDt'] = date('Y-m-d H:i:s');

            $arrBind = $this->db->get_binding(DBTableField::tableGoodsOptionTemp(), $optionData, 'insert');
            $this->db->set_insert_db(DB_GOODS_OPTION_TEMP, $arrBind['param'], $arrBind['bind'], 'y');

            unset($arrBind);
            unset($optionData);
        }

        $optionImage = array();
        $optionImageChanged = array();
        $optionImgPath = App::getUserBasePath() . '/data/goods/option_temp';
        //옵션 이미지 업로드 하기
        $arrFileData = Request::files()->get('optionYIcon');
        if ($arrFileData['name']['goodsImage']) {
            foreach ($arrFileData['name']['goodsImage'] as $fKey => $fVal) {
                foreach ($fVal as $vKey => $vVal) {
                    if (gd_file_uploadable($arrFileData, 'image', 'goodsImage', $fKey, $vKey) === true) {
                        if(file_exists($optionImgPath) === false){
                            //디렉토리 생성
                            mkdir($optionImgPath);
                        }
                        $fileExt = explode('.', $arrFileData['name']['goodsImage'][$fKey][$vKey]);
                        $fileExt = $fileExt[count($fileExt)-1];
                        move_uploaded_file($arrFileData['tmp_name']['goodsImage'][$fKey][$vKey], $optionImgPath.DIRECTORY_SEPARATOR.$sessionString.'_'.$vKey.'.'.$fileExt);
                    }
                    if ($data['optionImageAddUrl'] == 'y' && !empty($data['optionYIcon']['goodsImageText'][0][$vKey])) {
                        $optionImage[$vKey] = $data['optionYIcon']['goodsImageText'][0][$vKey];
                        $optionImageChanged[$vKey] = $data['optionYIcon']['goodsImageTextChanged'][0][$vKey];
                    } else if (gd_file_uploadable($arrFileData, 'image', 'goodsImage', $fKey, $vKey) === true) {
                        $optionImage[$vKey] = $sessionString.'_'.$vKey.'.'.$fileExt;
                        $optionImageChanged[$vKey] = 'y';
                    } else {
                        $optionImage[$vKey] = '';
                        $optionImageChanged[$vKey] = $data['optionYIcon']['goodsImageTextChanged'][0][$vKey];
                    }
                }
            }
        }

        //임시 파일 테이블에 등록
        foreach($optionImage as $key => $value){
            //이전 값은 삭제 할 것
            $strSQL = 'DELETE FROM '.DB_GOODS_OPTION_ICON_TEMP.' WHERE session = ? AND optionNo = ?';
            $this->db->bind_param_push($arrBind['bind'], 's', $sessionString);
            $this->db->bind_param_push($arrBind['bind'], 's', $key);
            $this->db->bind_query($strSQL, $arrBind['bind']);
            unset ($arrBind);

            //임시로 등록된 파일 가져오기
            $optionImgData['session'] = $sessionString;
            $optionImgData['optionNo'] = $key;
            $optionImgData['optionValue'] = $data['optionY']['optionValue'][0][$key];
            $optionImgData['goodsImage'] = $optionImage[$key];
            $optionImgData['isUpdated'] = $optionImageChanged[$key];

            $arrBind = $this->db->get_binding(DBTableField::tableGoodsOptionIconTemp(), $optionImgData, 'insert');
            $this->db->set_insert_db(DB_GOODS_OPTION_ICON_TEMP, $arrBind['param'], $arrBind['bind'], 'y');
            unset ($arrBind);
            unset ($optionImgData);
        }

        $return['stocked'] = $stocked;
        $return['stock'] = $stock;
        $return['sessionString'] = $sessionString;
        return $return;
    }

	public function getDataGoodsOptionTemp($goodsNo = null, $tmpSession)
    {
	
        if($goodsNo != null){
            $getData = $this->getDataGoodsOption($goodsNo);
        }
        unset($getData['data']['optionName']); //옵션이름 배열
        unset($getData['data']['optionDisplayFl']); //옵션 노출방식
        unset($getData['data']['optionImagePreviewFl']); //옵션 이미지 노출 설정 미리보기 사용
        unset($getData['data']['optionImageDisplayFl']); //옵션 이미지 노출 설정 상세 이미지에 추가
        unset($getData['data']['optionCnt']); //옵션 개수
        unset($getData['data']['option']); //실제 옵션 배열

        //옵션이름 불러오기
        $arrField = DBTableField::setTableField('tableGoodsOptionTemp');
        $arrWhere[] = 'session=?';
        $this->db->strField = implode(', ', $arrField);
        $this->db->strWhere = implode(' AND ', $arrWhere);

        $this->db->bind_param_push($arrBind, 's', $tmpSession);

        $query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM ' . DB_GOODS_OPTION_TEMP . implode(' ', $query);
        $data = $this->db->query_fetch($strSQL, $arrBind);
        for($i=1; $i<=5; $i++){
            if(!empty($data[0]['optionValue'.$i])){
                $tmpOptionName[] = $data[0]['optionValue'.$i];
            }
        }
        $getData['data']['optionName'] = $tmpOptionName; //옵션 이름 설정
        $getData['data']['optionCnt'] = count($tmpOptionName);

        unset($getData['checked']['optionDisplayFl']);
        unset($getData['checked']['optionImagePreviewFl']);
        unset($getData['checked']['optionImageDisplayFl']);

        $getData['checked']['optionDisplayFl'][$data[0]['optionDisplayFl']] = 'checked';
        $getData['checked']['optionImagePreviewFl'][$data[0]['optionImagePreviewFl']] = 'checked';
        $getData['checked']['optionImageDisplayFl'][$data[0]['optionImageDisplayFl']] = 'checked';

        //option 생성
        foreach($data as $k => $v){
            $optionTemp[$k]['sno'] = '';
            $optionTemp[$k]['optionNo'] = $k+1;
            $optionTemp[$k]['optionPrice'] = $v['optionPrice'];
            $optionTemp[$k]['optionCostPrice'] = $v['optionCostPrice'];
            $optionTemp[$k]['optionCode'] = $v['optionCode'];
            $optionTemp[$k]['optionViewFl'] = $v['optionViewFl'];
            $optionTemp[$k]['optionSellFl'] = $v['optionStockFl'];
            $optionTemp[$k]['stockCnt'] = $v['stockCnt'];
            $optionValueTemp = explode(STR_DIVISION, $v['optionValueText']);
            $optionTemp[$k]['optionValue1'] = $optionValueTemp[0];
            $optionTemp[$k]['optionValue2'] = $optionValueTemp[1];
            $optionTemp[$k]['optionValue3'] = $optionValueTemp[2];
            $optionTemp[$k]['optionValue4'] = $optionValueTemp[3];
            $optionTemp[$k]['optionValue5'] = $optionValueTemp[4];
            $optionTemp[$k]['optionMemo'] = $v['optionMemo'];
            $optionTemp[$k]['optionSellCode'] = $v['optionStockCode'];
            $optionTemp[$k]['optionDeliveryCode'] = $v['optionDeliveryCode'];
            $optionTemp[$k]['optionDeliveryFl'] = $v['optionDeliveryFl'];
            $optionTemp[$k]['sellStopFl'] = $v['sellStopFl'];
            $optionTemp[$k]['sellStopStock'] = $v['sellStopStock'];
            $optionTemp[$k]['confirmRequestFl'] = $v['confirmRequestFl'];
            $optionTemp[$k]['confirmRequestStock'] = $v['confirmRequestStock'];
			
			//루딕스-brown 옵션 추천인 적립금 
			$optionTemp[$k]['optionNoRecomMileage'] = $v['optionNoRecomMileage'];
            $optionTemp[$k]['optionNoRecomMileageUnit'] = $v['optionNoRecomMileageUnit'];
			$optionTemp[$k]['optionSubRecomMileage'] = $v['optionSubRecomMileage'];
            $optionTemp[$k]['optionSubRecomMileageUnit'] = $v['optionSubRecomMileageUnit'];
        }
        $getData['data']['option'] = $optionTemp;

        //optVal 생성
        foreach($data as $k => $v){
            $optValTemp[] = explode(STR_DIVISION, $v['optionValueText']);
        }
        foreach($optValTemp as $k => $v){
            for($i=0; $i<count($tmpOptionName); $i++){
                $optValTempExp[$i][] = $v[$i];
            }
        }
        for($i=1; $i<=5; $i++){
            $optVal[] = array();
        }
        foreach($optValTempExp as $k => $v){
            $optVal[$k+1] = array_unique($v);
        }

        $getData['data']['option']['optVal'] = $optVal;

        $getData['data']['optionIcon'] = '';
        $getData['data']['image'] = '';
        $getData['data']['mode'] = 'modify';
        $getData['data']['optionFl'] = 'y';
        $getData['data']['optionCnt'] = count($tmpOptionName);
        $getData['data']['optionValCnt'] = count($optVal);

        // 상품 리스트 품절, 노출 PC/mobile, 미노출 PC/mobile 카운트 쿼리
        if($goodsAdminGridMode == 'goods_list') {
            $dataStateCount = [];
            $dataStateCountQuery = [
                'pcDisplayCnt' => " g.goodsDisplayFl='y'",
                'mobileDisplayCnt' => " g.goodsDisplayMobileFl='y'",
                'pcNoDisplayCnt' => " g.goodsDisplayFl='n'",
                'mobileNoDisplayCnt' => " g.goodsDisplayMobileFl='n'",
            ];
            foreach ($dataStateCountQuery as $stateKey => $stateVal) {
                if($page->hasRecodeCache($stateKey)) {
                    $dataStateCount[$stateKey]  = $page->getRecodeCache($stateKey);
                    continue;
                }
                $dataStateSQL = " SELECT COUNT(g.goodsNo) AS cnt FROM " . $this->goodsTable . " as g WHERE  " . $stateVal . " AND g.delFl ='n'" . $scmWhereString;
                $dataStateCount[$stateKey] = $this->db->query_fetch($dataStateSQL)[0]['cnt'];
                $page->recode[$stateKey] = $dataStateCount[$stateKey];
            }
            // 품절의 경우 OR 절 INDEX 경유하지 않기에 별도 쿼리 실행 - DBA
            //                    if(!\Request::get()->get('__soldOutCnt')) {
            if($page->hasRecodeCache('soldOutCnt') === false) {
                $dataStateSoldOutSql = "select sum(cnt) as cnt from ( SELECT count(1) AS cnt FROM  " . $this->goodsTable . "  as g1 WHERE   g1.soldOutFl = 'y' AND g1.delFl ='n' union all SELECT count(1) AS cnt FROM  " . $this->goodsTable . "  as g2 WHERE  g2.soldOutFl = 'n' and g2.stockFl = 'y' AND g2.totalStock <= 0  AND g2.delFl ='n') gQ";
                $dataStateCount['soldOutCnt'] = $this->db->query_fetch($dataStateSoldOutSql)[0]['cnt'];
                $page->recode['soldOutCnt'] = $dataStateCount['soldOutCnt'];
            }
            else {
                $dataStateCount['soldOutCnt'] = $page->getRecodeCache('soldOutCnt');
            }
            $getData['stateCount'] = $dataStateCount;
        }

        //기본값이 없을 경우
        if(empty($getData['checked']['optionDisplayFl'])) $getData['checked']['optionDisplayFl'] = 's'; //옵션 노출방식

        return $getData;
    }
}