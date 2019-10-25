<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * RemittanceBatch Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 *
 * @method \App\Model\Entity\RemittanceBatch get($primaryKey, $options = [])
 * @method \App\Model\Entity\RemittanceBatch newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RemittanceBatch[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceBatch|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RemittanceBatch patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceBatch[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceBatch findOrCreate($search, callable $callback = null)
 */
class RemittanceBatchTable extends Table
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

        $this->table('remittance_batch');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id',
            'joinType' => 'INNER'
        ]);
        $this->hasMany('RemittanceLog', [
            'className' => 'RemittanceLog',
            'foreignKey' => 'batch_id',
        ]);
        $this->hasMany('RemittanceApiLog', [
            'className' => 'RemittanceApiLog',
            'foreignKey' => 'batch_id',
        ]);
    }

    public function afterFind($results, $primary = false)
    {
        $this->log('afterFind:'.var_export($results, true), 'debug');
        foreach ($results as $k => $val) {
        }
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
            ->integer('target')
            ->allowEmpty('target');

        $validator
            ->allowEmpty('file1');

        $validator
            ->allowEmpty('file2');

        $validator
            ->allowEmpty('file3');

        $validator
            ->allowEmpty('file1_md5');

        $validator
            ->integer('count')
            ->allowEmpty('count');

        $validator
            ->numeric('total_usd')
            ->allowEmpty('total_usd');

        $validator
            ->numeric('total_cny')
            ->allowEmpty('total_cny');

        $validator
            ->numeric('total_convert_rate')
            ->allowEmpty('total_convert_rate');

        $validator
            ->integer('status')
            ->requirePresence('status', 'create')
            ->notEmpty('status');

        $validator
            ->allowEmpty('username');

        $validator
            ->allowEmpty('ip_addr');

        $validator
            ->dateTime('settle_time')
            ->allowEmpty('settle_time');

        $validator
            ->dateTime('upload_time')
            ->requirePresence('upload_time', 'create')
            ->notEmpty('upload_time');

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
        $rules->add($rules->isUnique(['username']));
        $rules->add($rules->existsIn(['merchant_id'], 'Merchants'));

        return $rules;
    }
}
