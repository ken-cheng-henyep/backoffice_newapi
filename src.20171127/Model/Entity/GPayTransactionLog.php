<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GPayTransactionLog Entity
 *
 * @property int    $id
 * @property string $merchant_no
 * @property string $order_no
 * @property string $merchant_order_no
 * @property string $type
 * @property string $status
 * @property string $results
 * @property string $bank_name
 * @property string $bank_code
 * @property string $currency
 * @property float  $amount
 * @property float  $fee
 * @property date   $transaction_time
 * @property date   $update_time
 *
 */
class GPayTransactionLog extends Entity
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
