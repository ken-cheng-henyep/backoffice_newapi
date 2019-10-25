<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;
use RemittanceReportReader;

/**
 * RemittanceBatch Entity
 *
 * @property string $id
 * @property string $merchant_id
 * @property int $target
 * @property string $file1
 * @property string $file2
 * @property string $file3
 * @property string $file1_md5
 * @property int $count
 * @property float $total_usd
 * @property float $total_cny
 * @property float $total_convert_rate
 * @property int $status
 * @property string $username
 * @property string $ip_addr
 * @property \Cake\I18n\Time $settle_time
 * @property \Cake\I18n\Time $upload_time
 * @property \Cake\I18n\Time $update_time
 *
 * @property \App\Model\Entity\Merchant $merchant
 */
class RemittanceBatch extends Entity
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

    protected function _getStatusName()
    {
        //return $this->_properties['status'] . '  name' ;
        return RemittanceReportReader::getStatus($this->_properties['status']);
    }

    protected function _getTargetName()
    {
        return RemittanceReportReader::getTargetName($this->_properties['target']);
    }

    public function afterFind($results, $primary = false)
    {
        $this->log('RemittanceBatch afterFind:'.var_export($results,true),'debug');
        foreach ($results as $k => $val) {

        }
    }

}
