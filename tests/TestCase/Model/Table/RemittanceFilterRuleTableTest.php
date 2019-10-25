<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\RemittanceFilterRuleTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\RemittanceFilterRuleTable Test Case
 */
class RemittanceFilterRuleTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\RemittanceFilterRuleTable
     */
    public $RemittanceFilterRule;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.remittance_filter_rule',
        'app.filters'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('RemittanceFilterRule') ? [] : ['className' => 'App\Model\Table\RemittanceFilterRuleTable'];
        $this->RemittanceFilterRule = TableRegistry::get('RemittanceFilterRule', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->RemittanceFilterRule);

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
