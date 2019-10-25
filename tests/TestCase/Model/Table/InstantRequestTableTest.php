<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\InstantRequestTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\InstantRequestTable Test Case
 */
class InstantRequestTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\InstantRequestTable
     */
    public $InstantRequest;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.instant_request',
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
        $config = TableRegistry::exists('InstantRequest') ? [] : ['className' => 'App\Model\Table\InstantRequestTable'];
        $this->InstantRequest = TableRegistry::get('InstantRequest', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->InstantRequest);

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
