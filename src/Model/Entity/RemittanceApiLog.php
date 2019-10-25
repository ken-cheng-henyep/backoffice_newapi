<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * RemittanceApiLog Entity
 *
 * @property string $id
 * @property string $batch_id
 * @property int $log_id
 * @property string $req_id
 * @property string $processor
 * @property int $bank_code
 * @property string $url
 * @property string $request
 * @property string $response
 * @property string $callback
 * @property string $return_code
 * @property int $status
 * @property \Cake\I18n\Time $create_time
 * @property \Cake\I18n\Time $complete_time
 * @property \Cake\I18n\Time $callback_time
 *
 * @property \App\Model\Entity\Batch $batch
 * @property \App\Model\Entity\Log $log
 * @property \App\Model\Entity\Req $req
 */
class RemittanceApiLog extends Entity
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
