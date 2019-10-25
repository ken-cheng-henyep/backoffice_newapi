<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GPayTransactionLog Model
 *
 * @property \Cake\ORM\Association\BelongsTo $TransactionLog
 *
 * @method \App\Model\Entity\GPayTransactionLog get($primaryKey, $options = [])
 * @method \App\Model\Entity\GPayTransactionLog newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\GPayTransactionLog[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GPayTransactionLog|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\GPayTransactionLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GPayTransactionLog[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\GPayTransactionLog findOrCreate($search, callable $callback = null)
 */
class GPayTransactionLogTable extends Table
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

        $this->table('gpay_transaction_log');
        $this->displayField('id');
        $this->primaryKey('id');

        // $this->belongsTo('SettlementTransactionLog', [
        //     //'foreignKey' => 'merchant_order_no',
        // ]);

    }

}
