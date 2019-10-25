<?php
namespace App\Test\TestCase\Model\Table;

use App\Model\Table\MerchantArticleTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\MerchantArticleTable Test Case
 */
class MerchantArticleTableTest extends TestCase
{

    /**
     * Test subject
     *
     * @var \App\Model\Table\MerchantArticleTable
     */
    public $MerchantArticle;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'app.merchant_article'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::exists('MerchantArticle') ? [] : ['className' => 'App\Model\Table\MerchantArticleTable'];
        $this->MerchantArticle = TableRegistry::get('MerchantArticle', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->MerchantArticle);

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
