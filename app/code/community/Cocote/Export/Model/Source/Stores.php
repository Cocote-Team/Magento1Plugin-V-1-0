<?php
class Cocote_Export_Model_Source_Stores extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{


    public function toOptionArray()
    {
        $options = Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true);
        return $options;
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }

}