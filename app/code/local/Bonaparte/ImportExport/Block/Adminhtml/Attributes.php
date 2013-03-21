<?php

class Bonaparte_ImportExport_Block_Adminhtml_Attributes extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct(){
        $this->_controller = 'adminhtml_attributes';
        $this->_blockGroup = 'attributes';
        $this->_headerText = Mage::helper('attributes')->__('CVL list of Attributes');
        $this->_addButtonLabel = Mage::helper('attributes')->__('Add Selected Attributes');
        parent::__construct();
    }
}