<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * MerchantWalletService Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 * @property \Cake\ORM\Association\BelongsTo $Wallets
 *
 * @method \App\Model\Entity\MerchantWalletService get($primaryKey, $options = [])
 * @method \App\Model\Entity\MerchantWalletService newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\MerchantWalletService[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\MerchantWalletService|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\MerchantWalletService patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantWalletService[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantWalletService findOrCreate($search, callable $callback = null, $options = [])
 */
class MerchantWalletServiceTable extends Table
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

        $this->table('merchant_wallet_service');

        $this->primaryKey(['merchant_id', 'wallet_id','type']);

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id',
            'joinType' => 'INNER'
        ]);
        /*
        $this->belongsTo('Wallets', [
            'foreignKey' => 'wallet_id',
            'joinType' => 'INNER'
        ]);
        */
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
            ->integer('type')
            ->requirePresence('type', 'create')
            ->notEmpty('type');

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
        //$rules->add($rules->existsIn(['wallet_id'], 'Wallets'));

        return $rules;
    }
}
