<?php

namespace Controller\Admin\Member;

class EncashmentPsController extends \Controller\Admin\Controller
{

    public function index()
    {
		$getValue = \Request::get()->all();
		$postValue = \Request::post()->all();

		if(!$getValue) {
			$getValue = $postValue;
		}

        try {
            switch ($getValue['mode']) {
				case 'excel_bank_down':
					$encashmentClass = \App::load('Component\\Member\\Encashment');
					$encashmentClass->downloadBankExcelList();
				break;
				case 'excel_down':
					$encashmentClass = \App::load('Component\\Member\\Encashment');
					$encashmentClass->getExcelList();
				break;
				case 'excel_upload':
					$encashmentClass = \App::load('Component\\Member\\Encashment');
					$excelCnt = $encashmentClass->uploadExcel();
					
					if($excelCnt['total'] || $excelCnt['success']) {
						echo "<script>parent.$('#resultCnt').append('(전체 ".$excelCnt['total']."개 / 성공 ".gd_isset($excelCnt['success'], 0)."개)')</script>";
					}
					exit;
				break;
            }
        } catch (Exception $e) {
            throw new LayerException($e->getMessage());
        }

		exit;
    }
}
