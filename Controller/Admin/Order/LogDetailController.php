<?php
namespace Controller\Admin\Order;

use App;
use Request;

class LogDetailController extends \Controller\Admin\Controller
{
	public function index()
	{
		$this->getView()->setDefine("layout", "layout_blank");
		
		$in = Request::request()->all();

		$db = App::load(\DB::class);

		$sql="select a.*,(select memNm from ".DB_MEMBER." m where m.memNo=a.memNo) as memNm from wm_user_change_log a where a.idx=?";
		$row=$db->query_fetch($sql,['i',$in['idx']],false);

		//$before_content=stripslashes($row['before_content']);
		$after_content=str_replace("=||=","</br>",$row['after_content']);

		$this->setData("memNm",$row['memNm']);
		$this->setData("before_content",$before_content);
		$this->setData("after_content",$after_content);
	}

}