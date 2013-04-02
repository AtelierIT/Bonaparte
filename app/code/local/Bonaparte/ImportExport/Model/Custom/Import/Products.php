<?php

/**
 * Stores the business logic for the custom product import
 */
class Bonaparte_ImportExport_Model_Custom_Import_Products extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_configurationFilePath = array();
        $configFilesPath = '/var/www/bonaparte/magento/dump_files/xml/product';
        $files = scandir($configFilesPath);

        foreach($files as $fileName) {
            if(strlen($fileName) < 3) {
                continue;
            }
            $this->_configurationFilePath[] = $configFilesPath . '/' . $fileName;
        }
        unset($fileName);

        if(!is_array($this->_configurationFilePath)) {
            return parent::_initialize();
        }

        foreach($this->_configurationFilePath as $filePath) {
            $this->_data[] = new Varien_Simplexml_Config($filePath);
        }
    }

    /**
     * Recursive method to add unknown levels of categories
     *
     * @param mixed (integer|Mage_Catalog_Model_Category) $parentId
     * @param array $children
     */
    private function _addProduct($data) {
        return;
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        foreach($this->_data as $productConfig) {
            $product = $productConfig;
        }
        $this->_addProduct($product);

        echo 'DONE';
    }

}