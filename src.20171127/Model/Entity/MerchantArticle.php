<?php
namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * MerchantArticle Entity
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string $path
 * @property int $type
 * @property string $username
 * @property int $status
 * @property \Cake\I18n\Time $publish_time
 * @property \Cake\I18n\Time $create_time
 */
class MerchantArticle extends Entity
{
/*
Type
1	User Guide
2	API Specifications
3	Policy
4 	News
 */
    var $types = [
        1=>'User Guide',
        2=>'API Specifications',
        3=>'Policy',
        4=>'News',
    ];
    var $doctypes = [
        1=>'User Guide',
        2=>'API Specifications',
        3=>'Policy',
    ];

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

    //section name
    protected function _getTypeName()
    {
        $type = $this->_properties['type'];
        if (isset($this->types[$type]))
            return $this->types[$type];
        return 'N/A';
    }

    //return file basename
    protected function _getFilename() {
        if (! empty($this->_properties['path'])) {
            return str_replace('_' . $this->_properties['file_md5'], '', basename($this->_properties['path']));
        }
        return null;
    }

    protected function _getContentDate() {
        if (! empty($this->_properties['content_time'])) {
            return date('Y/m/d', strtotime($this->_properties['content_time']));
        }
        return null;
    }
}