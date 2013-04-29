<?php

/**
 * Import/Export helper
 *
 * @category   Bonaparte
 * @package    Bonaparte_ImportExport
 * @author     Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_ImportExport_Adminhtml_CustomController extends Mage_Adminhtml_Controller_Action
{
    public function attributesAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function importAttributesAction()
    {
        Mage::getModel('Bonaparte_ImportExport/Custom_Import_Attributes')->start();
    }

    public function categoriesAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function importCategoriesAction() {
        Mage::getModel('Bonaparte_ImportExport/Custom_Import_Categories')->start();
    }


    public function productsAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function importProductsAction()
    {
        Mage::getModel('Bonaparte_ImportExport/Custom_Import_Products')->start();
    }

    public function importPricesAction()
    {
        Mage::getModel('Bonaparte_ImportExport/Custom_Import_Prices')->start();
    }

    public function resourcesAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function importResourcesAction()
    {
        Mage::getModel('Bonaparte_ImportExport/Custom_Import_Resources')->start();
    }
}