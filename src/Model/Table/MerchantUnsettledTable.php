<?php 


namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class MerchantUnsettledTable extends Table 
{

    /*
    * Initialize method
    *
    * @param array $config The configuration for the Table.
    * @return void
    */
   public function initialize(array $config)
   {
       parent::initialize($config);

       $this->table('merchants_unsettled');
       $this->displayField('id');
       $this->primaryKey('id');
       $this->alias('mu');

       $this->belongsTo('Merchants', [
           'foreignKey' => 'merchant_id',
           'joinType' => 'INNER'
       ]);

       $this->belongsTo('MerchantsGroupId', [
           'foreignKey' => 'merchant_id',
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


        return $validator;
    }

}