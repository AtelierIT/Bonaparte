<?php

/**
 * Catalog product view block
 *
 * @category    Bonaparte
 * @package     Bonaparte_Catalog
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_Catalog_Block_Product_View extends Mage_Catalog_Block_Product_View {

    /**
     *
     */
    public function getStyleProducts() {
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToFilter('name', array('eq' => $this->getProduct()->getName()));
        $collection->addAttributeToFilter('type_id', array('eq' => 'configurable'));

        return $collection->load();
    }

}