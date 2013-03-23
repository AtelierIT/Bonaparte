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
        try {
            $importCategoriesModel = Mage::getModel('Bonaparte_ImportExport/Custom_Import_Categories');
            $importCategoriesModel->start();
        } catch(Exception $e) {
            $break = true;
            // handle
        }
    }

    public function generateCategoryRelationsAction() {
        try {
            $importCategoriesModel = Mage::getModel('Bonaparte_ImportExport/Custom_Import_CategoryRelations');
            $importCategoriesModel->start();
        } catch(Exception $e) {
            $break = true;
            // handle
        }
    }

    public function productsAction()
    {
        $this->loadLayout()->renderLayout();
    }
}