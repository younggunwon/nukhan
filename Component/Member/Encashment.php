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

use Framework\Debug\Exception\AlertBackException;
use App;
use Bundle\Component\Apple\AppleLogin;
use Bundle\Component\Godo\GodoKakaoServerApi;
use Component\Database\DBTableField;
use Component\Facebook\Facebook;
use Component\Godo\GodoNaverServerApi;
use Component\Godo\GodoPaycoServerApi;
use Component\Godo\GodoWonderServerApi;
use Component\GoodsStatistics\GoodsStatistics;
use Component\Mail\MailMimeAuto;
use Component\Member\Group\Util as GroupUtil;
use Component\Member\Util\MemberUtil;
use Component\Sms\Code;
use Component\Sms\SmsAutoCode;
use Component\Sms\SmsAutoObserver;
use Component\Validator\Validator;
use Encryptor;
use Exception;
use Framework\Debug\Exception\AlertCloseException;
use Framework\Debug\Exception\AlertRedirectException;
use Framework\Object\SimpleStorage;
use Framework\Utility\ArrayUtils;
use Framework\Utility\ComponentUtils;
use Framework\Utility\DateTimeUtils;
use Framework\Utility\ImageUtils;
use Framework\Utility\SkinUtils;
use Framework\Utility\StringUtils;
use Framework\Security\Digester;
use Framework\Utility\GodoUtils;
use Globals;
use Logger;
use Request;
use Session;
use UserFilePath;
use Component\Member\MemberDAO;
use Component\Sms\SmsAuto;
use DB;
use Vendor\Spreadsheet\Excel\Reader as SpreadsheetExcelReader;

/**
 * Class 회원 관리
 * @package Bundle\Component\Member
 * @author  yjwee
 */
class Encashment 
{
	public $bankCode = [
		'KB국민은행' => '004',
		'경남은행' => '039',
		'광주은행' => '034',
		'기업은행' => '003',
		'농협은행' => '011',
		'대구은행' => '031',
		'부산은행' => '032',
		'산업은행' => '002',
		'저축은행' => '050',
		'새마을금고' => '045',
		'수협은행' => '007',
		'신한은행' => '088',
		'신협은행' => '048',
		'씨티은행' => '027',
		'KEB하나은행' => '081',
		'우리은행' => '020',
		'우체국' => '071',
		'전북은행' => '037',
		'SC제일은행' => '023',
		'제주은행' => '035',
		'HSBC' => '054',
		'케이뱅크' => '089',
		'카카오뱅크' => '090',
		'토스뱅크' => '092',
	];

	public $refusFl = [
		1 => '계좌번호 조회 실패',
		2 => '계좌번호 명의 불일치',
		3 => '입금 실패',
	];
	protected $db;
	
	public function __construct()
    {
        if (!is_object($this->db)) {
            $this->db = \App::load('DB');
        }
    }
	public function mypageEncashmentList($memNo)
	{
		$getValue = Request::get()->toArray();
		
		$page = gd_isset($getValue['page'], 1);
		$pageNum = gd_isset($getValue['pageNum'], 10);
		// --- 정렬 설정
       $sort = 'me.regDt desc';

        $this->db->strField = ' count(*) as cnt';
		$this->db->strWhere = 'me.memNo = '.$memNo;

        $query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM wg_mileageEncashment as me ' . implode(' ', $query);
        list($result) = $this->db->query_fetch($strSQL);
        $totalCnt = $result['cnt'];

        $page = \App::load('\\Component\\Page\\Page', $page);
        $page->page['list'] = $pageNum; // 페이지당 리스트 수
        $page->recode['amount'] = $totalCnt; // 전체 레코드 수
        $page->setPage();
        $page->setUrl(\Request::getQueryString());
		
		$this->db->strField = ' me.*';
		$this->db->strOrder = $sort;
		$this->db->strLimit = $page->recode['start'] . ',' . $pageNum;

		//하위, 상위회원 탭에 따른 where
		$arrWhere[] = 'me.memNo = '.$memNo;
		if($getValue['applyFl'] && $getValue['applyFl'] !='a' ) {
			$arrWhere[] = 'me.applyFl = "'.$getValue['applyFl'].'"';
		}
		$this->db->strWhere = implode(' AND ', $arrWhere);
		// 검색 카운트
		$strSQL = ' SELECT COUNT(*) AS cnt FROM wg_mileageEncashment as me  WHERE ' . $this->db->strWhere;
		$res = $this->db->query_fetch($strSQL, null, false);
		$page->recode['total'] = $res['cnt']; // 검색 레코드 수
		$page->setPage();
		$page->setUrl(\Request::getQueryString());

		$query = $this->db->query_complete();
		$strSQL = 'SELECT ' . array_shift($query) . ' FROM wg_mileageEncashment as me ' . implode(' ', $query);
		$data = $this->db->query_fetch($strSQL);

		foreach($data as $key => $val) {
			$data[$key]['refusFl'] = $this->refusFl[$val['refusFl']];
		}

        // 각 데이터 배열화
        $getData['data'] = gd_htmlspecialchars_stripslashes(gd_isset($data));
        return $getData;
	}

