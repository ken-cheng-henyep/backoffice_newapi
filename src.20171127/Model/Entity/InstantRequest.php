<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * InstantRequest Entity
 *
 * @property string $id
 * @property int $type
 * @property string $merchant_id
 * @property int $test_trans
 * @property string $name
 * @property string $account
 * @property string $bank_name
 * @property string $bank_branch
 * @property int $bank_code
 * @property string $province
 * @property string $city
 * @property int $province_code
 * @property string $id_number
 * @property int $id_type
 * @property string $merchant_ref
 * @property string $currency
 * @property float $amount
 * @property string $convert_currency
 * @property float $convert_amount
 * @property float $convert_rate
 * @property float $fee_cny
 * @property float $convert_fee
 * @property float $gross_amount_cny
 * @property float $paid_amount
 * @property float $convert_paid_amount
 * @property int $target
 * @property string $remarks
 * @property int $status
 * @property int $filter_flag
 * @property string $filter_remarks
 * @property string $validation
 * @property \Cake\I18n\Time $create_time
 * @property \Cake\I18n\Time $update_time
 *
 * @property \App\Model\Entity\Merchant $merchant
 */
class InstantRequest extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false
    ];

    protected function _getStatusName()
    {
        return \RemittanceReportReader::getInsReqStatus($this->_properties['status']);
    }

    protected function _getTargetName()
    {
        return \RemittanceReportReader::getTargetName($this->_properties['target']);
    }
}