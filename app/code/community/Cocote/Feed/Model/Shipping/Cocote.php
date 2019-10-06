<?php

class Cocote_Feed_Model_Shipping_Cocote
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'cocote';

    public function collectRates(Mage_Shipping_Model_Rate_Request $request )
    {
        $reqData=$request->getData('all_items')[0];
        $quote=$reqData->getQuote();

        if($quote->getCocote()!=1) {
            return false;
        }

        $result = Mage::getModel('shipping/rate_result');
        /* @var $result Mage_Shipping_Model_Rate_Result */

        $result->append($this->_getStandardShippingRate());

        return $result;
    }

    protected function _getStandardShippingRate()
    {
        $rate = Mage::getModel('shipping/rate_result_method');
        /* @var $rate Mage_Shipping_Model_Rate_Result_Method */

        $rate->setCarrier($this->_code);
        /**
         * getConfigData(config_key) returns the configuration value for the
         * carriers/[carrier_code]/[config_key]
         */
        $rate->setCarrierTitle($this->getConfigData('title'));

        $rate->setMethod('cocote');
        $rate->setMethodTitle('Cocote');

        $rate->setPrice(4.99);
        $rate->setCost(0);

        return $rate;
    }

    public function getAllowedMethods()
    {
        return array(
            'cocote' => 'Standard',
        );
    }

    public function isActive()
    {
        return false;

//        if($quote->getCocote()==1) {
//            return true;
//        }
//
        $active = $this->getConfigData('active');
        return $active==1 || $active=='true';
    }

}