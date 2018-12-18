<?php
class Cocote_Export_Block_Adminhtml_System_Config_Link extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $url = Mage::helper('cocote_export')->getFileLink();

        stream_context_set_default(
            array(
                'http' => array(
                    'method' => 'HEAD'
                )
            )
        );
        $headers = get_headers($url, 1);
        $fileFound = stristr($headers[0], '200');

        if($fileFound) {
            $html='<a target="_blank" href="'.$url.'">'.$url.'</a>';
        }
        else {
            $html=$url;
        }

        return $html;
    }
}