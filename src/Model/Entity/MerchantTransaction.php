<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * MerchantTransaction Entity
 *
 * @property int $id
 * @property string $merchant_id
 * @property int $wallet_id
 * @property int $type
 * @property string $dsc
 * @property float $amount
 * @property float $balance
 * @property string $username
 * @property string $remarks
 * @property string $ref_id
 * @property int $latest
 * @property \Cake\I18n\Time $create_time
 *
 * @property \App\Model\Entity\Merchant $merchant
 * @property \App\Model\Entity\Wallet $wallet
 * @property \App\Model\Entity\Ref $ref
 */
class MerchantTransaction extends Entity
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

    protected function _getTypeName()
    {
        return \MerchantWallet::getTypeName($this->_properties['type']);
    }
}
