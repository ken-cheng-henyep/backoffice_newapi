<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RemittanceLog Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Batches
 *
 * @method \App\Model\Entity\RemittanceLog get($primaryKey, $options = [])
 * @method \App\Model\Entity\RemittanceLog newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RemittanceLog[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceLog|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RemittanceLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceLog[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceLog findOrCreate($search, callable $callback = null)
 */
class RemittanceLogTable extends Table
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

        $this->table('remittance_log');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('RemittanceBatch', [
            'foreignKey' => 'batch_id',
            'joinType' => 'INNER'
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
            ->requirePresence('beneficiary_name', 'create')
            ->notEmpty('beneficiary_name');

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
            ->allowEmpty('id_number');

        $validator
            ->integer('id_type')
            ->allowEmpty('id_type');

        $validator
            ->integer('status')
            ->requirePresence('status', 'create')
            ->notEmpty('status');

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
        $rules->add($rules->existsIn(['batch_id'], 'RemittanceBatch'));

        return $rules;
    }
}
