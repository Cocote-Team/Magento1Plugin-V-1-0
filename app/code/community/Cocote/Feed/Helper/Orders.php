<?php

class Cocote_Feed_Helper_Orders extends Mage_Core_Helper_Abstract
{


    public function getOrdersFromCocote()
    {
        echo 's';
        try {
                if (!function_exists('curl_version')) {
                    throw new Exception('no curl');
                }

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, "https://fr.cocote.com/api/shops/v2/get-last-orders");
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'X-Shop-Id: ' . Mage::getStoreConfig('cocote/catalog/shop_id'),
                    'X-Secret-Key: ' . Mage::getStoreConfig('cocote/catalog/shop_key'),
                    'X-Site-Version: Magento ' . Mage::getVersion(),
                    'X-Plugin-Version:' . (string)Mage::getConfig()->getNode('modules/Cocote_Feed/version'),
                ));

                $result = curl_exec($curl);
                print_r($result);
            echo '2';

            curl_close($curl);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'cocote.log');
    }
}
}