	# 리스트 조회
	public function getEncashmentList() {
		$getValue = Request::get()->toArray();
        // --- 검색 설정
        $this->setSearchEncashment($getValue);
		
        
		// --- 정렬 설정
		$sort = gd_isset($getValue['sort']);
        if (empty($sort)) {
            $sort = 'me.regDt desc';
        }
        
		gd_isset($getValue['page'], 1);
		gd_isset($getValue['pageNum'], 10);

        $this->db->strField = " count(*) as cnt";
        $query = $this->db->query_complete();
        $strSQL = 'SELECT ' . array_shift($query) . ' FROM wg_mileageEncashment as me ' . implode(' ', $query);
        list($result) = $this->db->query_fetch($strSQL);
        $totalCnt = $result['cnt'];

        $page = \App::load('\\Component\\Page\\Page', $getValue['page']);
        $page->page['list'] = $getValue['pageNum']; // 페이지당 리스트 수
        $page->recode['amount'] = $totalCnt; // 전체 레코드 수
        $page->setPage();
        $page->setUrl(\Request::getQueryString());
		
		//$this->db->strJoin = ' LEFT JOIN wg_consignment c ON cg.consignmentNo = c.consignmentNo ';
		$this->db->strField = " me.* ";
		$this->db->strJoin = ' LEFT JOIN es_member m ON m.memNo = me.memNo ';
		$this->db->strWhere = implode(' AND ', gd_isset($this->arrWhere));
		$this->db->strOrder = $sort;
		$this->db->strLimit = $page->recode['start'] . ',' . $getValue['pageNum'];

		// 검색 카운트
		$strSQL = ' SELECT COUNT(*) AS cnt FROM wg_mileageEncashment as me '.$this->db->strJoin.' WHERE ' . $this->db->strWhere;
		$res = $this->db->query_fetch($strSQL, $this->arrBind, false);
		
		$page->recode['total'] = $res['cnt']; // 검색 레코드 수
		$page->setPage();
		$page->setUrl(\Request::getQueryString());

		$query = $this->db->query_complete();
		$strSQL = 'SELECT ' . array_shift($query) . ' FROM wg_mileageEncashment as me ' . implode(' ', $query);
		$data = $this->db->query_fetch($strSQL, $this->arrBind);

		//gd_debug($this->db->getBindingQueryString($strSQL, $this->arrBind));
		//gd_debug($data);

		foreach($data as $key => $val) {
			$data[$key]['refusFl'] = $this->refusFl[$val['refusFl']];
		}

        // 각 데이터 배열화
        $getData['data'] = gd_htmlspecialchars_stripslashes(gd_isset($data));
        $getData['sort'] = $sort;
        $getData['search'] = gd_htmlspecialchars($this->search);
        $getData['checked'] = $this->checked;
        $getData['selected'] = $this->selected;
        return $getData;
	}

