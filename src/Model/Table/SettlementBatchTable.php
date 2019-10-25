<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Settlement Batch
 *
 */
class SettlementBatchTable extends Table
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

        $this->alias('b');
        $this->table('settlement_batch');
        $this->primaryKey('batch_id');
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
            ->allowEmpty('batch_id', 'create');

        $validator
            ->notEmpty('state');

        $validator
            ->notEmpty('report_date');

        $validator
            ->notEmpty('from_date');

        $validator
            ->notEmpty('to_date');

        $validator
            ->dateTime('process_time')
            ->requirePresence('process_time', 'create')
            ->notEmpty('process_time');

        return $validator;
    }
}
