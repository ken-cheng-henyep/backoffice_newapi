<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RemittanceApiLogTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RemittanceApiLogTable Test Case
 */
class RemittanceApiLogTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\RemittanceApiLogTable
     */
    public $RemittanceApiLog;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.remittance_api_log',
        'app.batches',
        'app.logs',
        'app.reqs'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('RemittanceApiLog') ? [] : ['className' => 'App\Model\Table\RemittanceApiLogTable'];
        $this->RemittanceApiLog = TableRegistry::get('RemittanceApiLog', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RemittanceApiLog);

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
