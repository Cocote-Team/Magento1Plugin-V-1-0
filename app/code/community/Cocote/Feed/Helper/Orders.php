<?php

class Cocote_Feed_Helper_Orders extends Mage_Core_Helper_Abstract
{

    public function testOrderCreate()
    {
        $ordersNew=$this->getOrdersFromCocote();
        if(isset($ordersNew['orders'])) {
            foreach($ordersNew['orders'] as $orderData) {
//                print_r($orderData);
                $data=$this->prepareOrderData($orderData);
//                print_r($data);
                $this->createOrder($data);
            }
        }
        else {
            Mage::log($ordersNew['errors'], null, 'cocote.log');
        }
    }

    public function getOrdersFromCocote()
    {
        try {
            if (!function_exists('curl_version')) {
                throw new Exception('no curl');
            }
            $key = Mage::getStoreConfig('cocote/catalog/shop_key');
            $shopId = Mage::getStoreConfig('cocote/catalog/shop_id');

            $shopId = 55;
            $key = 'd2e79d74a361f1d87ffdc519e0d2b941cbef0f395a779fb8bfcdc32a8e8b963c9205b40cfc09272a0aa262cc6bbfe6e8919e88e1d34a66417717775897c1eafb';


            $headers = [
                "X-Shop-Id: ".$shopId,
                "X-Secret-Key: ".$key,
                'X-Site-Version: Magento ' . Mage::getVersion(),
                'X-Plugin-Version:' . (string)Mage::getConfig()->getNode('modules/Cocote_Feed/version'),
            ];

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://fr.cocote.com/api/shops/v2/get-last-orders",
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => $headers,
            ]);

            $result = curl_exec($curl);
            $resultArray = json_decode($result, true);

