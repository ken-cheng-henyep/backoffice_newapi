<?php
/**
 * Routes configuration
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different URLs to chosen controllers and their actions (functions).
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Core\Plugin;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

/**
 * The default class to use for all routes
 *
 * The following route classes are supplied with CakePHP and are appropriate
 * to set as the default:
 *
 * - Route
 * - InflectedRoute
 * - DashedRoute
 *
 * If no call is made to `Router::defaultRouteClass()`, the class used is
 * `Route` (`Cake\Routing\Route\Route`)
 *
 * Note that `Route` does not do any inflections on URLs which will result in
 * inconsistently cased URLs when used with `:plugin`, `:controller` and
 * `:action` markers.
 *
 */
Router::defaultRouteClass('DashedRoute');

Router::scope('/', function (RouteBuilder $routes) {
    /**
     * Here, we are connecting '/' (base path) to a controller called 'Pages',
     * its action called 'display', and we pass a param to select the view file
     * to use (in this case, src/Template/Pages/home.ctp)...
     */
    //$routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
    //FX rate
    // summary json
    $routes->connect('/fxrate/transactionJson/:request', ['controller' => 'TransactionLog', 'action' => 'transactionJson'], ['pass'=>['request']]);
    $routes->connect('/fxrate/dateform', ['controller' => 'TransactionLog', 'action' => 'dateform']);
    $routes->connect('/fxrate/file/**', ['controller' => 'TransactionLog', 'action'=>'serveFile']);
    $routes->connect('/fxrate/:action/', ['controller' => 'TransactionLog']);
    $routes->connect('/fxrate/', ['controller' => 'TransactionLog', 'action' => 'index']);
    //Remittance
    //$routes->connect('/remittance/list/', ['controller' => 'RemittanceBatch', 'action'=>'index']);
    $routes->connect('/remittance/update/', ['controller' => 'RemittanceBatch', 'action'=>'updateStatus']);
    $routes->connect('/remittancelog/update/', ['controller' => 'RemittanceBatch', 'action'=>'updateLogStatus']);
    $routes->connect('/remittance/search/json/', ['controller' => 'RemittanceBatch', 'action'=>'jsonList']);
    $routes->connect('/remittance/json/', ['controller' => 'RemittanceBatch', 'action'=>'jsonList']);
    $routes->connect('/remittance/api-json/', ['controller' => 'RemittanceBatch', 'action'=>'apiLogJson']);

    //search instant
    $routes->connect('/remittance/batch/txsearch-:id/', ['controller' => 'RemittanceBatch', 'action'=>'searchInstant'], ['pass' => ['id']]);
    $routes->connect('/remittance/batch/json-:id/', ['controller' => 'RemittanceBatch', 'action'=>'viewJSON'], ['pass' => ['id']]);
    $routes->connect('/remittance/batch/target-json', ['controller' => 'RemittanceBatch', 'action'=>'targetJSON']);
    //$routes->connect('/remittance/batch/file/:fname/', ['controller' => 'RemittanceBatch', 'action'=>'serveStaticFile'], ['pass' => ['fname']]);
    $routes->connect('/remittance/batch/file/**', ['controller' => 'RemittanceBatch', 'action'=>'serveStaticFile']);
    $routes->connect('/remittance/batch/*', ['controller' => 'RemittanceBatch', 'action'=>'view']);
    $routes->connect('/remittance/:action/', ['controller' => 'RemittanceBatch']);
    $routes->connect('/remittance/', ['controller' => 'RemittanceBatch', 'action'=>'index']);

    //Merchant Transaction
    $routes->connect('/merchant/balance/file/**', ['controller' => 'MerchantTransaction', 'action'=>'serveFile']);
    $routes->connect('/merchant/balance/view-json/:mid/:wallet', ['controller' => 'MerchantTransaction', 'action'=>'viewJson'], ['pass' => ['mid','wallet']]);
    $routes->connect('/merchant/balance/view-json/*', ['controller' => 'MerchantTransaction', 'action'=>'viewJson']);
    $routes->connect('/merchant/balance/view/*', ['controller' => 'MerchantTransaction', 'action'=>'view']);
    $routes->connect('/merchant/balance/list-dl/', ['controller' => 'MerchantTransaction', 'action'=>'index', true]);
    $routes->connect('/merchant/balance/:action/*', ['controller' => 'MerchantTransaction']);
    $routes->connect('/merchant/balance/:action/', ['controller' => 'MerchantTransaction']);
/*
    // Settlement Transaction Search
    $routes->connect('/settlement/process/:action/', ['controller'=>'SettlementProcess']);
    $routes->connect('/settlement/process', ['controller' => 'SettlementProcess', 'action'=>'index']);

    // Settlement Transaction Search
    $routes->connect('/settlement/txsearch/:action/', ['controller'=>'SettlementTransaction']);
    $routes->connect('/settlement/txsearch/file/**', ['controller' => 'SettlementTransaction', 'action'=>'serveFile']);
    $routes->connect('/settlement/txsearch/', ['controller'=>'SettlementTransaction', 'action'=>'index']);

    // Settlement Rate
    $routes->connect('/settlement/rate/:action/', ['controller'=>'SettlementRate']);
    $routes->connect('/settlement/rate/', ['controller'=>'SettlementRate', 'action'=>'index']);
*/
    //Remittance API
    //The b1 version only checks ID card number if it is not empty
    $routes->connect('/api/remittance/sequential_request/b1/', ['controller' => 'RemittanceApi', 'action'=>'singleRequest',['id card no']]);
    $routes->connect('/api/remittance/sequential_request/', ['controller' => 'RemittanceApi', 'action'=>'singleRequest']);

    $routes->connect('/api/remittance/batch_request/:request/', ['controller' => 'RemittanceApi', 'action'=>'batchRequest'], ['pass'=>['request']]);
    $routes->connect('/api/remittance/instant_request/status/', ['controller' => 'RemittanceApi', 'action'=>'instantRequestStatus']);
    $routes->connect('/api/remittance/:action/', ['controller' => 'RemittanceApi',]);
    //Settlement API
    $routes->connect('/api/settlement/transaction/status', ['controller' => 'SettlementApi', 'action'=>'transactionStatus']);
    //Account Balance API
    $routes->connect('/api/balances', ['controller' => 'BalanceApi', 'action'=>'listBalances']);
    $routes->connect('/api/balance/:action/', ['controller' => 'BalanceApi',]);

    //China GPay API
    $routes->connect('/gpay/:action/', ['controller' => 'ChinaGPay',]);
    $routes->connect('/gpay/', ['controller' => 'ChinaGPay', 'action'=>'index']);

    //CIVS report
    $routes->connect('/civs_report/:action/', ['controller' => 'CivsReport',]);

    // Holiday
    $routes->connect('/holiday/ical/preview/*', ['controller'=>'Holidays','action'=>'previewCal']);
    $routes->connect('/holiday/ical/import/*', ['controller'=>'Holidays','action'=>'importCal']);
    $routes->connect('/holiday/:action/', ['controller'=>'Holidays']);
    $routes->connect('/holiday/', ['controller' => 'Holidays', 'action' => 'index']);

    //Information Section
    $routes->connect('/info/:action/*', ['controller' => 'MerchantArticle',]);

    // Filter for Instant Remittance
    $routes->connect('/filter/:action/*', ['controller' => 'RemittanceFilter',]);
    $routes->connect('/filter/', ['controller' => 'RemittanceFilter', 'action' => 'blacklist']);

    // Settlement Transaction Search
    $routes->connect('/settlement/txsearch/:action/', ['controller'=>'SettlementTransaction']);
    $routes->connect('/settlement/txsearch/file/**', ['controller' => 'SettlementTransaction', 'action'=>'serveFile']);
    $routes->connect('/settlement/txsearch/', ['controller'=>'SettlementTransaction', 'action'=>'index']);
	
    // Reconciliation
    $routes->connect('/reconciliation/:action/**', ['controller'=>'Reconciliation']);
    $routes->connect('/reconciliation/', ['controller'=>'Reconciliation', 'action'=>'index']);

    // Settlement Process
    $routes->connect('/settlement/process/:action/**', ['controller'=>'SettlementProcess']);
    $routes->connect('/settlement/process/', ['controller'=>'SettlementProcess', 'action'=>'index']);

    // Settlement Rate
    $routes->connect('/settlement/rate/:action/**', ['controller'=>'SettlementRate']);
    $routes->connect('/settlement/rate/', ['controller'=>'SettlementRate', 'action'=>'index']);

    // Queue Job
    $routes->connect('/queue/job/:action/**', ['controller'=>'QueueJob']);
    $routes->connect('/queue/job/:action', ['controller'=>'QueueJob', ]);

    // Merchant Setup
    $routes->connect('/merchants/:action/*', ['controller' => 'Merchants',]);

    /**
     * ...and connect the rest of 'Pages' controller's URLs.
     */
    //$routes->connect('/report/transaction', ['controller' => 'TransactionLog', 'action' => 'dateform']);

    //$routes->connect('/pages/*', ['controller' => 'Pages', 'action' => 'dateform']);
    $routes->connect('/pages/*', ['controller' => 'Pages', 'action' => 'display']);
    $routes->connect('/console/', ['controller' => 'Pages', 'action' => 'display', 'index']);
    //$routes->connect('/login/*', ['controller' => 'Pages', 'action' => 'dateform']);
    $routes->connect('/login/', ['controller' => 'Users', 'action' => 'login']);
    $routes->connect('/:action/*', ['controller' => 'Users']);
    //$routes->connect('/', ['controller' => 'Pages','action' => 'display','index']);
    //landing page
    $routes->connect('/', ['controller' => 'TransactionLog', 'action' => 'dateform']);
    /**
     * Connect catchall routes for all controllers.
     *
     * Using the argument `DashedRoute`, the `fallbacks` method is a shortcut for
     *    `$routes->connect('/:controller', ['action' => 'index'], ['routeClass' => 'DashedRoute']);`
     *    `$routes->connect('/:controller/:action/*', [], ['routeClass' => 'DashedRoute']);`
     *
     * Any route class can be used with this method, such as:
     * - DashedRoute
     * - InflectedRoute
     * - Route
     * - Or your own route class
     *
     * You can remove these routes once you've connected the
     * routes you want in your application.
     */
    $routes->fallbacks('DashedRoute');
});

/**
 * Load all plugin routes.  See the Plugin documentation on
 * how to customize the loading of plugin routes.
 */
Plugin::routes();
