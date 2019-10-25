<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Merchants Model
 *
 * @property \Cake\ORM\Association\HasMany $MerchantUsers
 * @property \Cake\ORM\Association\HasMany $RemittanceAuthorization
 * @property \Cake\ORM\Association\HasMany $RemittanceBatch
 * @property \Cake\ORM\Association\HasMany $TransactionLog
 * @property \Cake\ORM\Association\HasMany $UploadActivity
 *
 * @method \App\Model\Entity\Merchant get($primaryKey, $options = [])
 * @method \App\Model\Entity\Merchant newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Merchant[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Merchant|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Merchant patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Merchant[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Merchant findOrCreate($search, callable $callback = null)
 */
class MerchantGroupTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('merchants_group');
        $this->displayField('id');
        $this->displayField('name');
        $this->primaryKey('id');

        //$this->hasMany('MerchantsGroupId');
        $this->belongsToMany('Merchants', [
            //'joinTable' => 'merchants_group_id',
            'through' => 'MerchantsGroupId',
        ]);
            //->setForeignKey('id');
    }


    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->allowEmpty('id', 'create');


        return $validator;
    }
}
