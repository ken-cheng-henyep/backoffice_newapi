<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MerchantTransactionAccountTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MerchantTransactionAccountTable Test Case
 */
class MerchantTransactionAccountTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\MerchantTransactionAccountTable
     */
    public $MerchantTransactionAccount;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.merchant_transaction_account',
        'app.merchants',
        'app.merchant_users',
        'app.remittance_authorization',
        'app.remittance_batch',
        'app.remittance_log',
        'app.transaction_log',
        'app.internals',
        'app.states',
        'app.upload_activity',
        'app.wallets'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('MerchantTransactionAccount') ? [] : ['className' => 'App\Model\Table\MerchantTransactionAccountTable'];
        $this->MerchantTransactionAccount = TableRegistry::get('MerchantTransactionAccount', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->MerchantTransactionAccount);

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
