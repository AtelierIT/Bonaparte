<?php

/**
 * Stores the business logic for the custom category import
 *
 * Class Bonaparte_ImportExport_Model_Custom_Import_Categories
 */
class Bonaparte_ImportExport_Model_Custom_Import_Attributes extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_configurationFilePath =  Mage::getBaseDir().'/dump_files/xml/Cvl.xml';
        $this->_initialize();

        $attributes = array();
        $attributesConfig = $this->getConfig()->getNode('group');
        foreach($attributesConfig as $attribute) {
            $attributeCode = $attribute->getAttribute('id');
            $attributes[$attributeCode] = array();
            foreach($attribute->cvl as $attributeValue) {
                if(!$attributeValue->values->value) {
                    $attributes[$attributeCode][$attributeValue->getAttribute('id')] = (string)$attributeValue->values;
                } else {
                    $attributes[$attributeCode][$attributeValue->getAttribute('id')] = (string)$attributeValue->values->value[2];
                }
            }
            $break = true;
        }

        $this->_data = $attributes;
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        foreach($this->_data as $attributeCode => $attributeConfigData) {
            $code = 'bnp_' . strtolower($attributeCode);

            $attributeCollection = Mage::getModel('eav/entity_attribute')->getCollection();
            $attributeCollection->setModel('catalog/resource_eav_attribute')
                ->addFieldToFilter('attribute_code', $code)
                ->load();

            foreach($attributeCollection as $duplicateAttribute) {
                $duplicateAttribute->delete();
            }
            unset($attributeCollection);

            $optionValues = array();
            $optionIds = array();
            $counter = 0;
            foreach($attributeConfigData as $optionId => $optionValue) {
                $optionValues['option' . $counter][0] = $optionValue;
                $optionIds[] = $optionId;
                $counter++;
            }

            $attributeData = array(
                'attribute_code' => $code,
                'is_global' => '1',
                'frontend_input' => 'select',
                'default_value_text' => '',
                'default_value_yesno' => '0',
                'default_value_date' => '',
                'default_value_textarea' => '',
                'is_unique' => '0',
                'option' => array(
                    'value' => $optionValues
                ),
                'is_required' => '0',
                'apply_to' => array('simple', 'configurable'),
                'is_configurable' => '0',
                'is_searchable' => '0',
                'is_visible_in_advanced_search' => '0',
                'is_comparable' => '0',
                'is_used_for_price_rules' => '0',
                'is_wysiwyg_enabled' => '0',
                'is_html_allowed_on_front' => '1',
                'is_visible_on_front' => '0',
                'used_in_product_listing' => '0',
                'used_for_sort_by' => '0',
                'external_id' => $attributeCode,
                'frontend_label' => array('Bnp attribute ' . $attributeCode)
            );

            $model = Mage::getModel('catalog/resource_eav_attribute');
            if (!isset($attributeData['is_configurable'])) {
                $attributeData['is_configurable'] = 0;
            }
            if (!isset($attributeData['is_filterable'])) {
                $attributeData['is_filterable'] = 0;
            }
            if (!isset($attributeData['is_filterable_in_search'])) {
                $attributeData['is_filterable_in_search'] = 0;
            }
            if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
                $attributeData['backend_type'] = $model->getBackendTypeByInput($attributeData['frontend_input']);
            }

            $model->addData($attributeData);
            $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
            $model->setIsUserDefined(1);

            try {
                $model->save();
            } catch (Exception $e) {
                echo '<p>Sorry, error occured while trying to save the attribute. Error: '.$e->getMessage().'</p>';
            }

            $databaseOptions = $model->getSource()->getAllOptions(false);
            $idOrderedDatabaseOptions = array();
            foreach($databaseOptions as $option) {
                $idOrderedDatabaseOptions[$option['value']] = $option['label'];
            }
            ksort($idOrderedDatabaseOptions);
            $internalOptionIds = array_keys($idOrderedDatabaseOptions);

            $collection = Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option')->getCollection();
            $collection->load();
            foreach($internalOptionIds as $key => $internalOptionId) {
                $model = Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option');
                $model->setType(Bonaparte_ImportExport_Model_External_Relation_Attribute_Option::TYPE_ATTRIBUTE_OPTION);
                $model->setExternalId($optionIds[$key]);
                $model->setInternalId($internalOptionId);
                $collection->addItem($model);
            }
            $collection->save();

            $model = Mage::getModel('eav/entity_setup', 'core_setup');
            $attributeId = $model->getAttribute('catalog_product', $code);
            $attributeSetId = $model->getAttributeSetId('catalog_product','Default');
            $attributeGroupId = $model->getAttributeGroup('catalog_product', $attributeSetId, 'General');

            //add attribute to a set
            $model->addAttributeToSet('catalog_product', $attributeSetId, $attributeGroupId['attribute_group_id'], $attributeId['attribute_id']);
        }

        echo 'DONE';
    }

}