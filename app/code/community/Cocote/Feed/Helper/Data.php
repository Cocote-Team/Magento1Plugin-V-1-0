<?php

class Cocote_Feed_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function getFilePath()
    {
        $path = Mage::getStoreConfig('cocote/generate/path');

        $dirPath = Mage::getBaseDir() . DS . $path;

        $io = new Varien_Io_File();
        if (!$io->fileExists($dirPath, false)) {
            $io->mkdir($dirPath);
        }

        return $dirPath . DS . $this->getFileName();
    }


    public function getFileLink()
    {
        $path = Mage::getStoreConfig('cocote/generate/path');

        $dirPath = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true) . $path;

        return $dirPath . '/' . $this->getFileName();
    }

    public function getFileName()
    {
        $fileName = Mage::getStoreConfig('cocote/generate/filename');

        if (!$fileName) {
            $fileName = $this->generateRandomString() . '.xml';
            Mage::getConfig()->saveConfig('cocote/generate/filename', $fileName, 'default', 0);
            Mage::app()->getCacheInstance()->cleanType('config');
            Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => 'config'));
        }

        return $fileName;
    }

    public function generateRandomString($length = 8)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    protected function getDefaultStoreView()
    {
        $defaultStoreView = Mage::getStoreConfig('cocote/catalog/store');
        if ($defaultStoreView) {
            return $defaultStoreView;
        }
        $defaultStoreView = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
        return $defaultStoreView;
    }

    public function saveToken($token,$orderId) {
        $resource = Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $query="insert into cocote_token (order_id,token) values ('".$orderId."','".$token."')";
        $writeConnection->query($query);
        setcookie('Cocote-token', null, -1, '/');
    }

    public function getToken($orderId) {
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');

        $query = 'SELECT token FROM cocote_token WHERE order_id = ' . (int)$orderId . ' LIMIT 1';
        $token = $readConnection->fetchOne($query);
        return $token;

    }

    public function createOrder($data) {
        $defaultStoreView = Mage::getStoreConfig('cocote/catalog/store');
        if ($defaultStoreView) {
            return $defaultStoreView;
        }

        $defaultStoreView = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();

        Mage::app()->setCurrentStore($defaultStoreView); // adjust according to config setting

//        $store = Mage::app()->getStore();
//        $website = Mage::app()->getWebsite();

// initialize sales quote object
        $quote = Mage::getModel('sales/quote')->setStoreId($defaultStoreView);
        $quote->setData($data['customer_data']);
        $quote->setCocote(1);
        $quote->setCurrency(Mage::app()->getStore()->getBaseCurrencyCode());

// add products to quote
        foreach($data['products'] as $productId => $qty) {
            $product = Mage::getModel('catalog/product')->load($productId);
            $quote->addProduct($product, $qty);
        }

// add billing address to quote
        $billingAddressData = $quote->getBillingAddress()->addData($data['billing_address']);

// add shipping address to quote
        $shippingAddressData = $quote->getShippingAddress()->addData($data['shipping_address']);

// collect shipping rates on quote
        $shippingAddressData->setCollectShippingRates(true)
            ->collectShippingRates();

// set shipping method and payment method on the quote

        $shippingAddressData->setShippingMethod('cocote_cocote')
            ->setPaymentMethod('cocote');

// Set payment method for the quote

//        Mage::getSingleton('checkout/session')->setCocote(1);
        $quote->setCocote(1);
        $quote->setCocoteShippingPrice(6);
        $quote->getPayment()->importData(array('method' => 'cocote'));
        try {
            $quote->collectTotals()->save();

            // create order from quote
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $increment_id = $service->getOrder()->getRealOrderId();

            echo 'Order Id: ' .$increment_id;

        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function testOrderCreate() {
        $data=[];

        $productIds = array(666 => 1);
        $data['products']=$productIds;

        $customerData=[
            'customer_is_guest'=>1,
            'customer_firstname'=>'fn',
            'customer_lastname'=>'lasnt',
            'customer_email'=>'jaki@taki.pl'
        ];
        $data['customer_data']=$customerData;

        $billingAddress = array(
            'firstname' => 'first',
            'middlename' => '',
            'lastname' => 'lastname',
            'company' => '',
            'street' => array(
                '0' => 'Thunder River Boulevard', // required
                '1' => 'Customer Address 2' // optional
            ),
            'city' => 'Teramuggus',
            'country_id' => 'FR', // country code
            'region' => 'Alaska',
            'region_id' => '2',
            'postcode' => '99767',
            'telephone' => '123-456-7890',
        );

        $shippingAddress = array(
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'company' => '',
            'street' => array(
                '0' => 'Thunder River Boulevard', // required
                '1' => 'Customer Address 2' // optional
            ),
            'city' => 'Teramuggus',
            'country_id' => 'FR',
            'region' => 'Alaska',
            'region_id' => '2',
            'postcode' => '99767',
            'telephone' => '123-456-7890',
            'cocote' => 'true',
        );

        $data['billing_address']= $billingAddress;
        $data['shipping_address'] =$shippingAddress;
        $this->createOrder($data);
    }

}