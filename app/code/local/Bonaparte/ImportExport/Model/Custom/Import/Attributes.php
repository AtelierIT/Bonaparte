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
        $this->_configurationFilePath = '/var/www/bonaparte/magento/dump_files/xml/Cvl.xml';
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

            $attributeData = array(
                'attribute_code' => $code,
                'is_global' => '1',
                'frontend_input' => 'boolean',
                'default_value_text' => '',
                'default_value_yesno' => '0',
                'default_value_date' => '',
                'default_value_textarea' => '',
                'is_unique' => '0',
                'is_required' => '0',
                'apply_to' => array('simple'),
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
            /*$defaultValueField = $model->getDefaultValueByInput($attributeData['frontend_input']);
            if ($defaultValueField) {
                $attributeData['default_value'] = $this->getRequest()->getParam($defaultValueField);
            }*/
            $model->addData($attributeData);
            $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
            $model->setIsUserDefined(1);

            try {
                $model->save();
            } catch (Exception $e) {
                echo '<p>Sorry, error occured while trying to save the attribute. Error: '.$e->getMessage().'</p>';
            }

            $model = Mage::getModel('eav/entity_setup', 'core_setup');
            $attributeId = $model->getAttribute('catalog_product', $code);
            $attributeSetId = $model->getAttributeSetId('catalog_product','Cell Phones');
            $attributeGroupId = $model->getAttributeGroup('catalog_product', $attributeSetId, 'General');

            //add attribute to a set
            $model->addAttributeToSet('catalog_product', $attributeSetId, $attributeGroupId['attribute_group_id'], $attributeId['attribute_id']);
        }

        echo 'DONE';
    }

}