<?php

namespace Controller\Admin\Policy;

use App;
use Request;
use Framework\Debug\Exception\AlertOnlyException;
use Component\Database\DBTableField;

/**
* 카드진열설정 DB 처리 
*
* @author webnmobile
*/
class IndbCardSettingController extends \Controller\Admin\Controller 
{
	public function index()
	{
		try {
			$in = Request::request()->all();
			$db = App::load(\DB::class);
			switch ($in['mode']) {
				/* 카드 진열 정보 업데이트 */
				case "update_card" : 
					$path = dirname(__FILE__) . "/../../../../data/cards/";
					$files = Request::files()->toArray();
					$files = $files['file'];
					foreach ($in['cardCode'] as $code) {
						$f = $path . $in['cardType']."_".$code;
						
						if ($in['deleteImage'][$code]) {
							@unlink($f);
						}
							
						if ($files['tmp_name'][$code] && empty($files['error'][$code]) && preg_match("/^image/", $files['type'][$code])) {
							
							move_uploaded_file($files['tmp_name'][$code], $f);
						}
						
						$setData = [
							'cardNm' => $in['cardNm'][$code],
							'backgroundColor' => $in['backgroundColor'][$code],
							'fontColor' => $in['fontColor'][$code],
							'cardCode' => $code,
							'cardType' => $in['cardType'],
						];
						
						$db->set_delete_db("wm_cardSet", "cardType = ? AND cardCode = ?", ["ss", $in['cardType'], $code]);												
						$arrBind = $db->get_binding(DBTableField::tableWmCardSet(), $setData, "insert", array_keys($setData));
						$db->set_insert_db("wm_cardSet", $arrBind['param'], $arrBind['bind'], "y");

					}
					
					return $this->layer("설정되었습니다.");
					break;
			}
		} catch (AlertOnlyException $e) {
			throw $e;
		}
		exit;
	}
}