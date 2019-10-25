<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * MerchantTransactionAccount Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 * @property \Cake\ORM\Association\BelongsTo $Wallets
 *
 * @method \App\Model\Entity\MerchantTransactionAccount get($primaryKey, $options = [])
 * @method \App\Model\Entity\MerchantTransactionAccount newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\MerchantTransactionAccount[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\MerchantTransactionAccount|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\MerchantTransactionAccount patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantTransactionAccount[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantTransactionAccount findOrCreate($search, callable $callback = null, $options = [])
 */
class MerchantTransactionAccountTable extends Table
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

        $this->table('merchant_transaction_account');
        $this->displayField('name');
        $this->primaryKey(['merchant_id', 'wallet_id']);

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Wallets', [
            'foreignKey' => 'wallet_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('MerchantWalletService');
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
            ->allowEmpty('name');

        $validator
            ->numeric('balance')
            ->allowEmpty('balance');

        $validator
            ->integer('status')
            ->allowEmpty('status');

        $validator
            ->dateTime('create_time')
            ->requirePresence('create_time', 'create')
            ->notEmpty('create_time');

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
        $rules->add($rules->existsIn(['wallet_id'], 'Wallets'));

        return $rules;
    }
}
