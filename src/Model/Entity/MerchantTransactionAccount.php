<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * MerchantTransactionAccount Entity
 *
 * @property string $merchant_id
 * @property int $wallet_id
 * @property string $name
 * @property float $balance
 * @property int $status
 * @property \Cake\I18n\Time $create_time
 *
 * @property \App\Model\Entity\Merchant $merchant
 * @property \App\Model\Entity\Wallet $wallet
 */
class MerchantTransactionAccount extends Entity
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
        'merchant_id' => false,
        'wallet_id' => false
    ];
}
