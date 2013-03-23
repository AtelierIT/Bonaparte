<?php

class Bonaparte_ImportExport_Model_Custom_Import_CategoryRelations extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
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

        $this->_initialize();
    }

    protected function _initialize() {
        if(!is_array($this->_configurationFilePath)) {
            return parent::_initialize();
        }

        foreach($this->_configurationFilePath as $filePath) {
            $config = new Varien_Simplexml_Config($filePath);
            $parentCategory = (string)$config
                ->getNode()
                ->{Bonaparte_ImportExport_Model_Custom_Import_Categories::CATEGORY_CODE_PARENT};
            $childCategory = (string)$config
                ->getNode()
                ->{Bonaparte_ImportExport_Model_Custom_Import_Categories::CATEGORY_CODE_CHILD};

            if(!isset($this->_data[$parentCategory])) {
                $this->_data[$parentCategory][] = $childCategory;
            } elseif(!in_array($childCategory, $this->_data[$parentCategory])) {
                $this->_data[$parentCategory][] = $childCategory;
            }

            unset($config);
        }

        ksort($this->_data);
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        $jsonEncodedData = json_encode($this->_data);

        file_put_contents('/var/www/bonaparte/magento/var/tmp/category_relations.json', $jsonEncodedData);

        echo 'DONE ESTABLISHING RELATIONS';
    }

}