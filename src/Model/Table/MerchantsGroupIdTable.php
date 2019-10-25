<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * MerchantsGroupId Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 *
 * @method \App\Model\Entity\MerchantsGroupId get($primaryKey, $options = [])
 * @method \App\Model\Entity\MerchantsGroupId newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\MerchantsGroupId[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\MerchantsGroupId|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\MerchantsGroupId patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantsGroupId[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantsGroupId findOrCreate($search, callable $callback = null, $options = [])
 */
class MerchantsGroupIdTable extends Table
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

        $this->table('merchants_group_id');
        $this->displayField('merchant_id');
        $this->primaryKey('merchant_id');

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id',
            //'joinType' => 'INNER'
        ]);
        $this->belongsTo('MerchantGroup', [
            'foreignKey' => 'id',
            //'joinType' => 'INNER'
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
            ->allowEmpty('id');

        $validator
            ->integer('master')
            ->allowEmpty('master');

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
