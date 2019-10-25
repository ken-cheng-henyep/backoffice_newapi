<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TransactionLog Entity
 *
 * @property int $id
 * @property string $platform
 * @property \Cake\I18n\Time $update_time
 * @property string $STATE
 * @property string $TRANSACTION_STATE
 * @property string $TRANSACTION_ID
 * @property string $TRANSACTION_CODE
 * @property string $TRANSACTION_TYPE
 * @property \Cake\I18n\Time $STATE_TIME
 * @property \Cake\I18n\Time $TRANSACTION_TIME
 * @property string $MERCHANT_REF
 * @property string $RESPONSE_CODE
 * @property string $CURRENCY
 * @property float $AMOUNT
 * @property string $CONVERT_CURRENCY
 * @property float $CONVERT_AMOUNT
 * @property float $CONVERT_RATE
 * @property string $FIRST_NAME
 * @property string $LAST_NAME
 * @property string $email
 * @property string $merchant_id
 * @property float $ADJUSTMENT
 * @property string $card_number
 * @property string $ip_address
 * @property string $SITE_ID
 * @property string $REBILL_ID
 * @property string $internal_id
 * @property string $state_id
 *
 * @property \App\Model\Entity\Merchant $merchant
 * @property \App\Model\Entity\Internal $internal
 * @property \App\Model\Entity\State $state
 */
class TransactionLog extends Entity
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
