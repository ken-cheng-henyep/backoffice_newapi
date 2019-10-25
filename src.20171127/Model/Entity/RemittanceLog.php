<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RemittanceLog Entity
 *
 * @property int $id
 * @property string $beneficiary_name
 * @property string $account
 * @property string $bank_name
 * @property string $bank_branch
 * @property int $bank_code
 * @property string $province
 * @property string $city
 * @property int $province_code
 * @property string $currency
 * @property float $amount
 * @property string $convert_currency
 * @property float $convert_amount
 * @property float $convert_rate
 * @property string $id_number
 * @property int $id_type
 * @property string $batch_id
 * @property int $status
 * @property string $validation
 * @property \Cake\I18n\Time $create_time
 * @property \Cake\I18n\Time $update_time
 *
 * @property \App\Model\Entity\Batch $batch
 */
class RemittanceLog extends Entity
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
}
