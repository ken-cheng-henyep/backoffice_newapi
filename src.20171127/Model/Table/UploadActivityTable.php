<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * UploadActivity Model
 *
 * @method \App\Model\Entity\UploadActivity get($primaryKey, $options = [])
 * @method \App\Model\Entity\UploadActivity newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\UploadActivity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UploadActivity|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UploadActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\UploadActivity[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\UploadActivity findOrCreate($search, callable $callback = null)
 */
class UploadActivityTable extends Table
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

        $this->table('upload_activity');
        $this->displayField('id');
        $this->primaryKey('id');
		$this->hasOne('Merchants', [
            'className' => 'Merchants',
			'foreignKey' => 'id',
			'bindingKey' => 'merchant_id',
            //'conditions' => ['Addresses.primary' => '1'],
            //'dependent' => true
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
            ->integer('status')
            //->requirePresence('status', 'create')
            ->allowEmpty('status');
			//->notEmpty('status');

        $validator
            //->allowEmpty('merchant');
			->notEmpty('merchant_id');

        $validator
            ->requirePresence('currency', 'create')
            ->notEmpty('currency');

        $validator
            ->dateTime('upload_time')
            //->requirePresence('upload_time', 'create')
            ->allowEmpty('upload_time');
			//->notEmpty('upload_time');

        $validator
            ->dateTime('tx_time')
            ->allowEmpty('tx_time');

        $validator
            ->requirePresence('source_file', 'create')
            ->notEmpty('source_file');

        $validator
            ->allowEmpty('output_file');

        $validator
            ->allowEmpty('json_file');

        $validator
            ->allowEmpty('username');

        $validator
            ->allowEmpty('ip_addr');

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
        //$rules->add($rules->isUnique(['username']));
        return $rules;
    }
}
