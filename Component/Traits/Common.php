<?php

namespace Component\Traits;

/**
* 공통 Trait 
*
* @author webnmobile
*/
trait Common
{
	/**
	* 주문단계 목록 
	*
	* @return Array
	*/
    public function getOrderStatusList()
    {
         $status = [];
         if ($tmp = gd_policy('order.status')) {
             foreach ($tmp as $_tmp) {
                 foreach ($_tmp as $k => $v) {
					 if (strlen($k) == 2) {
						$status[$k] = $v['user'];
					 }
                 }
              }
          }

          return $status;
    }
	
	/**
	* 주문단계 
	*
	* @return String
	*/
	public function getOrderStatus($orderStatus = "")
	{
		if (!$orderStatus)
			return;
		
		$status = $this->getOrderStatusList();
			
		return $status[$orderStatus];
	}
}