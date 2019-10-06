<?php

class Cocote_Feed_Model_Payment_Cocote extends Mage_Payment_Model_Method_Abstract
{

    protected $_code = 'cocote';

    protected $_isInitializeNeeded      = true;
    protected $_canUseInternal          = false;
    protected $_canUseForMultishipping  = false;

    /**
     * Return Order place redirect url
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
//when you click on place order you will be redirected on this url, if you don't want this action remove this method
        return Mage::getUrl('customcard/standard/redirect', array('_secure' => true));
    }

    public function isAvailable($quote = null)
    {
        $checkResult = new StdClass;
        $isActive = (bool)(int)$this->getConfigData('active', $quote ? $quote->getStoreId() : null);
        if($quote->getCocote()==1) {
            $isActive=1;
        }

        $checkResult->isAvailable = $isActive;
        $checkResult->isDeniedInConfig = !$isActive; // for future use in observers
        Mage::dispatchEvent('payment_method_is_active', array(
            'result'          => $checkResult,
            'method_instance' => $this,
            'quote'           => $quote,
        ));

        if ($checkResult->isAvailable && $quote) {
            $checkResult->isAvailable = $this->isApplicableToQuote($quote, self::CHECK_RECURRING_PROFILES);
        }
        return $checkResult->isAvailable;
    }

    //getRates get info from quote



}
