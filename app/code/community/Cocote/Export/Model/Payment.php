<?php
class Cocote_Export_Model_Payment extends Mage_Payment_Model_Method_Abstract
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
    //$session = Mage::getSingleton(‘checkout/session’);
}

}