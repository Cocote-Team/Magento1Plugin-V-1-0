<?php

class Cocote_Feed_Model_Observer
{
    public $mapping = array();
    public $categoriesList=array();

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
        $collection->addAttributeToSelect('special_price');
        $collection->addAttributeToSelect('special_price_to');
        $collection->addAttributeToSelect('special_price_from');
        $collection->addAttributeToSelect('image');
        $collection->addAttributeToSelect('meta_keyword');
        $collection->addAttributeToSelect('short_description');
        $collection->addAttributeToSelect('weight');

        $collection->addAttributeToFilter('visibility', array('in'=>array(2,3,4))); //2/3/4 = catalog/search/both                
        $collection->addAttributeToFilter('status', 1);

//        $collection->joinField('category_id', 'catalog/category_product', 'category_id', 'product_id = entity_id', null, 'right');
//        $collection->getSelect()->distinct();

        foreach ($this->mapping as $attribute) {
            $collection->addAttributeToSelect($attribute);
        }

        if (Mage::getStoreConfig('cocote/generate/in_stock_only')) {
            $collection->getSelect()->join('cataloginventory_stock_item', 'cataloginventory_stock_item.product_id = e.entity_id', array('is_in_stock','manage_stock','use_config_manage_stock'));
            if (Mage::getStoreConfig('cataloginventory/item_options/manage_stock')) {
                $collection->getSelect()->where("(cataloginventory_stock_item.use_config_manage_stock=0 AND cataloginventory_stock_item.manage_stock=0) OR cataloginventory_stock_item.is_in_stock = 1");
            }
            else { //config set to "no"
                $collection->getSelect()->where("(cataloginventory_stock_item.use_config_manage_stock=1 OR cataloginventory_stock_item.manage_stock=0) OR cataloginventory_stock_item.is_in_stock = 1");
            }
        }

