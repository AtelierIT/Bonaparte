<?php

class Bonaparte_ImportExport_Model_Custom_Import_Categories extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    const CATEGORY_CODE_PARENT = 'ProductMainGroup';
    const CATEGORY_CODE_CHILD = 'ProductGroup';

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_configurationFilePath = '/var/www/bonaparte/magento/dump_files/xml/Cvl.xml';
        $this->_initialize();

        $config = $this->getConfig();
        foreach ($config->getNode('group') as $node) {
            $attributeId = $node->getAttribute('id');
            if (in_array($attributeId, array(
                self::CATEGORY_CODE_PARENT,
                self::CATEGORY_CODE_CHILD
            ))
            ) {
                foreach ($node->children() as $childNode) {
                    foreach ($childNode->children() as $value) {
                        $value = (array)$value;
                        $this->_data[$attributeId][$childNode->getAttribute('id')] = $value['value'];
                    }
                }
                ksort($this->_data[$attributeId]);
            }
        }
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        // importing parent categories
        $parentId = Mage::app()->getDefaultStoreView()->getRootCategoryId();
        $parent = Mage::getModel('catalog/category')->load($parentId);
        foreach ($this->_data[self::CATEGORY_CODE_PARENT] as $oldCategoryId => $parentCategory) {
            $category = Mage::getModel('catalog/category');

            $categoryCollection = $category->getCollection();
            $categoryCollection->addAttributeToFilter('old_id', $oldCategoryId);
            $categoryCollection->load();
            foreach($categoryCollection as $duplicateCategory) {
                $duplicateCategory->delete();
            }

            $category->setData(array(
                'name' => trim($parentCategory[2]),
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
                ->setParentId($parentId)
                ->save();

            unset($categoryCollection);
            $category->clearInstance();
        }

        // importing child categories
        $rawCategoryRelations = json_decode(
            file_get_contents('/var/www/bonaparte/magento/var/tmp/category_relations.json'),
            true
        );

        $finalCategoryRelations = $multipleCategoryAssignment = array();
        foreach($rawCategoryRelations as $parentCategory => $childCategories) {
            foreach($childCategories as $childCategory) {
                if(isset($finalCategoryRelations[$childCategory])) {
                    $multipleCategoryAssignment[$childCategory][] = $parentCategory;
                    continue;
                }
                $finalCategoryRelations[$childCategory] = $parentCategory;
            }
        }
        unset($parentCategory, $childCategories, $childCategory);

        $parentId = Mage::app()->getDefaultStoreView()->getRootCategoryId();
        foreach ($this->_data[self::CATEGORY_CODE_CHILD] as $oldCategoryId => $childCategory) {
            $category = Mage::getModel('catalog/category');

            $categoryCollection = $category->getCollection();
            $categoryCollection->addAttributeToFilter('old_id', $oldCategoryId);
            $categoryCollection->load();
            foreach($categoryCollection as $duplicateCategory) {
                $duplicateCategory->delete();
            }

            if(isset($finalCategoryRelations[$oldCategoryId])) {
                $parentId = $finalCategoryRelations[$oldCategoryId];
                $collection = Mage::getModel('catalog/category')->getCollection();
                $collection->addAttributeToFilter('old_id', $parentId);
                $collection->load();
                $parent = array_pop($collection->getItems());
            } else {
                $parent = Mage::getModel('catalog/category')->load($parentId);
            }

            $category->setData(array(
                'name' => trim($childCategory[2]),
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
                ->setParentId($parentId)
                ->save();

            unset($collection, $categoryCollection);
            $parent->clearInstance();
            $category->clearInstance();
        }

        echo 'DONE';
    }

}