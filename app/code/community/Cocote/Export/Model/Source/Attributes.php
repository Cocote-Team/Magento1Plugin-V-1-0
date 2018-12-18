<?php
class Cocote_Export_Model_Source_Attributes extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{

    public function toOptionArray()
    {
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->getItems();

        $options = array();
        $options[] = array(
            'value' => '',
            'label' => '---'
        );

        foreach ($attributes as $attribute) {
            if (!$label = $attribute->getFrontendLabel()) {
                $label = $attribute->getAttributecode();
            }

            $options[] = array(
                'value' => $attribute->getAttributecode(),
                'label' => $label
            );
        }

        return $options;
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }

}