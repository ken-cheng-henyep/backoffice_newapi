<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GHTTransactionLog Entity
 *
 * @property int    $id
 * @property string $transaction_id
 * @property string $merchant_no
 * @property string $payment_no
 * @property string $currency
 * @property string $status
 * @property string $settle_status
 * @property float  $amount
 * @property float  $fee
 * @property float  $convert_rate
 * @property float  $refund_amount
 * @property string $bank_name
 * @property string $bank_code
 * @property string $payment_code
 * @property string $payer_name
 * @property string $phone_no
 * @property int    $id_type
 * @property string $id_number
 * @property string $remark
 * @property date   $transaction_time
 * @property date   $payment_time
 * @property date   $settle_time
 * @property date   $update_time
 *
 */
class GHTTransactionLog extends Entity
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
