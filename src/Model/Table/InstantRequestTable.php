<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * InstantRequest Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 *
 * @method \App\Model\Entity\InstantRequest get($primaryKey, $options = [])
 * @method \App\Model\Entity\InstantRequest newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\InstantRequest[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\InstantRequest|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\InstantRequest patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\InstantRequest[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\InstantRequest findOrCreate($search, callable $callback = null, $options = [])
 */
class InstantRequestTable extends Table
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

        $this->table('instant_request');
        $this->displayField('name');
        $this->primaryKey('id');

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('RemittanceApiLog', [
            'className' => 'RemittanceApiLog',
            'foreignKey' => 'req_id',
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
            ->integer('type')
            ->allowEmpty('type');

        $validator
            ->integer('test_trans')
            ->requirePresence('test_trans', 'create')
            ->notEmpty('test_trans');

        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->requirePresence('account', 'create')
            ->notEmpty('account');

        $validator
            ->requirePresence('bank_name', 'create')
            ->notEmpty('bank_name');

        $validator
            ->allowEmpty('bank_branch');

        $validator
            ->integer('bank_code')
            ->allowEmpty('bank_code');

        $validator
            ->allowEmpty('province');

        $validator
            ->allowEmpty('city');

        $validator
            ->integer('province_code')
            ->allowEmpty('province_code');

        $validator
            ->allowEmpty('id_number');

        $validator
            ->integer('id_type')
            ->allowEmpty('id_type');

        $validator
            ->allowEmpty('merchant_ref');

        $validator
            ->requirePresence('currency', 'create')
            ->notEmpty('currency');

        $validator
            ->numeric('amount')
            ->requirePresence('amount', 'create')
            ->notEmpty('amount');

        $validator
            ->allowEmpty('convert_currency');

        $validator
            ->numeric('convert_amount')
            ->allowEmpty('convert_amount');

        $validator
            ->numeric('convert_rate')
            ->allowEmpty('convert_rate');

        $validator
            ->numeric('fee_cny')
            ->allowEmpty('fee_cny');

        $validator
            ->numeric('convert_fee')
            ->allowEmpty('convert_fee');

        $validator
            ->numeric('gross_amount_cny')
            ->allowEmpty('gross_amount_cny');

        $validator
            ->numeric('paid_amount')
            ->allowEmpty('paid_amount');

        $validator
            ->numeric('convert_paid_amount')
            ->allowEmpty('convert_paid_amount');

        $validator
            ->integer('target')
            ->allowEmpty('target');

        $validator
            ->allowEmpty('remarks');

        $validator
            ->integer('status')
            ->requirePresence('status', 'create')
            ->notEmpty('status');

        $validator
            ->integer('filter_flag')
            ->requirePresence('filter_flag', 'create')
            ->notEmpty('filter_flag');

        $validator
            ->allowEmpty('filter_remarks');

        $validator
            ->allowEmpty('validation');

        $validator
            ->dateTime('create_time')
            ->requirePresence('create_time', 'create')
            ->notEmpty('create_time');

        $validator
            ->dateTime('update_time')
            ->requirePresence('update_time', 'create')
            ->notEmpty('update_time');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        $rules->add($rules->existsIn(['merchant_id'], 'Merchants'));

        return $rules;
    }
}
