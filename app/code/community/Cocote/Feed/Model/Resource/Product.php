<?php
 
class Cocote_Feed_Model_Resource_Product extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('cocote_feed/product', 'id');
    }
}