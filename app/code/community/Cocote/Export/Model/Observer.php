<?php

class Cocote_Export_Model_Observer
{
    public $mapping = array();

    public function __construct()
    {
        $mapName = Mage::getStoreConfig('cocote/catalog/map_name');
        $mapMpn = Mage::getStoreConfig('cocote/catalog/map_mpn');
        $mapGtin = Mage::getStoreConfig('cocote/catalog/map_gtin');
        $mapDescription = Mage::getStoreConfig('cocote/catalog/map_description');
        $mapManufacturer = Mage::getStoreConfig('cocote/catalog/map_manufacturer');

        if ($mapName) {
            $this->mapping['title'] = $mapName;
        }

        if ($mapMpn) {
            $this->mapping['mpn'] = $mapMpn;
        }

        if ($mapGtin) {
            $this->mapping['gtin'] = $mapGtin;
        }

        if ($mapDescription) {
            $this->mapping['description'] = $mapDescription;
        }

        if ($mapManufacturer) {
            $this->mapping['brand'] = $mapManufacturer;
        }
    }

    public function getProductCollection()
    {
        $defaultStoreView = $this->getDefaultStoreView();

        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->setStoreId($defaultStoreView);
        $collection->addUrlRewrite();
        $collection->addWebsiteFilter($defaultStoreView);
        $collection->addAttributeToSelect('price');
        $collection->addAttributeToSelect('image');
        $collection->addAttributeToSelect('meta_keyword');

        $collection->addAttributeToFilter('visibility', array('in'=>array(2,3,4))); //2/3/4 = catalog/search/both                
        $collection->addAttributeToFilter('status', 1);

        foreach ($this->mapping as $attribute) {
            $collection->addAttributeToSelect($attribute);
        }

        if (Mage::getStoreConfig('cocote/generate/in_stock_only')) {
            $collection->getSelect()->join('cataloginventory_stock_item', 'cataloginventory_stock_item.product_id = e.entity_id', array('is_in_stock'));
            $collection->getSelect()->where("cataloginventory_stock_item.is_in_stock = 1");
        }

        return $collection;
    }

