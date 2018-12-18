<?php

class Cocote_Export_Helper_Data extends Mage_Core_Helper_Abstract
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

}