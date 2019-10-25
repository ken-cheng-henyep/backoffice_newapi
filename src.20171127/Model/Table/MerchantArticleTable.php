<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

use Cake\ORM\Rule\IsUnique;

/**
 * MerchantArticle Model
 *
 * @method \App\Model\Entity\MerchantArticle get($primaryKey, $options = [])
 * @method \App\Model\Entity\MerchantArticle newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\MerchantArticle[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\MerchantArticle|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\MerchantArticle patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantArticle[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantArticle findOrCreate($search, callable $callback = null, $options = [])
 */
class MerchantArticleTable extends Table
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

        $this->table('merchant_article');
        $this->displayField('title');
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
            ->allowEmpty('id', 'create');

        $validator
            ->requirePresence('title', 'create')
            ->notEmpty('title');

        $validator
            ->allowEmpty('content');

        $validator
            ->allowEmpty('path');

        $validator
            ->integer('type')
            ->requirePresence('type', 'create')
            ->notEmpty('type');

        $validator
            ->allowEmpty('username');

        $validator
            ->integer('status')
            ->requirePresence('status', 'create')
            ->notEmpty('status');

        /*
        $validator
            ->dateTime('publish_time')
            ->requirePresence('publish_time', 'create')
            ->notEmpty('publish_time');
*/
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
        //$rules->add($rules->isUnique(['username']));

        //todo: skip if empty path
        $rules->add( $rules->isUnique(
            ['type', 'file_md5'],
            'Same file exists in same section.'
        ));
        /*
        // Add a rule for create.
        $rules->addCreate(function ($entity, $options) {
            // Return a boolean to indicate pass/failure
            //return true;
        }, 'uniqueFile');
*/
        return $rules;
    }
}
