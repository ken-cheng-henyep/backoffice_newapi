<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MerchantTransactionTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MerchantTransactionTable Test Case
 */
class MerchantTransactionTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\MerchantTransactionTable
     */
    public $MerchantTransaction;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.merchant_transaction',
        'app.merchants',
        'app.merchant_users',
        'app.remittance_authorization',
        'app.remittance_batch',
        'app.remittance_log',
        'app.transaction_log',
        'app.internals',
        'app.states',
        'app.upload_activity',
        'app.wallets',
        'app.reves'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('MerchantTransaction') ? [] : ['className' => 'App\Model\Table\MerchantTransactionTable'];
        $this->MerchantTransaction = TableRegistry::get('MerchantTransaction', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->MerchantTransaction);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