	public function setSearchEncashment($searchData, $searchPeriod = '-1') {
		$this->search['applyFl'] = gd_isset($searchData['applyFl'], 'a');
        $this->search['sort'] = gd_isset($searchData['sort'], 'me.regDt desc');
        $this->search['detailSearch'] = gd_isset($searchData['detailSearch']);
        $this->search['searchDateFl'] = gd_isset($searchData['searchDateFl'], 'regDt');
        $this->search['searchPeriod'] = gd_isset($searchData['searchPeriod'], '-1');
		$this->search['category'] = gd_isset($searchData['category'], 'all');

        $this->search['key'] = gd_isset($searchData['key']);
        $this->search['keyword'] = gd_isset($searchData['keyword']);

        if ($this->search['searchPeriod'] < 0) {
            $this->search['searchDate'][] = '0000-00-00 00:00:00';
            $this->search['searchDate'][] = '9999-12-31 23:59:59';
        } else {
            $this->search['searchDate'][] = gd_isset($searchData['searchDate'][0], date('Y-m-d', strtotime('-6 day')));
            $this->search['searchDate'][] = gd_isset($searchData['searchDate'][1], date('Y-m-d'));
        }
		
		//승인여부
		if($this->search['applyFl'] && $this->search['applyFl'] != 'a') {
			$this->arrWhere[] = 'me.applyFl = "'.$this->search['applyFl'].'"';
		}
		$this->checked['applyFl'][$this->search['applyFl']] = "checked='checked'";
		$this->checked['searchPeriod'][$this->search['searchPeriod']] = "active";
        $this->selected['searchDateFl'][$this->search['searchDateFl']] = $this->selected['sort'][$this->search['sort']] = "selected='selected'";

        // 처리일자 검색
        if ($this->search['searchDateFl'] && $this->search['searchDate'][0] && $this->search['searchDate'][1]) {
            $this->arrWhere[] = 'me.' . $this->search['searchDateFl'] . ' BETWEEN ? AND ?';
            $this->db->bind_param_push($this->arrBind, 's', $this->search['searchDate'][0] . ' 00:00:00');
            $this->db->bind_param_push($this->arrBind, 's', $this->search['searchDate'][1] . ' 23:59:59');
        }

		if($this->search['keyword']) {
			if($this->search['key'] == 'all') {
				$tmpWhere = array('m.memId', 'me.applicant', 'me.cellPhone');
                $arrWhereAll = array();
                foreach ($tmpWhere as $keyNm) {
                    $arrWhereAll[] = '(' . $keyNm . ' LIKE concat(\'%\',?,\'%\'))';
                    $this->db->bind_param_push($this->arrBind, 's', $this->search['keyword']);
                }
                $this->arrWhere[] = '(' . implode(' OR ', $arrWhereAll) . ')';
			} else {
				if($this->search['key'] == 'memId') {
					$this->arrWhere[] = '(m.' . $this->search['key'] . ' LIKE concat(\'%\',?,\'%\'))';
					$this->db->bind_param_push($this->arrBind, 's', $this->search['keyword']);
				} else {
					$this->arrWhere[] = '(me.' . $this->search['key'] . ' LIKE concat(\'%\',?,\'%\'))';
					$this->db->bind_param_push($this->arrBind, 's', $this->search['keyword']);
				}
			}
		}

        if (empty($this->arrBind)) {
            $this->arrBind = null;
        }
	}

