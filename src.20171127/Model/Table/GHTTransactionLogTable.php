<?php
namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GHTTransactionLog Model
 *
 * @property \Cake\ORM\Association\BelongsTo $TransactionLog
 *
 * @method \App\Model\Entity\GHTTransactionLog get($primaryKey, $options = [])
 * @method \App\Model\Entity\GHTTransactionLog newEntity($data = null, array $options = [])
 * @method \App\Model\Entity\GHTTransactionLog[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GHTTransactionLog|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\GHTTransactionLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GHTTransactionLog[] patchEntities($entities, array $data, array $options = [])
 * @method \App\Model\Entity\GHTTransactionLog findOrCreate($search, callable $callback = null)
 */
class GHTTransactionLogTable extends Table
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

        $this->table('ght_transaction_log');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->belongsTo('SettlementTransactionLog', [
            'foreignKey' => 'transaction_id',
        ]);

    }

}
