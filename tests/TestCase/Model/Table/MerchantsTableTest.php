<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MerchantsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MerchantsTable Test Case
 */
class MerchantsTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\MerchantsTable
     */
    public $Merchants;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.merchants'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('Merchants') ? [] : ['className' => 'App\Model\Table\MerchantsTable'];
        $this->Merchants = TableRegistry::get('Merchants', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Merchants);

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
