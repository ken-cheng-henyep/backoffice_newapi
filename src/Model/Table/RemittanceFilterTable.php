<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Log\Log;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use RemittanceReportReader;

/**
 * RemittanceFilter Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 *
 * @method \App\Model\Entity\RemittanceFilter get($primaryKey, $options = [])
 * @method \App\Model\Entity\RemittanceFilter newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\RemittanceFilter[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceFilter|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\RemittanceFilter patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceFilter[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\RemittanceFilter findOrCreate($search, callable $callback = null, $options = [])
 */
class RemittanceFilterTable extends Table
{
    var $reader;
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        //parent::initialize($config);

        $this->table('remittance_filter');
        $this->displayField('name');
        $this->primaryKey('id');

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id'
        ]);

        $this->hasMany('RemittanceFilterRule', ['dependent'=>true, 'foreignKey'=>'filter_id']);
            //->setDependent(true);
            //->setForeignKey('filter_id');
        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->reader = new RemittanceReportReader($db_name);
        Log::debug("DB: $db_name");
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
            ->allowEmpty('name');

        $validator
            ->allowEmpty('dsc');

        $validator
            ->allowEmpty('code');

        $validator
            ->requirePresence('rule_type', 'create')
            ->notEmpty('rule_type');

        $validator
            ->requirePresence('action', 'create')
            ->notEmpty('action');

        $validator
            ->integer('isblacklist')
            ->requirePresence('isblacklist', 'create')
            ->notEmpty('isblacklist');

        $validator
            ->integer('count_limit')
            ->allowEmpty('count_limit');

        $validator
            ->numeric('amount_limit')
            ->allowEmpty('amount_limit');

        $validator
            ->allowEmpty('period');

        $validator
            ->allowEmpty('username');

        $validator
            ->allowEmpty('remarks');

        $validator
            ->integer('status')
            ->requirePresence('status', 'create')
            ->notEmpty('status');

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
        $rules->add($rules->isUnique(['username']));
        // A list of fields
        /*
        $rules->add($rules->isUnique(
            ['code', 'merchant_id','dsc'
                //'remittance_filter_rule'=>[['column', 'val', 'condition']],
            ],
            'This rule has already existed.'
        ));
        */

        $rules->add($rules->existsIn(['merchant_id'], 'Merchants'));

        // Add a rule for create.
        $rules->addCreate(function ($entity, $options) {
            // Return a boolean to indicate pass/failure
            //Log::debug("isExistFilter:".$this->reader->isExistFilter($entity->toArray()));
            //check if duplicated
            if ($this->reader->isExistFilter($entity->toArray()))
                return false;
            return true; //Pass
            //return false;
        }
        , 'UniqueRemittanceFilter'
        , ['errorField' => 'notUnique', 'message' => 'Filter already exists.']
        ) ;

        return $rules;
    }
}