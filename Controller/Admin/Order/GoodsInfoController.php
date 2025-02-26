<?php
namespace Controller\Admin\Order;

class GoodsInfoController extends \Controller\Admin\Controller
{

	public function index()
	{
		$goodsNo	= \Request::post()->get("goodsNo");
		
		//$goodsNo = "1000000031";
		
		$db			= \App::load(\DB::class);
		$sql		= "select sno,concat(optionValue1,optionValue2,optionValue3,optionValue4,optionValue5) as optionName from ".DB_GOODS_OPTION." where goodsNo=?";
		$optionRow	= $db->query_fetch($sql,['i',$goodsNo]);
		
		$optionInfo = [];
		$addInfo	= [];
		
		foreach($optionRow as $key => $t){
			//$optionInfo[$t['sno']]['optionName'] = $t['optionName'];
			$optionInfo['sno'][]=$t['sno'];
			$optionInfo['optionName'][]=$t['optionName'];
		}
		
		$sql		= "select * from ".DB_GOODS." where goodsNo=?"; 
		$row		= $db->query_fetch($sql,['i',$goodsNo],false);
		
		$addGoods	= json_decode(stripslashes($row['addGoods']));
		
		foreach($addGoods as $key => $val){

			if($key == "addGoods"){
				foreach($val->addGoods as $key2 => $val2){
					
				
					
					$addSQL	= "select * from ".DB_ADD_GOODS." where addGoodsNo=?";
					$addRow	= $db->query_fetch($addSQL,['i',$val2],false);
					$addInfo['goodsNm'][]=$addRow['goodsNm'];
					$addInfo['goodsPrice'][]=number_format($addRow['goodsPrice']);
					$addInfo['goodsNo'][]=$val2;
				}
			}
		}
		
		$result[$goodsNo]['option']=$optionInfo;
		$result[$goodsNo]['addGoods']=$addInfo;
		
		echo json_encode($result);

		exit();
	}

}