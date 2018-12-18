<?php
class Cocote_Export_Block_Adminhtml_System_Config_Checkboxstock extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $checked='';
        if(Mage::getStoreConfig('cocote/generate/in_stock_only', 0)) {
            $checked=" checked='checked' ";
        }

        $html = "
       <input type='hidden' name='groups[generate][fields][in_stock_only][value]' value='0'>
       <input id='cocote_generate_in_stock_only'".$checked." type='checkbox' name='groups[generate][fields][in_stock_only][value]' value='1'>";
        return $html;
    }
}
