<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class MerchantGroupTable extends Table
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

        $this->table('merchants_group');
        $this->displayField('id');
        $this->displayField('name');
        $this->primaryKey('id');
        $this->alias('mg');

        $this->hasMany('MerchantGroupId', [
            'foreignKey' => 'id',
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



    /**
     * Gets the master merchant. contains extra fields 'merchantgroup_id',
     * 'merchantgroup_name', and 'daily_fxrate' for fx package 2 merchants
     *
     * @param      string             $merchant_id  The merchant identifier
     *
     * @throws     NotFoundException  Merchant group or master merchant does not find.
     *
     * @return     Entity              The master merchant entity object
     */
    public function getMasterMerchant($merchant_id, $loadSettlementRate = false)
    {
        // $merchant = $this->Merchant->get($merchantgroup_id);
        $query = $this->find('all')
            ->where([
                'mgi.merchant_id'=>$merchant_id
            ])
            ->join([
                'mgi'=>[
                    'table'=>'merchants_group_id',
                    'conditions'=>'mgi.id = mg.id'
                ],
                'mgii'=>[
                    'table'=>'merchants_group_id',
                    'conditions'=>'mgi.id = mgii.id AND mgii.master = 1',
                    'select'=>[
                        'master_merchant_id'=>'mgii.merchant_id',
                    ]
                ],
            ]);
            
        $merchantgroup_total = $query->count();
        if ($merchantgroup_total > 1) {
            // print_r($merchantgroup_query);
            // exit;
            throw new \Exception('Master merchant setting is not correct.');
        }
        if ($merchantgroup_total < 1) {
            // print_r($merchantgroup_query);
            // exit;
            throw new \Exception('Master merchant does not exist.');
        }
        $merchantgroups = $query->toArray();
        $merchantgroup = $merchantgroups[0];
        
        // Get the record for master merchant
        $masterMerchant = $this->Merchants->get($merchantgroup['master_merchant_id']);

        // Transalte into array format.
        $masterMerchant = $masterMerchant->toArray();

        // For FX Package 2, need to check is the rate available today.
        if ($loadSettlementRate && $masterMerchant['settle_option'] == '2') {
            if (empty($masterMerchant['settle_rate_symbol'])) {
                throw new \Exception('Merchant\'s settlement rate symbol is missing.');
            }

            $now = date('Y-m-d H:i:s');

            $rateQuery = $this->SettlementRate->find('all');
            $rateQuery-> where([
                'rate_symbol'=> $masterMerchant['settle_rate_symbol'],
                'modify_time >='=>date('Y-m-d').' 00:00:00',
            ]);
            if ($rateQuery->count() < 1) {
                throw new \Exception('The data of Settlement FX Rate is out-of-date.');
            }
            $rateRows = $rateQuery->toArray();
            $rateRow = $rateRows[0];
            $masterMerchant['daily_fxrate'] = $rateRow['rate_value'];
        }

        $masterMerchant['merchantgroup_id'] = $merchantgroup['id'];
        $masterMerchant['merchantgroup_name'] = $merchantgroup['name'];

        return $masterMerchant;
    }
}
