<?php
class Cocote_Feed_Block_Adminhtml_System_Config_Buttongenerate extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $url = $this->getUrl('*/cocote/generate');

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel($this->__('Generate Now !'))
            ->setOnClick("setLocation('$url')")
            ->toHtml();

        $html .= '<h1 style="display:none" id="button_change_message2">' . $this->__(
            'Please save configuration first'
        ) . '</h1>';
        return $html;
    }
}