            curl_close($curl);
            return $resultArray;
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'cocote.log');
        }
    }

    public function createOrder($data)
    {

        $prefix=Mage::getStoreConfig('cocote/catalog/prefix');
        $defaultStoreView = Mage::getStoreConfig('cocote/catalog/store');
        if ($defaultStoreView) {
            return $defaultStoreView;
        }

        $shippingPrice=$data['shipping_cost'];

        $defaultStoreView = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStoreId();
        Mage::app()->setCurrentStore($defaultStoreView); // adjust according to config setting

        $quote = Mage::getModel('sales/quote')->setStoreId($defaultStoreView);
        $quote->setData($data['customer_data']);
        $quote->setCocote(1);
        $quote->setCocoteShippingPrice($shippingPrice);
        $quote->setCurrency(Mage::app()->getStore()->getBaseCurrencyCode());

        foreach ($data['products'] as $request) {
            $item=$quote->addProduct($request['prod'], $request['params']);
            $quote->save();

            $customPrice=$request['params']['custom_price'];

            if($item->getParentItemId()) {
                $parentItem=$item->getParentItem();
                $parentItem->setIsSuperMode(1);
                $parentItem->setCustomPrice($customPrice);
                $parentItem->setOriginalCustomPrice($customPrice);
                $parentItem->save();
            }
            $item->setIsSuperMode(1);
            $item->setCustomPrice($customPrice);
            $item->setOriginalCustomPrice($customPrice);
            $item->save();
        }

        $billingAddressData = $quote->getBillingAddress()->addData($data['billing_address']);
        $shippingAddressData = $quote->getShippingAddress()->addData($data['shipping_address']);

        $shippingAddressData->setCollectShippingRates(true)->collectShippingRates();
        $shippingAddressData->setShippingMethod('cocote_cocote')->setPaymentMethod('cocote');

        $quote->setCocote(1);
        $quote->setCocoteShippingPrice($shippingPrice);
        $quote->getPayment()->importData(array('method' => 'cocote'));
        try {
            $quote->collectTotals()->save();
            $quote->reserveOrderId();
            $quote->setData('reserved_order_id',$prefix.$quote->getData('reserved_order_id'));

            // create order from quote
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $increment_id = $service->getOrder()->getRealOrderId();

            echo 'Order Id: ' . $increment_id;

        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function prepareOrderData($orderData) {

        $countriesList = Mage::app()->getLocale()->getCountryTranslationList();
        $data = [];

        $customerData = [
            'customer_is_guest' => 1,
            'customer_firstname' => $orderData['billing_address']['billing_firstname'],
            'customer_lastname' => $orderData['billing_address']['billing_lastname'],
            'customer_email' => $orderData['billing_address']['billing_email']
        ];
        $data['customer_data'] = $customerData;

        $data['shipping_cost']=$orderData['shipping_costs_vat'];

        $data['billing_address'] = [
            'firstname' => $orderData['billing_address']['billing_firstname'],
            'lastname' => $orderData['billing_address']['billing_lastname'],
            'company' => $orderData['billing_address']['billing_company_name'],
            'street' => [$orderData['billing_address']['billing_address1'],$orderData['billing_address']['billing_address2']],
            'city' => $orderData['billing_address']['billing_city'],
            'country_id' => $orderData['billing_address']['billing_country_code'],
//            'region' => 'Alaska',
//            'region_id' => '2',
            'postcode' => $orderData['billing_address']['billing_postcode'],
            'telephone' => $orderData['billing_address']['billing_phone']
        ];

        $data['shipping_address'] = [
            'firstname' => $orderData['delivery_address']['delivery_firstname'],
            'lastname' => $orderData['delivery_address']['delivery_lastname'],
            'company' => $orderData['delivery_address']['delivery_company_name'],
            'street' => [$orderData['delivery_address']['delivery_address1'],$orderData['delivery_address']['delivery_address2']],
            'city' => $orderData['delivery_address']['delivery_city'],
            'country_id' => $orderData['delivery_address']['delivery_country_code'],
//            'region' => 'Alaska',
//            'region_id' => '2',
            'postcode' => $orderData['delivery_address']['delivery_postcode'],
            'telephone' => $orderData['delivery_address']['delivery_phone'],
            'cocote' => 'true',
            'shipping_cost' => $orderData['shipping_costs_vat'],
        ];

        $productIds = [];

        foreach($orderData['products'] as $product) {
            if(isset($product['variation_id']) && $product['variation_id']) {
                $confData=$this->getConfigurableProductsData($product);
                if($confData) {
                    $productIds[]=$confData;
                }
            }
            else {
                $simpleProduct = Mage::getModel('catalog/product')->load($product['id']);
                if(!$simpleProduct->getId()) {
                    Mage::log('wrong product - '.$product['id'], null, 'cocote.log');
                    continue;
                }
                $params = array(
                    'product' => $simpleProduct->getId(),
                    'qty' => $product['quantity'],
                    'custom_price' => $product['unit_price_vat'],
                );
                $request = new Varien_Object();
                $request->setData($params);
                $productIds[] = ['prod' => $simpleProduct, 'params' => $request];
            }
        }

        $data['products'] = $productIds;
        return $data;
   }

    public function getConfigurableProductsData($product) {

        $configurableProduct = Mage::getModel('catalog/product')->load($product['id']);

        if(!$configurableProduct->getId()) {
            Mage::log('wrong product - '.$product['id'], null, 'cocote.log');
            return null;
        }

        $variationProduct = Mage::getModel('catalog/product')->load($product['variation_id']);
        $productAttributeOptions = $configurableProduct->getTypeInstance(true)->getConfigurableAttributesAsArray($configurableProduct);
        $options = array();

        foreach ($productAttributeOptions as $productAttribute) {
            $allValues = array_column($productAttribute['values'], 'value_index');
            $currentProductValue = $variationProduct->getData($productAttribute['attribute_code']);
            if (in_array($currentProductValue, $allValues)) {
                $options[$productAttribute['attribute_id']] = $currentProductValue;
            }
        }
        $params = array(
            'custom_price' => $product['unit_price_vat'],
            'product' => $configurableProduct->getId(),
            'qty' => $product['quantity'],
            'super_attribute' => $options
        );
        $request = new Varien_Object();
        $request->setData($params);

        $retData = ['prod' => $configurableProduct, 'params' => $request];
        return $retData;
    }
}