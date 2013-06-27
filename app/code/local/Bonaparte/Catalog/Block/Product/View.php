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
     * Retrieves the products with the same style
     *
     * @param boolean $exceptCurrentProduct
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    public function getStyleProducts($exceptCurrentProduct = true) {
        $collection = Mage::getModel('catalog/product')->getCollection();
        $collection->addAttributeToFilter('name', array('eq' => $this->getProduct()->getName()));
        $collection->addAttributeToFilter('type_id', array('eq' => 'configurable'));

        if($exceptCurrentProduct) {
            $collection->addAttributeToFilter('entity_id', array('neq' => $this->getProduct()->getId()));
        }

        return $collection;
    }

}