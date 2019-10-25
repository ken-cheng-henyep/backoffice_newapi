<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RemittanceApiLog Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Batches
 * @property \Cake\ORM\Association\BelongsTo $Logs
 * @property \Cake\ORM\Association\BelongsTo $Reqs
 *
 * @method \App\Model\Entity\RemittanceApiLog get($primaryKey, $options = [])
 * @method \App\Model\Entity\RemittanceApiLog newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RemittanceApiLog[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceApiLog|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RemittanceApiLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceApiLog[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceApiLog findOrCreate($search, callable $callback = null, $options = [])
 */
class RemittanceApiLogTable extends Table
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

        $this->table('remittance_api_log');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('Batches', [
            'foreignKey' => 'batch_id'
        ]);
        /*
        $this->belongsTo('Logs', [
            'foreignKey' => 'log_id'
        ]);
        */
        $this->belongsTo('InstantRequest', [
            'foreignKey' => 'req_id'
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
            ->requirePresence('processor', 'create')
            ->notEmpty('processor');

        $validator
            ->integer('bank_code')
            ->allowEmpty('bank_code');

        $validator
            ->allowEmpty('url');

        $validator
            ->allowEmpty('request');

        $validator
            ->allowEmpty('response');

        $validator
            ->allowEmpty('callback');

        $validator
            ->allowEmpty('return_code');

        $validator
            ->integer('status')
            ->requirePresence('status', 'create')
            ->notEmpty('status');

        $validator
            ->dateTime('create_time')
            ->requirePresence('create_time', 'create')
            ->notEmpty('create_time');

        $validator
            ->dateTime('complete_time')
            ->allowEmpty('complete_time');

        $validator
            ->dateTime('callback_time')
            ->allowEmpty('callback_time');

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
        //$rules->add($rules->existsIn(['batch_id'], 'Batches'));
        $rules->add($rules->existsIn(['batch_id'], 'RemittanceBatch'));
        //$rules->add($rules->existsIn(['log_id'], 'Logs'));
        $rules->add($rules->existsIn(['req_id'], 'InstantRequest'));

        return $rules;
    }
}
