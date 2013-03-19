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

    public function categoriesAction()
    {
        $this->loadLayout()->renderLayout();
    }

    public function importCategoriesAction() {
        var_dump(Mage::getModel('Bonaparte_ImportExport/custom_import_categories'));exit;
    }

    public function productsAction()
    {
        $this->loadLayout()->renderLayout();
    }
}