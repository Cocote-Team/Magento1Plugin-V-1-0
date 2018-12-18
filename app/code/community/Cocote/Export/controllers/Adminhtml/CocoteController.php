<?php

class Cocote_Export_Adminhtml_CocoteController extends Mage_Adminhtml_Controller_Action
{


    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/cocote_products');
    }

    public function generateAction()
    {
        $session = Mage::getSingleton("adminhtml/session");

        try {
            Mage::getModel('cocote_export/observer')->generateFeed();
            $session->addSuccess(Mage::helper('cocote_export')->__("Generating done"));
        } catch (Exception $e) {
            Mage::getSingleton("core/session")->addError($e->getMessage());
        }

        $this->_redirect('*/system_config/edit/section/cocote/');
    }

}


