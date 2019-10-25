<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * UploadActivity Entity
 *
 * @property int $id
 * @property int $status
 * @property string $merchant
 * @property string $currency
 * @property \Cake\I18n\Time $upload_time
 * @property \Cake\I18n\Time $tx_time
 * @property string $source_file
 * @property string $output_file
 * @property string $json_file
 * @property string $username
 * @property string $ip_addr
 */
class UploadActivity extends Entity
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
		//'status' => false,
		'upload_time' => false,
    ];
}
