<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Merchant Entity
 *
 * @property string $id
 * @property string $name
 * @property string $currencies
 * @property int $settle_option
 * @property string $settle_currency
 * @property string $type
 * @property bool $show_fx
 * @property int $round_precision
 * @property string $remittance_symbol
 * @property float $remittance_fee
 * @property int $remittance_fee_type
 * @property float $remittance_min_fee
 * @property bool $remittance_preauthorized
 * @property bool $local_remittance_enabled
 * @property float $local_remittance_fee
 * @property int $enabled
 * @property string $authorized_email
 * @property string $api_username
 * @property string $api_password
 * @property \Cake\I18n\Time $createdate
 */
class Merchant extends Entity
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
        'id' => false,
        'api_password' => false,
    ];
}
