<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Routing\Router;
use Cake\Log\Log;

use MerchantWallet;
use RemittanceReportReader;

/**
 * MerchantTransaction Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Merchants
 * @property \Cake\ORM\Association\BelongsTo $Wallets
 * @property \Cake\ORM\Association\BelongsTo $Reves
 *
 * @method \App\Model\Entity\MerchantTransaction get($primaryKey, $options = [])
 * @method \App\Model\Entity\MerchantTransaction newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\MerchantTransaction[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\MerchantTransaction|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\MerchantTransaction patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantTransaction[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\MerchantTransaction findOrCreate($search, callable $callback = null, $options = [])
 */
class MerchantTransactionTable extends Table
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

        $this->table('merchant_transaction');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('Merchants', [
            'foreignKey' => 'merchant_id',
            'joinType' => 'INNER'
        ]);
        $this->belongsTo('Wallets', [
            'foreignKey' => 'wallet_id'
        ]);
        /*
        $this->belongsTo('Reves', [
            'foreignKey' => 'ref_id'
        ]);
        */
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
            ->integer('type')
            ->allowEmpty('type');

        $validator
            ->allowEmpty('dsc');

        $validator
            ->numeric('amount')
            ->requirePresence('amount', 'create')
            ->notEmpty('amount');

        $validator
            ->numeric('balance')
            ->requirePresence('balance', 'create')
            ->notEmpty('balance');

        $validator
            ->allowEmpty('username');

        $validator
            ->allowEmpty('remarks');

        $validator
            ->integer('latest')
            ->allowEmpty('latest');

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
        $rules->add($rules->existsIn(['merchant_id'], 'Merchants'));
        $rules->add($rules->existsIn(['wallet_id'], 'Wallets'));
        //$rules->add($rules->existsIn(['ref_id'], 'Reves'));

        return $rules;
    }

    public function getTransactions($id, $wallet_id = null, $start = null, $end = null) {
        //$this->log("getTransactions($id, $wallet_id, $start, $end)",'debug');
        Log::write('debug', "getTransactions($id, $wallet_id, $start, $end)");

        $startdate = (is_null($start)?date('Y/m/d 00:00:00'):date('Y/m/d 00:00:00', strtotime($start)));
        $endtime = (is_null($end)?strtotime('+1 day'):strtotime('+1 day', strtotime($end)));
        $enddate = date('Y/m/d 00:00:00', $endtime);

        $this->wallet = new MerchantWallet();
        $this->wallet->setMerchant($id);
        $this->wallet->setWallet($wallet_id);
        $currency = $this->wallet->getWalletCurrency($wallet_id);
        Log::write('debug', "wallet ($wallet_id): $currency");
        //$query = $this->MerchantTransaction->find()
        $query = $this->find()
            ->contain(['Merchants'])
            ->select(['merchant_id', 'type', 'amount', 'balance', 'create_time', 'username', 'remarks', 'ref_id', 'merchant_name'=>'Merchants.name',])
            //->select([$this->MerchantTransaction, $this->Merchants.name])
            ->where(['merchant_id' => $id, 'wallet_id' => $wallet_id, 'create_time >='=> $startdate, 'create_time <'=> $enddate])
            ->order(['create_time' => 'asc']);
        /*
$query->map(function ($r) {
    //if (!isset($r['type_name']))
    $r->typeName = \MerchantWallet::getTypeName($r->type);
    return $r;
});
*/

        $query->formatResults(function (\Cake\Collection\CollectionInterface $results) {
            return $results->map(function ($r) {
                if (!isset($r['type_name']))
                    $r['type_name'] = MerchantWallet::getTypeName($r['type']);
                if (isset($r['ref_id'])) {
                    $url = Router::url(['controller'=>'RemittanceBatch','action' => 'view', $r['ref_id']]) ;
                    $irurl = Router::url(['controller'=>'RemittanceBatch','action' => 'searchInstant', $r['ref_id']]) ;
                    switch ($r['type']) {
                        case MerchantWallet::TYPE_BATCH_REMITTANCE:
                            $r['remarks'] = sprintf("Batch <a href='%s'>%s</a> approved", $url, $r['ref_id']);
                            break;
                        case MerchantWallet::TYPE_BATCH_REMITTANCE_ADJUSTMENT:
                        case MerchantWallet::TYPE_BATCH_REMITTANCELOG_ADJUSTMENT:
                            $r['remarks'] = sprintf("Batch <a href='%s'>%s</a> transaction status updated", $url, $r['ref_id']);
                            //$r['remarks_url'] = Router::url(['controller'=>'RemittanceBatch','action' => 'view', $r['ref_id']]) ;
                            break;
                        case MerchantWallet::TYPE_INSTANT_REMITTANCE:
                            $r['remarks'] = sprintf("Transaction <a href='%s'>%s</a>", $irurl, $r['ref_id']);
                            break;
                        case MerchantWallet::TYPE_INSTANT_REMITTANCE_FAILED_ADJUSTMENT:
                            $r['remarks'] = sprintf("Transaction <a href='%s'>%s</a> failed", $irurl, $r['ref_id']);
                            break;
                        case MerchantWallet::TYPE_INSTANT_REMITTANCE_ADMIN_ADJUSTMENT:
                            $r['remarks'] = sprintf("Transaction <a href='%s'>%s</a> status updated", $irurl, $r['ref_id']);
                            break;
                    }
                }
                //global $currency;
                //$r['currency'] = $currency;
                return $r;
            });
        });

        $total = $query->count();
        $query->hydrate(false);
        $res = $query->toArray();

        $dayFormat = 'Y-m-d 00:00:00';
        // Add Opening Balance
        if (isset($res[0]) && is_array($res[0])) {
            //$this->log($res[0],'debug');
            $opens = $res[0];
            $opens['create_time'] = date($dayFormat, strtotime($res[0]['create_time']));
            $opens['amount'] = 0;
            $opens['balance'] = $res[0]['balance'] - $res[0]['amount'];
            $opens['type'] = MerchantWallet::TYPE_OPEN_BALANCE;
            $opens['type_name'] = MerchantWallet::getTypeName(MerchantWallet::TYPE_OPEN_BALANCE);
            //$opens = compact('create_time', 'balance', 'type_name');
            $opens['username'] = $opens['remarks'] = '';
            //$this->log($opens,'debug');
            $res = array_merge([$opens], $res);
            $total++;
        } elseif ($total==0) {
            // Get previous balance
            $txs = $this->wallet->getPreviousBalances($startdate, $id);
            //$this->log("getPreviousBalance=$balance",'debug');
            //$this->log($txs, 'debug');

            if (is_array($txs)) {
                $res[] = [
                    'create_time' => date($dayFormat, strtotime($startdate)),
                    'amount' => 0,
                    'balance' => floatval($txs['balance']),
                    'merchant_id' => $id,
                    'merchant_name' => $txs['merchant_name'],
                    'type' => MerchantWallet::TYPE_OPEN_BALANCE,
                    'type_name' => MerchantWallet::getTypeName(MerchantWallet::TYPE_OPEN_BALANCE),
                    'username' => '',
                    'remarks' => '',
                ];
            } else {
                //no record
                $merchant = $this->Merchants->get($id, [
                    'contain' => []]);
                //No transaction at all
                $res[] = [
                    'create_time' => date($dayFormat, strtotime($startdate)),
                    'amount' => 0,
                    'balance' => 0,
                    'merchant_id' => $id,
                    //'merchant_name' => $merchant['name'],
                    'merchant_name' => $merchant->name,
                    'type' => MerchantWallet::TYPE_OPEN_BALANCE,
                    'type_name' => MerchantWallet::getTypeName(MerchantWallet::TYPE_OPEN_BALANCE),
                    'username' => '',
                    'remarks' => '',
                ];
            }
            $total++;
        }

        return $res;
        //$data = ['data'=>$res, 'total'=>$total];
    }
}
