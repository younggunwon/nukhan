<?php
namespace Component\Subscription\InicisApi;

use Component\Subscription\InicisApi\INIStdPayUtil;
//require_once('../libs/INIStdPayUtil.php');

use Request;
class INIpayStdMakeSignature
{
    public function get()
    {
        $SignatureUtil = new INIStdPayUtil();
        $_REQUEST = Request::request()->all();
        
        $input = "oid=" . $_REQUEST["oid"] . "&price=" . $_REQUEST["price"] . "&timestamp=" . $_REQUEST["timestamp"];
    
        $output['signature'] = array(
            ///'signature' => $SignatureUtil->makeHash($input, "sha256")
            'signature' => hash("sha256", $input)
        );
        
        return json_encode($output);
    }
}
?>