	public function getExcelList() {
		header( "Content-type: application/vnd.ms-excel" );   
		header( "Content-type: application/vnd.ms-excel; charset=utf-8");  
		header( "Content-Disposition: attachment; filename = member_payback_".date('Y-m-d H:i:s').".xls" );   
		header( "Content-Description: PHP4 Generated Data" );

		$data = $this->getEncashmentList();
		
		// 테이블 상단 만들기
		$EXCEL_STR = "  
		<table border='1'>
		<tr> 
			<td>고유번호</td>
			<td>은행코드</td>
			<td>계좌번호</td>
			<td>금액</td>
			<td>신청인</td>  
			<td>지급여부</td>
			<td>지급거절사유</td>
			<td>신청일</td>
			<td>휴대폰번호</td>
		</tr>";

		foreach($data['data'] as $key => $val) {
			if($val['applyFl'] == 'n') {
				 $val['applyFl'] = '페이백 거절';
			} else if ($val['applyFl'] == 'y') {
				$val['applyFl'] = '페이백 지급완료';
			} else {
				$val['applyFl'] = '페이백 신청';
			}
			$bankCode = $this->bankCode[$val['bankNm']];

			$EXCEL_STR .= "  
			<tr>  
				<td>".$val['sno']."</td>
				<td>".$bankCode."</td>
				<td style='mso-number-format:\"\@\"'>".$val['accountNum']."</td>
				<td style='mso-number-format:\"#,##0\"'>".$val['encashmentPrice']."</td>
				<td>".$val['applicant']."</td>
				<td>".$val['applyFl']."</td>
				<td>".$val['refusFl']."</td>
				<td>".gd_date_format('Y-m-d', $val['regDt'])."</td>
				<td>".$val['cellPhone']."</td>	
			</tr>  
			";
		}
		$EXCEL_STR .= "</table>";  

		echo "<meta content=\"application/vnd.ms-excel; charset=UTF-8\" name=\"Content-type\"> ";  
		echo $EXCEL_STR;
	}

	public function uploadExcel() {
		if(!\Request::files()->get('excel')['tmp_name']){
			 throw new AlertBackException(__('등록한 엑셀파일이 없습니다.'));
			 return false;
		}

		// --- 모듈 호출
		$getValue = \Request::post()->toArray();

		$data = new SpreadsheetExcelReader();
		$data->setOutputEncoding('UTF-8');
		$chk = $data->read(Request::files()->get('excel')['tmp_name']);

		$excelCnt = [];
		$excelCnt['total'] = $data->sheets[0]['numRows'] - 1;

		// 엑셀 데이터를 추출 후 가공
		for ($i = 2; $i <= $data->sheets[0]['numRows']; $i++) {
			$excelKey = [
				1 => 'sno',
				2 => 'bankCode',
				3 => 'accountNum',
				4 => 'encashmentPrice',
				5 => 'applicant',
				6 => 'applyFl',
				7 => 'refusFl',
				8 => 'regDt',
				9 => 'cellPhone',
			];

			$excelData = [];
			foreach($excelKey as $key => $val) {
				$excelData[$val] = trim(gd_isset($data->sheets[0]['cells'][$i][$key]));
			}

			if(in_array($excelData['refusFl'], ['1', '2', '3', '4'])) {
				$excelData['applyFl'] = 'n';
			}

			# 빈값을 넣을 경우 업데이트되지 않도록
			//if(in_array($excelData['applyFl'], ['n', 'y', ''])) {
			if(in_array($excelData['applyFl'], ['n', 'y'])) {
				$applySql = ",applyFl = '".$excelData['applyFl']."'";
			}

			//거절 마일리지 지급
			if($excelData['applyFl'] == 'n') {
				$mileage = \App::load('\\Component\\Mileage\\Mileage');
				$mileage->rejectEncashment($excelData['sno'], 'y');
			}
			
			$sql = "
				UPDATE wg_mileageEncashment 
				SET refusFl = '".$excelData['refusFl']."'
				".$applySql."
				WHERE sno = ". $excelData['sno'];
			$result = $this->db->query($sql);
			
			if($result) {
				$excelCnt['success']++;
			}
		}

		return $excelCnt;
	}

	public function downloadBankExcelList(){
		// 다운로드할 파일 경로
		$filepath = \App::getUserBasePath()."/admin/image/bankSampleExcel.xls";
		// 파일이 존재하는지 확인
		if (file_exists($filepath)) {
			// 파일 크기 가져오기
			$filesize = filesize($filepath);

			// 다운로드 헤더 설정
			header('Content-Type: application/octet-stream');
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename=' . basename($filepath));
			header('Content-Transfer-Encoding: binary');
			header('Connection: Keep-Alive');
			header('Content-Length: ' . $filesize);

			// 파일을 읽고 클라이언트로 전송
			readfile($filepath);

			// 다운로드 후에 파일 삭제 또는 추가 작업을 수행할 수도 있습니다.
			// unlink($filepath);
		} else {
			// 파일이 존재하지 않을 경우 에러 메시지 출력
			echo "File not found";
		}
	}
}