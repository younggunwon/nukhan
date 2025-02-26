<?php
namespace Widget\Front\Subscription;

class OptionNameWidget extends \Widget\Front\Widget
{

	public function index()
	{
		$db=\App::load(\DB::class);

		$idx=$this->getData("idx");



		$sql="select a.addGoodsNo,a.addGoodsCnt,b.optionValue1,b.optionValue2,b.optionValue3,b.optionValue4,b.optionValue5 from wm_subApplyGoods a INNER JOIN ".DB_GOODS_OPTION." b ON a.optionSno=b.sno where a.idxApply='$idx'";

		$row=$db->fetch($sql);

		if(!empty($row['optionValue1']))
			$optionName.=$row['optionValue1'];
		if(!empty($row['optionValue2']))
			$optionName.="/".$row['optionValue2'];
		if(!empty($row['optionValue3']))
			$optionName.="/".$row['optionValue3'];
		if(!empty($row['optionValue4']))
			$optionName.="/".$row['optionValue4'];
		if(!empty($row['optionValue5']))
			$optionName.="/".$row['optionValue5'];

		$this->setData("optionName",$optionName);

			$addData=[];
			if(!empty($row['addGoodsNo'])){
				
				$addGoodsNo=json_decode(stripslashes($row['addGoodsNo']));

				foreach($addGoodsNo as $key =>$t){
					$addSQL="select * from es_addGoods where addGoodsNo=?";
					$addROW = $db->query_fetch($addSQL,['i',$t],false);

					$addData[]=$addROW['goodsNm'];
					
				}
				
			}
			
			$addGoodsNm="";
			if(count($addData)>0){
				$addGoodsNm=implode(",",$addData);
			}
			$this->setData("addGoodsNm",$addGoodsNm);
		//}

		//echo $sql;

	}
}