<?php

require_once dirname(__FILE__) . '/../abstract.php';

class Mage_Shell_Cocote_SendUpdates extends Mage_Shell_Abstract
{

    /**
     * Run script
     */
    public function run()
    {
        try {
            Mage::getModel('cocote_feed/observer')->sendPriceStockToCocote();
        } catch (Exception $e) {
            Mage::log($e->getMessage());
        }
    }

    /**
     * Retrieve Usage Help Message
     */
    public function usageHelp()
    {
        return
            <<<USAGE
                    Usage:  php sendUpdates.php -- [options]
USAGE;
    }
}

$shell = new Mage_Shell_Cocote_SendUpdates();
$shell->run();
