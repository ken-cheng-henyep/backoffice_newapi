<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RemittanceFilter Entity
 *
 * @property int $id
 * @property string $name
 * @property string $dsc
 * @property string $code
 * @property string $merchant_id
 * @property string $rule_type
 * @property string $action
 * @property int $isblacklist
 * @property int $count_limit
 * @property float $amount_limit
 * @property int $period
 * @property string $username
 * @property string $remarks
 * @property int $status
 * @property \Cake\I18n\Time $create_time
 *
 * @property \App\Model\Entity\Merchant $merchant
 */
class RemittanceFilter extends Entity
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
