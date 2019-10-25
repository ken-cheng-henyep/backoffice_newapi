<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Processors Model
 *
 * @method \App\Model\Entity\Processor get($primaryKey, $options = [])
 * @method \App\Model\Entity\Processor newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\Processor[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Processor|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Processor patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Processor[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\Processor findOrCreate($search, callable $callback = null, $options = [])
 */
class ProcessorsTable extends Table
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

        $this->table('processors');
        $this->displayField('name');
        $this->primaryKey('id');
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
            ->integer('id')
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('name', 'create')
            ->notEmpty('name');

        $validator
            ->integer('function')
            ->requirePresence('function', 'create')
            ->notEmpty('function');

        $validator
            ->requirePresence('type', 'create')
            ->notEmpty('type');

        $validator
            ->numeric('cost')
            ->allowEmpty('cost');

        $validator
            ->numeric('min_cost')
            ->allowEmpty('min_cost');

        $validator
            ->integer('priority')
            ->allowEmpty('priority');

        return $validator;
    }
}
