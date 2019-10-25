<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MerchantWalletServiceTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MerchantWalletServiceTable Test Case
 */
class MerchantWalletServiceTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\MerchantWalletServiceTable
     */
    public $MerchantWalletService;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.merchant_wallet_service',
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
        $config = TableRegistry::exists('MerchantWalletService') ? [] : ['className' => 'App\Model\Table\MerchantWalletServiceTable'];
        $this->MerchantWalletService = TableRegistry::get('MerchantWalletService', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->MerchantWalletService);

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
