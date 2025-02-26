<?php
namespace Controller\Front\Subscription;

use App;
use Request;

class OrderDelController extends \Controller\Front\Controller
{
	protected $db="";

	public function pre()
	{
		if(!is_object($this->db)){
			$this->db=App::load(\DB::class);
		}
	
	}
	public function index()
	{
		$in = Request::request()->all();

		if(empty($in['addGoodsNo']) || empty($in['cart_sno']))
			return false;


		$sql="select * from wm_subCart where sno=?";
		$row = $this->db->query_fetch($sql,['i',$in['cart_sno']],false);
		
		$addGoodsNo =json_decode(stripslashes($row['addGoodsNo']));
		$addGoodsCnt =json_decode(stripslashes($row['addGoodsCnt']));

		$newAddGoodsNo=[];
		$newAddGooodsCnt=[];

		
		
		foreach($addGoodsNo as $key =>$val){
			
			if($val==$in['addGoodsNo'])
				continue;

			$newAddGoodsNo[]=$val;
			$newAddGooodsCnt[]=$addGoodsCnt[$key];
		}

		if(count($newAddGoodsNo)>0){
			$addInfo = addslashes(json_encode($newAddGoodsNo));
			$addInfoCnt = addslashes(json_encode($newAddGooodsCnt));

			$upSQL="update wm_subCart set addGoodsNo='{$addInfo}',addGoodsCnt='{$addInfoCnt}' where sno=?";
			$this->db->bind_query($upSQL,['i',$in['cart_sno']]);

		}else{
			$upSQL="update wm_subCart set addGoodsNo='',addGoodsCnt='' where sno=?";
			$this->db->bind_query($upSQL,['i',$in['cart_sno']]);
		}

		exit;
	
	}


}


?>