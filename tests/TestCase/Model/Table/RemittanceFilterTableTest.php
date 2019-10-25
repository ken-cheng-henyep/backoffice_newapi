<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RemittanceFilterTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RemittanceFilterTable Test Case
 */
class RemittanceFilterTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\RemittanceFilterTable
     */
    public $RemittanceFilter;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.remittance_filter',
        'app.merchants',
        'app.merchant_users',
        'app.remittance_authorization',
        'app.remittance_batch',
        'app.remittance_log',
        'app.transaction_log',
        'app.internals',
        'app.states',
        'app.upload_activity'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('RemittanceFilter') ? [] : ['className' => 'App\Model\Table\RemittanceFilterTable'];
        $this->RemittanceFilter = TableRegistry::get('RemittanceFilter', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RemittanceFilter);

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
