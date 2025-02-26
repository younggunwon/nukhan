<?php
/**
 * *
 *  * This is commercial software, only users who have purchased a valid license
 *  * and accept to the terms of the License Agreement can install and use this
 *  * program.
 *  *
 *  * Do not edit or add to this file if you wish to upgrade Godomall5 to newer
 *  * versions in the future.
 *  *
 *  * @copyright ⓒ 2016, NHN godo: Corp.
 *  * @link http://www.godo.co.kr
 *
 */

namespace Component\Mileage;


use App;
use Framework\Utility\DateTimeUtils;

/**
 * Class 마일리지 유틸리티 클래스
 * @package Bundle\Component\Member\Util
 * @author  yjwee
 */
class MileageUtil extends \Bundle\Component\Mileage\MileageUtil
{
    /**
     * 마일리지 소멸 예정일 반환 함수
     *
     * @static
     * @return string
     */
    public static function getDeleteRecomScheduleDate()
    {
        $recomMileageConfig = gd_policy('member.recomMileageGive');
        if ($recomMileageConfig['expiryFl'] === 'n') {
            return '9999-12-31 00:00:00';
        } else {
            $expiryDays = $recomMileageConfig['expiryDays'];

            return DateTimeUtils::dateFormat('Y-m-d G:i:s', '+' . $expiryDays . ' day');
        }
    }
}