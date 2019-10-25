<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ProcessorsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ProcessorsTable Test Case
 */
class ProcessorsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\ProcessorsTable
     */
    public $Processors;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.processors'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('Processors') ? [] : ['className' => 'App\Model\Table\ProcessorsTable'];
        $this->Processors = TableRegistry::get('Processors', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Processors);

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
}