    public function generateFeed()
    {
        $validate=array();
        $defaultStoreView = $this->getDefaultStoreView();

        Mage::app()->setCurrentStore($defaultStoreView); // adjust according to config setting
        $productCollection = $this->getProductCollection();

        $domtree = new DOMDocument('1.0', 'UTF-8');

        $xmlRoot = $domtree->createElement("shop");
        $xmlRoot = $domtree->appendChild($xmlRoot);

        $generated = $domtree->createElement('generated',Mage::getModel('core/date')->gmtDate('Y-m-d H:i:s'));
        $generated->setAttribute('cms', 'magento');
        $xmlRoot->appendChild($generated);

        $offers = $domtree->createElement("offers");
        $offers = $xmlRoot->appendChild($offers);

        foreach ($productCollection as $product) {
            $imageUsed = 0;
            $attributeCode = 'media_gallery';
            $attribute = $product->getResource()->getAttribute($attributeCode);
            $backend = $attribute->getBackend();
            $backend->afterLoad($product);

            $imageLink='';
            $imageSecondaryLink='';

            if($product->getImage() && $product->getImage()!='no_selection') {
                $imageLink = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product'.$product->getImage();
            }

            foreach ($product->getMediaGalleryImages() as $image) { //if they have default one set
                if ($image->getFile() && $image->getFile() == $product->getImage()) {
                    $imageLink = $image->getUrl();
                    $imageUsed = $image->getPosition();
                }
            }

            foreach ($product->getMediaGalleryImages() as $image) { //next pass to get secondary one
                if ($imageUsed == $image->getPosition()) {
                    continue;
                }

                if (!($imageUsed)) {
                    $imageLink = $image->getUrl(); //if no default one set first one
                    $imageUsed = $image->getPosition();
                    continue;
                }

                $imageSecondaryLink = $image->getUrl();
                break;
            }

            $currentprod = $domtree->createElement("item");
            $currentprod = $offers->appendChild($currentprod);

            //$url=Mage::helper('catalog/product')->getProductUrl($product->getId());
            $url = $product->getProductUrl();

            $currentprod->appendChild($domtree->createElement('identifier', $product->getId()));
            $currentprod->appendChild($domtree->createElement('link', $url));
            $currentprod->appendChild($domtree->createElement('keywords', $product->getData('meta_keyword')));

            if ($product->getTypeId() == 'configurable') {
                $price = Mage::helper('core')->formatPrice($this->getConfigurableLowestPrice($product->getId()), false);
            }
            elseif ($product->getTypeId() == 'bundle') {
                $price = Mage::helper('core')->formatPrice($this->getBundlePrice($product), false);
            }
            else {
                $price = Mage::helper('core')->formatPrice($product->getFinalPrice(), false);
            }

            $currentprod->appendChild($domtree->createElement('price', $price));

            foreach ($this->mapping as $nodeName => $attrName) {
                if ($nodeName=='description') {
                    $descTag=$domtree->createElement('description');
                    $descTag->appendChild($domtree->createCDATASection($product->getData($attrName)));
                    $currentprod->appendChild($descTag);
                }
                else {
                    $attributeModel = Mage::getModel('eav/config')->getAttribute('catalog_product', $attrName);
                    //$attributeModel=Mage::getModel('eav/entity_attribute')->load( $attrName);

                    if ($attributeModel->getFrontendInput() == 'select' || $attributeModel->getFrontendInput() == 'multiselect') {
                        $currentprod->appendChild($domtree->createElement($nodeName, $product->getAttributeText($attrName)));
                    } else {
                        $currentprod->appendChild($domtree->createElement($nodeName, htmlspecialchars($product->getData($attrName))));
                    }
                }
            }

            if ($imageLink) {
                $currentprod->appendChild($domtree->createElement('image_link', $imageLink));
            }

            if ($imageSecondaryLink) {
                $currentprod->appendChild($domtree->createElement('image_link2', $imageSecondaryLink));
            }
        }

        $domtree->save(Mage::helper('cocote_export')->getFilePath());

        Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
        return $validate;
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

    public function sendOrderToCocote($observer)
    {
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        );
        try {
            $order = $observer->getEvent()->getOrder();

            $stateProcessing = $order::STATE_PROCESSING;
            if ($order->getState() == $stateProcessing && $order->getOrigData('state') != $stateProcessing) {
                $data=[
                    'state'=>$order->getState(),
                    'orig_state'=>$order->getOrigData('state'),
                    'status'=>$order->getStatus()
                ];

                Mage::log($data, null, 'cocote.log');


                $data = array(
                    'shopId' => Mage::getStoreConfig('cocote/catalog/shop_id'),
                    'privateKey' => Mage::getStoreConfig('cocote/catalog/shop_key'),
                    'email' => $order->getCustomerEmail(),
                    'orderId' => $order->getIncrementId(),
                    'orderPrice' => $order->getGrandTotal(),
                    'priceCurrency' => 'EUR',
                );
                Mage::log($data, null, 'cocote.log');

                if (!function_exists('curl_version')) {
                    throw new Exception('no curl');
                }

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                curl_setopt($curl, CURLOPT_URL, "https://fr.cocote.com/api/cashback/request");
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $result = curl_exec($curl);
                curl_close($curl);
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'cocote.log');
        }

        finally {
            restore_error_handler();
        }
    }

    public function getConfigurableLowestPrice($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        $block = Mage::app()->getLayout()->createBlock('catalog/product_view_type_configurable');
        $block->setProduct($product);
        $config = json_decode($block->getJsonConfig(), true);
        $basePrice = $config['basePrice'];
        $min = null;
        foreach ($config['attributes'] as $aValues) {
            foreach ($aValues['options'] as $value) {
                if (is_null($min) || $min > $value['price']) {
                    $min = $value['price'];
                }
            }
        }

        $min += $basePrice;
        return $min;
    }

    public function getBundlePrice($product) {
        $optionCol= $product->getTypeInstance(true)
            ->getOptionsCollection($product);
        $selectionCol= $product->getTypeInstance(true)
            ->getSelectionsCollection(
                $product->getTypeInstance(true)->getOptionsIds($product),
                $product
            );
        $optionCol->appendSelections($selectionCol);
        $backupPrice=0;
        $price = $product->getPrice();

        foreach ($optionCol as $option) {
            if($option->required) {
                $selections = $option->getSelections();
                $minPrice = min(array_map(function ($s) {
                    return $s->price;
                }, $selections));
                if($product->getSpecialPrice() > 0) {
                    $minPrice *= $product->getSpecialPrice()/100;
                }
                $price += round($minPrice,2);
            }
            else {
                $selections = $option->getSelections();
                $minPrice = min(array_map(function ($s) {
                            return $s->price;
                        }, $selections));
                if(!$backupPrice || $backupPrice>$minPrice) {
                    $backupPrice=$minPrice;
                }
            }
        }
        if($price==0) {
            $price=$backupPrice;
        }
        return $price;
    }
}

