<?php

/**
 * Stores the business logic for the custom attribute import
 *
 * @category    Bonaparte
 * @package     Bonaparte_ImportExport
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_ImportExport_Model_Custom_Import_Attributes extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    /**
     * Path at which the category configuration is found
     *
     * @var string
     */
    const CONFIGURATION_FILE_PATH = '/dump_files/xml/Cvl.xml';

    /**
     * Path at which the size attribute is found
     *
     * @var string
     */
    const CONFIGURATION_FILE_PATH_SIZE = '/dump_files/xml/size.csv';

    /**
     * Prefix used to distinguish the Magento core attributes from the Bonaparte attributes
     */
    const ATTRIBUTE_PREFIX = 'bnp_';

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_logMessage('Reading configuration files');
        $this->_configurationFilePath = Mage::getBaseDir() . self::CONFIGURATION_FILE_PATH;
        $this->_initialize();

        $attributes = array();
        $attributesConfig = $this->getConfig()->getNode('group');
        foreach ($attributesConfig as $attribute) {
            $attributeCode = $attribute->getAttribute('id');
            $attributes[$attributeCode] = array();
            foreach ($attribute->cvl as $attributeValue) {
                if (!$attributeValue->values->value) {
                    $attributes[$attributeCode][$attributeValue->getAttribute('id')] = (string)$attributeValue->values;
                } else {
                    $nrValues = count($attributeValue->values->value);
                    for ($i = 0; $i < $nrValues; $i++) {
                        $attributes[$attributeCode][$attributeValue->getAttribute('id')][] = (string)$attributeValue->values->value[$i];
                    }
                }
            }
        }

        $this->_data = $attributes;

        $sizes = array();
        $handle = fopen(Mage::getBaseDir() . self::CONFIGURATION_FILE_PATH_SIZE, 'r');
        while ($row = fgetcsv($handle,null,';','"')) {
            $row[] = $sizes[6];
        }
        $sizes = array_unique($sizes);
        fclose($handle);

        $this->_data['Size'] = $sizes;

        $this->_logMessage('Finished reading configuration files');
    }

    /**
     * Remove previously imported attributes
     */
    private function _removeDuplicates()
    {
        $this->_logMessage('Started removing duplicate attributes');

        $currentAttributeNumber = 0;
        $attributesNumber = count($this->_data);
        foreach ($this->_data as $attributeCode => $attributeConfigurationData) {
            $currentAttributeNumber++;
            $code = self::ATTRIBUTE_PREFIX . strtolower($attributeCode);
            $attributeCollection = Mage::getModel('eav/entity_attribute')->getCollection()
                ->setModel('catalog/resource_eav_attribute')
                ->addFieldToFilter('attribute_code', $code)
                ->load();

            foreach ($attributeCollection as $duplicateAttribute) {
                $this->_logMessage(
                    'Deleting attribute '
                        . $currentAttributeNumber
                        . ' out of '
                        . $attributesNumber
                        . ' with the code '
                        . '"'
                        . $code
                        . '"'
                );

                $duplicateAttribute->delete();
                $duplicateAttribute->clearInstance();
            }
            unset($attributeCollection);
        }
        $this->_removeExternalIdsWithoutInternalId();

        $this->_logMessage('Finished removing duplicate attributes');

        $this->_addMissingAttributes();
    }

    /**
     * Remove relations with external ids if there is none anymore after the attribute has been deleted
     */
    private function _removeExternalIdsWithoutInternalId() {
        $this->_logMessage('Started removing obsolete external id internal id relations');
        $read = Mage::getSingleton('core/resource')->getConnection('core_write');
        $result=$read->query('SELECT bierao.id FROM bonaparte_importexport_external_relation_attribute_option bierao
	                            LEFT JOIN eav_attribute_option eao ON bierao.internal_id = eao.option_id
	                            WHERE eao.option_id IS NULL');

        $relationIds = array();
        while($row = $result->fetch()) {
            $relationIds[] = $row['id'];
        }

        if(empty($relationIds)) {
            $this->_logMessage('No obsolete external id internal id relations found');
            return;
        }

        $relationIds = implode(',', $relationIds);
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->query('DELETE FROM bonaparte_importexport_external_relation_attribute_option
                        WHERE id IN (' . $relationIds . ')');

        $this->_logMessage('Finished removing obsolete external id internal id relations');
    }

    private function _addMissingAttributes() {
        $this->_logMessage('Adding missing attributes');

        $missingAttributes = array(
            'AnimalOrigin' => array( // bolean
                0,
                1
            ),
            'DisplayComposition' => array( // boolean
                0,
                1
            ),
            'ColorGroup' => array( // single value
                0, 1, 2, 3, 4, 5, 6, 7, 8, 9
            ),
            'AdCodes' => array( // multiple values
                'Catalogue 1',
                'Catalogue 2'
            ),
            'StyleNbr' => array(), // single value
            'MeasurementChart' => array(), // text, needs values
            'MeasureChartAbrv' => array(), // text, needs values
            'Length' => array(), // single value, needs values
            'WashInstructions' => array() // single value, needs values
        );

        $this->_data = array_merge($this->_data, $missingAttributes);
        $this->_data['PriceCatalogAdcode'] = $this->_data['CatalogAdcode'];
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        $this->_removeDuplicates();

        $this->_logMessage('Started importing all attributes');

        $storeViews = array();
        foreach(Mage::app()->getWebsites() as $website) {
            $storeIds = $website->getStoreIds();
            $storeViews[strtolower($website->getCode())] = array_pop($storeIds);
        }

        $attributesNumber = count($this->_data);
        $currentAttributeNumber = 0;
        foreach ($this->_data as $attributeCode => $attributeConfigurationData) {
            $code = self::ATTRIBUTE_PREFIX . strtolower($attributeCode);
            $currentAttributeNumber++;

            // logs
            $this->_logMessage(
                'Processing attribute '
                    . $currentAttributeNumber
                    . ' out of '
                    . $attributesNumber
                    . ' with the code '
                    . '"'
                    . $code
                    . '"'
            );
            $this->_logMemoryUsage();

            // add the store id in the label value array
            $optionValues = array();
            $optionIds = array();
            $counter = 0;
            $this->_logMessage('Adding attribute options(' . count($attributeConfigurationData) . ')');
            foreach ($attributeConfigurationData as $optionId => $optionValue) {
                $this->_logMessage('.', false);

                if (is_array($optionValue)) {
                    $optionValues['option' . $counter][0] = $optionValue[2];
                    $optionValues['option' . $counter][$storeViews['dk']] = $optionValue[0];
                    $optionValues['option' . $counter][$storeViews['ch']] = $optionValue[1];
                    $optionValues['option' . $counter][$storeViews['uk']] = $optionValue[2];
                    $optionValues['option' . $counter][$storeViews['de']] = $optionValue[3];
                    $optionValues['option' . $counter][$storeViews['nl']] = $optionValue[4];
                    $optionValues['option' . $counter][$storeViews['se']] = $optionValue[5];
                } else {
                    //add option id to the label if label is smaller than 2 char
                    if ((strlen($optionValue) <= 2) && ($attributeCode!='Size')) {
                        $optionValue = $optionId . '_' . $optionValue;
                    }
                    $optionValues['option' . $counter][0] = $optionValue;
                    $optionValues['option' . $counter][$storeViews['dk']] = $optionValue;
                    $optionValues['option' . $counter][$storeViews['ch']] = $optionValue;
                    $optionValues['option' . $counter][$storeViews['uk']] = $optionValue;
                    $optionValues['option' . $counter][$storeViews['de']] = $optionValue;
                    $optionValues['option' . $counter][$storeViews['nl']] = $optionValue;
                    $optionValues['option' . $counter][$storeViews['se']] = $optionValue;
                }

                $optionIds[] = $optionId;
                $counter++;
            }

            if(in_array($attributeCode, array('Catalogue', 'Season', 'WashIcon', 'AdCodes'))) {
                $frontendInput = 'multiselect';
            } elseif(in_array($attributeCode, array('AnimalOrigin', 'DisplayComposition'))) {
                $frontendInput = 'boolean';
            } elseif(in_array($attributeCode, array('MeasurementChart','MeasureChartAbrv'))) {
                $frontendInput = 'text';
            } else {
                $frontendInput = 'select';
            }

            $attributeData = array(
                'attribute_code' => $code,
                'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
                'frontend_input' => $frontendInput,
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
            if ($attributeCode=='Size') {
                $attributeData['is_configurable'] = 1;
            }
            $model->addData($attributeData);
            $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
            $model->setIsUserDefined(1);

            try {
                $this->_logMessage('Saving attribute');
                $model->save();
                $this->_logMessage('Saved');
            } catch (Exception $e) {
                $this->_logMessage('Sorry, error occured while trying to save the attribute. Error: ' . $e->getMessage());
            }

            $databaseOptions = $model->getSource()->getAllOptions(false);
            $idOrderedDatabaseOptions = array();
            foreach ($databaseOptions as $option) {
                $idOrderedDatabaseOptions[$option['value']] = $option['label'];
            }
            ksort($idOrderedDatabaseOptions);
            $internalOptionIds = array_keys($idOrderedDatabaseOptions);

            $model->clearInstance();

            $this->_logMessage('Relating external id of options to the internal id of options');
            foreach ($internalOptionIds as $key => $internalOptionId) {
                $model = Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option');
                $model->setType(Bonaparte_ImportExport_Model_External_Relation_Attribute_Option::TYPE_ATTRIBUTE_OPTION);
                $model->setExternalId($optionIds[$key]);
                $model->setInternalId($internalOptionId);
                $model->save();
                $model->clearInstance();
            }
            unset($collection);
            $this->_logMessage('Finished relating external id of options to the internal id of options');

            $model = Mage::getModel('eav/entity_setup', 'core_setup');
            $attributeId = $model->getAttribute('catalog_product', $code);
            $attributeSetId = $model->getAttributeSetId('catalog_product', 'Default');
            $attributeGroupId = $model->getAttributeGroup('catalog_product', $attributeSetId, 'General');

            //add attribute to a set
            $model->addAttributeToSet(
                'catalog_product',
                $attributeSetId,
                $attributeGroupId['attribute_group_id'],
                $attributeId['attribute_id']
            );
            unset($model);
        }

        $this->_logMessage('Finished importing attributes' . "\n" );
    }

}