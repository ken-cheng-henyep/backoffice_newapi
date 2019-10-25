<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RemittanceFilterRule Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Filters
 *
 * @method \App\Model\Entity\RemittanceFilterRule get($primaryKey, $options = [])
 * @method \App\Model\Entity\RemittanceFilterRule newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RemittanceFilterRule[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceFilterRule|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RemittanceFilterRule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceFilterRule[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceFilterRule findOrCreate($search, callable $callback = null, $options = [])
 */
class RemittanceFilterRuleTable extends Table
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

        $this->table('remittance_filter_rule');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('RemittanceFilter', [
            'foreignKey' => 'filter_id',
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
            ->requirePresence('column', 'create')
            ->notEmpty('column');

        $validator
            ->allowEmpty('val');

        $validator
            ->allowEmpty('condition');

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
        $rules->add($rules->existsIn(['filter_id'], 'RemittanceFilter'));

        return $rules;
    }
}
