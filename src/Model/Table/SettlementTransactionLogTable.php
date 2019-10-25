<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * TransactionLog Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 * @property \Cake\ORM\Association\BelongsTo $Internals
 * @property \Cake\ORM\Association\BelongsTo $States
 *
 * @method \App\Model\Entity\TransactionLog get($primaryKey, $options = [])
 * @method \App\Model\Entity\TransactionLog newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\TransactionLog[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\TransactionLog|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TransactionLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\TransactionLog[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\TransactionLog findOrCreate($search, callable $callback = null)
 */
class SettlementTransactionLogTable extends Table
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

        $this->table('transaction_log');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('Merchants', [

            'foreignKey' => 'merchant_id',
            'joinType' => 'INNER'
        ]);
        // $this->hasOne ('GHTTransactionLog', [
            // 'foreignKey' => 'transaction_id',
            //'conditions'=>['GHTTransactionLog.transaction_id = TransactionLog.transaction_id'],
            // 'joinType' => 'LEFT'
        // ]);
        // $this->hasOne ('GPayTransactionLog', [
            //'foreignKey' => 'merchant_order_no',
            //'conditions'=>['GPayTransactionLog.merchant_order_no = TransactionLog.internal_id','internal_id IS NOT NULL'],
        //     'joinType' => 'LEFT'
        // ]);

        // $this->belongsTo('Internals', [
        //     'foreignKey' => 'internal_id'
        // ]);
        // $this->belongsTo('States', [
        //     'foreignKey' => 'state_id'
        // ]);
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
            ->requirePresence('platform', 'create')
            ->notEmpty('platform');

        $validator
            ->dateTime('update_time')
            ->requirePresence('update_time', 'create')
            ->notEmpty('update_time');

        $validator
            ->requirePresence('STATE', 'create')
            ->notEmpty('STATE');

        $validator
            ->requirePresence('TRANSACTION_STATE', 'create')
            ->notEmpty('TRANSACTION_STATE');

        $validator
            ->requirePresence('TRANSACTION_ID', 'create')
            ->notEmpty('TRANSACTION_ID');

        $validator
            ->allowEmpty('TRANSACTION_CODE');

        $validator
            ->allowEmpty('TRANSACTION_TYPE');

        $validator
            ->dateTime('STATE_TIME')
            ->allowEmpty('STATE_TIME');

        $validator
            ->dateTime('TRANSACTION_TIME')
            ->allowEmpty('TRANSACTION_TIME');

        $validator
            ->allowEmpty('MERCHANT_REF');

        $validator
            ->allowEmpty('RESPONSE_CODE');

        $validator
            ->requirePresence('CURRENCY', 'create')
            ->notEmpty('CURRENCY');

        $validator
            ->numeric('AMOUNT')
            ->requirePresence('AMOUNT', 'create')
            ->notEmpty('AMOUNT');

        $validator
            ->allowEmpty('CONVERT_CURRENCY');

        $validator
            ->numeric('CONVERT_AMOUNT')
            ->allowEmpty('CONVERT_AMOUNT');

        $validator
            ->numeric('CONVERT_RATE')
            ->allowEmpty('CONVERT_RATE');

        $validator
            ->allowEmpty('FIRST_NAME');

        $validator
            ->allowEmpty('LAST_NAME');

        $validator
            ->email('email')
            ->allowEmpty('email');

        $validator
            ->numeric('ADJUSTMENT')
            ->allowEmpty('ADJUSTMENT');

        $validator
            ->allowEmpty('card_number');

        $validator
            ->allowEmpty('ip_address');

        $validator
            ->allowEmpty('SITE_ID');

        $validator
            ->allowEmpty('REBILL_ID');

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
        $rules->add($rules->isUnique(['email']));
        $rules->add($rules->existsIn(['merchant_id'], 'Merchants'));
        // $rules->add($rules->existsIn(['internal_id'], 'Internals'));
        // $rules->add($rules->existsIn(['state_id'], 'States'));

        return $rules;
    }
}