        $collection->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );

        $collection->joinField(
            'min_qty',
            'cataloginventory/stock_item',
            'min_qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

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
        $version=(string)Mage::getConfig()->getNode('modules/Cocote_Feed/version');
        $generated->setAttribute('plugin_version',$version);
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
            $currentprod->appendChild($domtree->createElement('keywords', htmlspecialchars($product->getData('meta_keyword'))));

            $descTag=$domtree->createElement('short_description');
            $descTag->appendChild($domtree->createCDATASection($product->getData('short_description')));
            $currentprod->appendChild($descTag);

            if ($product->getTypeId() == 'configurable') {
                $configurableOptions = $this->getConfigurableOptions($product,$domtree);
                $currentprod->appendChild($configurableOptions);
                //$price = Mage::helper('core')->formatPrice($product->getFinalPrice(), false);
                $price = $product->getFinalPrice();
            }
            elseif ($product->getTypeId() == 'bundle') {
                $price = Mage::helper('core')->formatPrice($this->getBundlePrice($product), false);
            }
            else {//simple
                $price=$product->getFinalPrice();
                $discountPrice=Mage::getModel('catalogrule/rule')->calcProductPriceRule($product,$product->getPrice());
                if($discountPrice && $discountPrice<$price) {
                    $price=$discountPrice;
                }
                $price = Mage::helper('core')->formatPrice($price, false);
                $currentprod->appendChild($domtree->createElement('stock', (int)$product->getQty()));
                $currentprod->appendChild($domtree->createElement('threshold_stock', (int)$product->getData('min_qty')));
            }
            if($product->getData('weight')) {
                $currentprod->appendChild($domtree->createElement('weight', (int)$product->getWeight()));
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

            if($catName=$this->getBestCategory($product->getCategoryIds())) {
                $currentprod->appendChild($domtree->createElement('category', htmlspecialchars($catName)));
            }

            if ($imageLink) {
                $currentprod->appendChild($domtree->createElement('image_link', $imageLink));
            }

            if ($imageSecondaryLink) {
                $currentprod->appendChild($domtree->createElement('image_link2', $imageSecondaryLink));
            }
        }

        $domtree->save(Mage::helper('cocote_feed')->getFilePath());

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

            $mappedStatuses=array('complete'=>'shipped',
                                  'processing'=>'paid',
                                  'closed' =>'refunded'

                );

            $order = $observer->getEvent()->getOrder();
            $token='';

            if(isset($_COOKIE["Cocote-token"])) {
                $token=htmlspecialchars($_COOKIE["Cocote-token"]);
                Mage::helper('cocote_feed')->saveToken($token,$order->getId());
            }

            $stateComplete = $order::STATE_COMPLETE;
            $stateProcessing=$order::STATE_PROCESSING;

            if (($order->getState() != $order->getOrigData('state'))
            && (($order->getOrigData('state') == $stateComplete) || $order->getState() == $stateComplete || $order->getState() == $stateProcessing)
            ) {

                $orderState=$order->getState();
                //A value in “paid”, “shipped”,“partially_refunded”, refunded
                if(isset($mappedStatuses[$orderState])) {
                    $orderState=$mappedStatuses[$orderState];
                }

                $items=array();
                foreach ($order->getAllVisibleItems() as $item) {
                    $items[]=['id'=>$item->getSku(),'qty'=>1];
                }

                if(!$token) {
                    $token=Mage::helper('cocote_feed')->getToken($order->getId());
                }

                $refundedAmount=0;
                if($order->getData('base_total_refunded')) {
                    $refundedAmount=$order->getData('base_total_refunded');
                }
                    $data = array(
                    'orderId' => $order->getIncrementId(),
                    'orderDate' => $order->getCreatedAt(),
                    'orderState' => $orderState,
                    'refundedAmount' => $refundedAmount,
                    'orderAmount' => $order->getGrandTotal(),
                    'currencyCode' => 'EUR',
                    'customerToken' =>$token,
                    'trackingUrl'=>'',
                    'products'=>$items, //id quantity array
                );

                $dataJson = json_encode($data);

                if($order->getState() == $stateProcessing) {
                    foreach($order->getAllVisibleItems() as $item) {
                        $this->setProductToUpdate($item->getProductId());
                    }
                }
                Mage::log($data, null, 'cocote.log');

                if (!function_exists('curl_version')) {
                    throw new Exception('no curl');
                }

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'X-Shop-Id: '.Mage::getStoreConfig('cocote/catalog/shop_id'),
                    'X-Secret-Key: '.Mage::getStoreConfig('cocote/catalog/shop_key'),
                    'X-Site-Version: Magento '.Mage::getVersion(),
                    'X-Plugin-Version:'.(string)Mage::getConfig()->getNode('modules/Cocote_Feed/version'),
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($dataJson),
                ));

                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJson);
                curl_setopt($curl, CURLOPT_TIMEOUT_MS, 1000);
                curl_setopt($curl, CURLOPT_URL, "https://fr.cocote.com/api/shops/v2/notify-order");
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

    public function getBestCategory($ids) {
        $ret='';
        if(!sizeof($ids)) {
            return $ret;
        }
        $level=0;
        foreach($ids as $id) {
            $category=$this->getCategory($id);
            if($category['level']>$level) {
                $bestCategory=$category;
                $level=$category['level'];
            }
        }
        $path=explode('/',$bestCategory['path']);
        for($i=2;$i<sizeof($path)-1;$i++) {
            $cat=$this->getCategory($path[$i]);
            $ret.=$this->formatCategoryName($cat['name']).'>';
        }
        $ret.=$this->formatCategoryName($bestCategory['name']);
        return $ret;
    }

    public function formatCategoryName($name) {
        $name=str_replace(' ','-',$name);
        $regexp = '/&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|caron);/i';
        $name=html_entity_decode(preg_replace($regexp, '$1', htmlentities($name)));
        return $name;
    }

    public function getCategory($id) {

        if(!sizeof($this->categoriesList)) {
            $defaultStoreView = $this->getDefaultStoreView();
            $categories=array();

            $allCategories = Mage::getModel('catalog/category')->getCollection()->addAttributeToSelect('name');
            $allCategories->setStoreId($defaultStoreView);

            foreach($allCategories as $cat) {
                $categories[$cat->getId()]['name'] = $cat->getName();
                $categories[$cat->getId()]['level'] = $cat->getLevel();
                $categories[$cat->getId()]['path'] = $cat->getPath();
            }
            $this->categoriesList=$categories;
        }
        return $this->categoriesList[$id];
    }

    public function getConfigurableOptions($product,$domtree) {
        $optionsList=$domtree->createElement('variations');
        $attributesMapping=[];
        $attributesPrices=[];

        $attributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

        foreach ($attributes as $attribute) {
            $attributesMapping[$attribute['attribute_code']]=$attribute['store_label'];
            foreach($attribute['values'] as $value) {
                $attributesPrices[$attribute['attribute_code']][$value['value_index']]=['addprice'=>$value['pricing_value'],'is_percent'=>$value['is_percent']];
            }
        }

        $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$product);
        foreach($childProducts as $simpleProd) {
            $idArray=[];
            $simplePrice=$product->getFinalPrice();
            foreach($attributesMapping as $code=>$id) {
                $idArray[]=$id.' - '.$simpleProd->getAttributeText($code);
                $pricePlus=$attributesPrices[$attribute['attribute_code']][$simpleProd->getData($code)];
                if($pricePlus['is_percent']) {
                    $simplePrice=$simplePrice*(100+$pricePlus['addprice'])/100;
                }
                else {
                    $simplePrice+=$pricePlus['addprice'];
                }
            }
            $variation=$domtree->createElement('variation');

            $variation->appendChild($domtree->createElement('variation_id',$simpleProd->getId()));
            $variation->appendChild($domtree->createElement('variation_name',$simpleProd->getName()));
            $variation->appendChild($domtree->createElement('variation_reference',$simpleProd->getSku()));
            if($simpleProd->getWeight()) {
                $variation->appendChild($domtree->createElement('variation_weight',$simpleProd->getWeight()));
            }
            $variation->appendChild($domtree->createElement('variation_stock',(int)$simpleProd->getStockItem()->getQty()));
            $variation->appendChild($domtree->createElement('variation_threshold_stock',(int)$simpleProd->getStockItem()->getMinQty()));
            $variation->appendChild($domtree->createElement('variation_price',$simplePrice));
            $variation->appendChild($domtree->createElement('variation_options',implode(',',$idArray)));

            $descTag=$domtree->createElement('variation_description');
            $descTag->appendChild($domtree->createCDATASection($simpleProd->getDescription()));
            $variation->appendChild($descTag);

            $descTag=$domtree->createElement('variation_short_description');
            $descTag->appendChild($domtree->createCDATASection($simpleProd->getData('short_description')));
            $variation->appendChild($descTag);


            if($simpleProd->getImage()) {
                $image=Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product'.$simpleProd->getImage();
                $variation->appendChild($domtree->createElement('variation_image',$image));
            }

            $optionsList->appendChild($variation);
        }
        return $optionsList;
    }

    public function afterProductSave($observer)
    {
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        );
        try
        {
            $product = $observer->getEvent()->getProduct();
            if($product->getPrice()!=$product->getOrigData('price')) {
                $this->setProductToUpdate($product->getId());
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'cocote.log');
        }

        finally {
        restore_error_handler();
        }
    }

    public function afterStockSave($observer)
    {
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        );
        try
        {
            $item = $observer->getEvent()->getItem();
            if($item->getQty()!=$item->getOrigData('qty')) {
                $this->setProductToUpdate($item->getProductId());
            }
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'cocote.log');
        }

        finally {
            restore_error_handler();
        }
    }

    public function sendPriceStockToCocote()
    {
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        );
        try
        {

            $updates=Mage::getModel('cocote_feed/product')->getCollection();

            if(!$updates->getSize()) {
                return; //no products to send
            }

            if (!function_exists('curl_version')) {
                throw new Exception('no curl');
            }

            $productsToUpdate=[];

            foreach($updates as $prod) {
                $productsToUpdate[]=$prod->getData('product_id');
                $prod->delete();
            }

            $defaultStoreView = $this->getDefaultStoreView();

            $collection = Mage::getModel('catalog/product')->getCollection();
            $collection->setStoreId($defaultStoreView);
            $collection->addAttributeToSelect('price');
            $collection->addAttributeToSelect('special_price');
            $collection->addAttributeToSelect('special_price_to');
            $collection->addAttributeToSelect('special_price_from');

            $collection->addAttributeToFilter('entity_id',['in'=>$productsToUpdate]);

            $collection->joinField(
                'qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );

            $offers=[];

            foreach($collection as $product) {
                $offer=[];

                $offer['id']=$product->getId();
                $offer['price']=$product->getFinalPrice();
                $offer['stock']=$product->getQty();
                $offer['variations']=[];
                $offers[]=$offer;
            }

            $data = array(
                'shopId' => Mage::getStoreConfig('cocote/catalog/shop_id'),
                'privateKey' => Mage::getStoreConfig('cocote/catalog/shop_key'),
                'offers'=>$offers
            );

            //$dataJson=json_encode($data);
            Mage::log($data, null, 'cocote.log');

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($curl, CURLOPT_TIMEOUT_MS, 1000);
            curl_setopt($curl, CURLOPT_URL, "https://fr.cocote.com/api/products");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($curl);
            curl_close($curl);

        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'cocote.log');
        }

        finally {
            restore_error_handler();
        }

    }

    public function setProductToUpdate($productId) {
        $cocoteProduct=Mage::getModel('cocote_feed/product');
        $cocoteProduct->setData('product_id',$productId);
        $cocoteProduct->save();
    }
}


