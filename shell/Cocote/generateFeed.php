<?php

require_once dirname(__FILE__) . '/../abstract.php';

class Mage_Shell_Cocote_Test extends Mage_Shell_Abstract
{

    /**
     * Run script
     */
    public function run()
    {
        try {
            Mage::getModel('cocote_feed/observer')->generateFeed();
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
                    Usage:  php generateFeed.php -- [options]
USAGE;
    }
}

$shell = new Mage_Shell_Cocote_Test();
$shell->run();
