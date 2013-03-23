<?php

abstract class Bonaparte_ImportExport_Model_Custom_Import_Abstract extends Mage_ImportExport_Model_Abstract {
    /**
     * Stores the XML configuration file used
     *
     * @var Varien_Simplexml_Config
     */
    private $_config = null;

    /**
     * Store data that will be used in the import
     *
     * @var array
     */
    protected $_data = array();

    /**
     * Stores the path to the XML configuration file
     *
     * @var mixed string|array
     */
    protected $_configurationFilePath = '';

    /**
     * Initialize import
     */
    protected function _initialize() {
        if(is_array($this->_configurationFilePath)) {
            throw new Mage_Exception('Multiple files assigned as configuration files require specific implementation of
                Bonaparte_ImportExport_Model_Custom_Import_Abstract::_initialize');
        }

        $this->_config = new Varien_Simplexml_Config($this->_configurationFilePath);
    }

    /**
     * Returns the configuration handle
     *
     * @return null|Varien_Simplexml_Config
     */
    public function getConfig() {
        return $this->_config;
    }

    /**
     * Starts the import process
     *
     * @return mixed
     */
    abstract function start();
}