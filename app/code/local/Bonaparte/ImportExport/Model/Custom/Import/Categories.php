<?php

/**
 * Stores the business logic for the custom category import
 *
 * Class Bonaparte_ImportExport_Model_Custom_Import_Categories
 */
class Bonaparte_ImportExport_Model_Custom_Import_Categories extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    private $language_index = 0;
    private $rootCategoryName = array(
        0  => "da",
        1  => "ch",
        2  => "en",
        3  => "de",
        4  => "nl",
        5  => "sv",
    );
    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_configurationFilePath = Mage::getBaseDir().'/dump_files/xml/structure.xml';
        $this->_initialize();

        $config = $this->getConfig();
        $categoryStructure = array();
        $this->_extractNode($config, $categoryStructure);

        $this->_data = $categoryStructure;
    }

    /**
     * Recursive method to extract unknown levels of categories
     *
     * @param mixed (Varien_Simplexml_Config|Varien_Simplexml_Element) $node
     * @param array $categoryStructure
     */
    private function _extractNode($node, &$categoryStructure) {
        if($node instanceof Varien_Simplexml_Config) {
            $folder = $node->getNode('Folder');
        } else {
            $folder = $node->Folder;
        }

        if(empty($folder)) {
            return;
        }

        foreach ($folder as $node) {
            $attributeId = $node->getAttribute('groupId');
            $name = (array)$node->locale;
            $categoryStructure[$attributeId] = array(
                'name' => $name['value'][$this->language_index],
                'children' => array()
            );
            $this->_extractNode($node, $categoryStructure[$attributeId]['children']);
        }
    }

    /**
     * Recursive method to add unknown levels of categories
     *
     * @param mixed (integer|Mage_Catalog_Model_Category) $parentId
     * @param array $children
     */
    private function _addCategory($parentId, $children) {
        if(!($parentId instanceof Mage_Catalog_Model_Category)) {
            $collection = Mage::getModel('catalog/category')->getCollection();
            $collection->addAttributeToFilter('old_id', $parentId);
            $collection->load();
            $parent = array_pop($collection->getItems());
        } else {
            $parent = $parentId;
        }

        foreach($children as $oldCategoryId => $data) {
            $category = Mage::getModel('catalog/category');
            $categoryCollection = $category->getCollection();
            $categoryCollection->addAttributeToFilter('old_id', $oldCategoryId);
            $categoryCollection->load();
            foreach($categoryCollection as $duplicateCategory) {
                $duplicateCategory->delete();
            }

            $category->setData(array(
                'name' => $data['name'],
                'is_active' => 1,
                'include_in_menu' => 1,
                'is_anchor' => 0,
                'url_key' => '',
                'description' => '',
                'old_id' => $oldCategoryId
            ))
                ->setAttributeSetId($category->getDefaultAttributeSetId())
                ->setStoreId(0)
                ->setPath(implode('/', $parent->getPathIds()))
                ->setParentId($parent->getId())
                ->save();

            unset($categoryCollection, $collection);

            $category->clearInstance();
            $parent->clearInstance();

            if(!empty($data['children'])) {
                $this->_addCategory($oldCategoryId, $data['children']);
            }
        }
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        // importing parent categories
      //  $parent = Mage::getModel('catalog/category')->load(
      //      Mage::app()->getDefaultStoreView()->getRootCategoryId()
      //  );
        foreach ($this->rootCategoryName as $currentParentLanguageIndex =>$currentParentName){

            $this->language_index = $currentParentLanguageIndex;

            // Create category object
            $category = Mage::getModel('catalog/category');
            $category->setStoreId(0); // No store is assigned to this category.

            $tempRootCategory['name'] = $currentParentName." root category";
            $tempRootCategory['path'] = "1"; // this is the catgeory path - 1 for root category.
            $tempRootCategory['description'] = "Category ".$currentParentName;
            $tempRootCategory['meta_title'] = "Meta Title";
            $tempRootCategory['meta_keywords'] = "Meta Keywords";
            $tempRootCategory['meta_description'] = "Meta Description";
            $tempRootCategory['display_mode'] = "PRODUCTS";
            $tempRootCategory['is_active'] = 1;
            $tempRootCategory['is_anchor'] = 1;

            $category->addData($tempRootCategory);
//            $category->save();
            try {
                $category->save();
                $currentParent = $category->getId();
            }
            catch (Exception $e){
                echo $e->getMessage();
            }
//            $currentParent = $category->getId();
            $parenting = Mage::getModel('catalog/category')->load(
                Mage::app()->getDefaultStoreView()->getRootCategoryId()
            );

            $this->_addCategory($category, $this->_data);
            echo 'DONE '.$currentParentName;
        }

    }

}