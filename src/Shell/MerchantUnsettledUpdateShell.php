<?php


namespace App\Shell;

use Cake\Console\ConsoleIo;
use Cake\Console\Shell;

use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

use App\Lib\CakeDbConnector;

use WC\Backoffice\SettlementService;
use WC\Backoffice\MerchantService;

/**
 * A console process for update merchant
 */
class MerchantUnsettledUpdateShell extends Shell
{
    /**
     * Override main() to handle action
     *
     * @return void
     */
    public function main() 
    {
        $this->log('Starting process for reload merchants unsettled summary.', 'info');
            
        $conn = ConnectionManager::get('default');
        
        // Setup driver for query helper.
        CakeDbConnector::setShared($conn);

        
        $settlementService = new SettlementService();
        $settlementService->convertTxAvailableToUnsettled();

        $settlementService->updateMerchantUnsettled();

        $data =$settlementService->getMerchantUnsettled(false);

        if (count($data) > 0) {
            $output = [];
            $fields = array_keys($data[0]);
            $output[] =$fields;
            foreach ($data as $row) {
                $_row = [];
                foreach ($fields as $index => $field) {
                    $_row [$index] = $row[$field];
                }
                $output[] = $_row;
            }
            // array_splice($data, 0, 0, [array_keys($data[0])]);
            // print_r($output);
            // $output = array_merge(array_keys($data), $data);
            $this->log('Results: ', 'info');
            $this->helper('Table')->output($output);
        } else {
            $this->log('No data was found.', 'info');
        }
        

        $this->log('Done.', 'info');
    }
}