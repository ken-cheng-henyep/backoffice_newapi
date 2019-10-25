<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TransactionLogTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\TransactionLogTable Test Case
 */
class TransactionLogTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\TransactionLogTable
     */
    public $TransactionLog;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.transaction_log',
        'app.merchants',
        'app.internals',
        'app.states'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('TransactionLog') ? [] : ['className' => 'App\Model\Table\TransactionLogTable'];
        $this->TransactionLog = TableRegistry::get('TransactionLog', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->TransactionLog);

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
