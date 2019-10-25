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
class MerchantsTable extends Table
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

        $this->table('merchants');
        $this->displayField('name');
        $this->primaryKey('id');

        $this->hasMany('MerchantUsers', [
            'foreignKey' => 'merchant_id'
        ]);
        $this->hasOne('SettlementRate', [
            'foreignKey' => 'settle_rate_symbol',
        ]);
        $this->hasMany('RemittanceAuthorization', [
            'foreignKey' => 'merchant_id'
        ]);
        $this->hasMany('RemittanceBatch', [
            'foreignKey' => 'merchant_id'
        ]);                                                 
        $this->hasMany('TransactionLog', [
            'foreignKey' => 'merchant_id'
        ]);
        $this->hasMany('UploadActivity', [
            'foreignKey' => 'merchant_id'
        ]);


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

        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->requirePresence('currencies', 'create')
            ->notEmpty('currencies');

        $validator
            ->integer('settle_option')
            ->allowEmpty('settle_option');

        $validator
            ->requirePresence('settle_currency', 'create')
            ->notEmpty('settle_currency');
            
        $validator
            ->allowEmpty('settle_handling_fee');

        $validator
            ->allowEmpty('settle_bank_account');

        $validator
            ->allowEmpty('settle_bank_name');

        $validator
            ->allowEmpty('type');

        $validator
            ->boolean('show_fx')
            ->allowEmpty('show_fx');

        $validator
            ->integer('round_precision')
            ->allowEmpty('round_precision');

        $validator
            ->allowEmpty('remittance_symbol');

        $validator
            ->numeric('remittance_fee')
            ->allowEmpty('remittance_fee');

        $validator
            ->integer('remittance_fee_type')
            ->allowEmpty('remittance_fee_type');

        $validator
            ->numeric('remittance_min_fee')
            ->allowEmpty('remittance_min_fee');

        $validator
            ->boolean('remittance_preauthorized')
            ->allowEmpty('remittance_preauthorized');

        $validator
            ->boolean('local_remittance_enabled')
            ->allowEmpty('local_remittance_enabled');

        $validator
            ->numeric('local_remittance_fee')
            ->allowEmpty('local_remittance_fee');

        $validator
            ->integer('enabled')
            ->requirePresence('enabled', 'create')
            ->notEmpty('enabled');

        $validator
            ->allowEmpty('authorized_email');

        $validator
            ->allowEmpty('api_username');

        $validator
            ->allowEmpty('api_password');

        $validator
            ->dateTime('createdate')
            ->requirePresence('createdate', 'create')
            ->notEmpty('createdate');

        return $validator;
    }
}
