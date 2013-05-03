<?php

/**
 * Stores the business logic for the custom category import
 *
 * @category    Bonaparte
 * @package     Bonaparte_ImportExport
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_ImportExport_Model_Custom_Import_Categories extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    /**
     * Path at which the category configuration is found
     *
     * @var string
     */
    const CONFIGURATION_FILE_PATH = '/dump_files/xml/structure.xml';

    /**
     * Language index that will be used to determine what label value to use
     *
     * @var int
     */
    private $_languageIndex = 0;

    /**
     * Languages used to create the root categories for each one
     *
     * @var array
     */
    private $_languages = array('uk', 'dk', 'se', 'nl', 'de', 'ch');

    /**
     * Construct import model
     */
    protected function _construct()
    {
        $this->_configurationFilePath = Mage::getBaseDir() . self::CONFIGURATION_FILE_PATH;
        $this->_initialize();

        $this->_data = array();
        $this->_extractNode($this->getConfig(), $this->_data);
    }

    /**
     * Recursive method to extract unknown levels of categories
     *
     * @param mixed (Varien_Simplexml_Config|Varien_Simplexml_Element) $node
     * @param array $categoryStructure
     */
    private function _extractNode($node, &$categoryStructure)
    {
        if ($node instanceof Varien_Simplexml_Config) {
            $folder = $node->getNode('Folder');
        } else {
            $folder = $node->Folder;
        }

        if (empty($folder)) {
            return;
        }

        foreach ($folder as $node) {



            $attributeId = $node->getAttribute('groupId');
            $name = (array)$node->locale;
            $categoryStructure[$attributeId] = array(
                'name' => $name['value'],
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
    private function _addCategory($parentId, $children)
    {
        if (!($parentId instanceof Mage_Catalog_Model_Category)) {
            $collection = Mage::getModel('catalog/category')->getCollection();
            $collection->addAttributeToFilter('old_id', $parentId);
            $collection->load();
            $parent = array_pop($collection->getItems());
        } else {
            $parent = $parentId;
        }

        foreach ($children as $oldCategoryId => $data) {
            $category = Mage::getModel('catalog/category');
            $categoryCollection = $category->getCollection()
                ->addAttributeToFilter('old_id', $oldCategoryId)
                ->load();

            $name = $data['name'];
            if(is_array($name)) {
                $name = $data['name'][$this->_languageIndex];
            }

            $parent->setPathIds(null);
            $category->setData(array(
                'name' => $name,
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

            if (!empty($data['children'])) {
                $this->_addCategory($oldCategoryId, $data['children']);
            }
        }
    }

    /**
     * Remove category duplicates
     *
     * @param array $children
     */
    private function _removeDuplicates($children)
    {
        foreach ($children as $externalCategoryId => $data) {
            $categoryCollection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToFilter('old_id', $externalCategoryId)
                ->load();
            
            foreach ($categoryCollection as $duplicateCategory) {
                $duplicateCategory->delete();
                $duplicateCategory->clearInstance();
            }
            unset($categoryCollection);

            if (!empty($data['children'])) {
                $this->_removeDuplicates($data['children']);
            }
        }
    }

    /**
     * Specific category functionality
     */
    public function start($options = array())
    {
        // before importing remove last imported categories
        $this->_removeDuplicates($this->_data);

        foreach ($this->_languages as $languageIndex => $language) {
            $name = $language . ' root category';

            // also remove all language root categories
            $categoryCollection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToFilter('name', $name)
                ->load();

            foreach ($categoryCollection as $duplicateCategory) {
                $duplicateCategory->delete();
                $duplicateCategory->clearInstance();
            }

            unset($categoryCollection);

            $this->_languageIndex = $languageIndex;

            // Create category object
            $category = Mage::getModel('catalog/category')
                ->setStoreId(0)
                ->addData(array(
                    'name' => $name,
                    'path' => '1',
                    'description' => 'Category ' . $language,
                    'meta_title' => 'Meta Title',
                    'meta_keywords' => 'Meta Keywords',
                    'meta_description' => 'Meta Description',
                    'display_mode' => 'PRODUCTS',
                    'is_active' => 1,
                    'is_anchor' => 1
                ));

            try {
                $category->save();
            } catch (Exception $e) {
                echo $e->getMessage();
                exit;
            }

            $this->_addCategory($category, $this->_data);
        }

        echo 'DONE ';
    }

}