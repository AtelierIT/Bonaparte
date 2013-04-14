<?php

/**
 * Stores the business logic for the custom product import
 */
class Bonaparte_ImportExport_Model_Custom_Import_Products extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_configurationFilePath = array();
        $configFilesPath = '/var/www/bonaparte/magento/dump_files/xml/last_product';
        $files = scandir($configFilesPath);

        foreach($files as $fileName) {
            if(strlen($fileName) < 3) {
                continue;
            }
            $this->_configurationFilePath[] = $configFilesPath . '/' . $fileName;
        }
        unset($fileName);

        if(!is_array($this->_configurationFilePath)) {
            return parent::_initialize();
        }

        $limit = 5000;
        $counter = 0;
        foreach($this->_configurationFilePath as $filePath) {
            if($counter == $limit) {
                break;
            }

            $this->_data[] = new Varien_Simplexml_Config($filePath);
            
            $counter++;
        }
    }

    /**
     * Recursive method to add unknown levels of categories
     *
     * @param mixed (integer|Mage_Catalog_Model_Category) $parentId
     * @param array $children
     */
    private function _addProduct($data) {
        return;
    }

    private function _extractConfiguration($node, &$productData) {
        foreach($node as $element) {
            $key = $element->getName();

            $value = (string)$element->value;
            if(empty($value)) {
                $value = (string)$element;
                if(in_array($key, array('SizeGroup', 'AdCodes', 'Prices'))) {
                    $stringXML = new Varien_Simplexml_Element('<general_bracket>' . $value . '</general_bracket>');//simplexml_load_string($value, null);
                    $value = array();
                    switch($key) {
                        case 'SizeGroup';
                            $stringXML = $stringXML->SizeRange;
                            $value[$stringXML->getName()] = array(
                                'name' => $stringXML->getAttribute('name'),
                                'value' => (string)$stringXML
                            );
                            break;
                        case 'AdCodes':
                            foreach($stringXML->AdCode as $subElement) {
                                $value[] = array(
                                    'catalogue' => $subElement->getAttribute('catalogue'),
                                    'value' => (string)$subElement,
                                    'key' => 'AdCode'
                                );
                            }
                            unset($subElement);
                            break;
                        case 'Prices':
                            foreach($stringXML->Catalogue as $subElement) {
                                $subValue = array();
                                foreach($subElement->Country as $country) {
                                    $subValue[] = array(
                                        'code' => $country->getAttribute('code'),
                                        'currency' => $country->getAttribute('currency'),
                                        'size' => (string)$country->Size
                                    );
                                }
                                $value['Catalogue'][] = array(
                                    'name' => $subElement->getAttribute('name'),
                                    'value' => $subValue
                                );
                            }
                            break;
                    }
                }
            } else {
                $value = null;
            }

            /*if(in_array($key, array('MeasureHeading', 'CatalogAdcode', 'ActiveCatalogue', 'Catalogue'))) {
                $value = null;
            }*/

            if(empty($value)) {
                foreach($element as $subElement) {
                    $locale = $subElement->getAttribute('locale');
                    $text = (string)$subElement;
                    if($locale) {
                        $value[$locale] = $text;
                        continue;
                    }

                    $id = $subElement->getAttribute('id');
                    if(!empty($id)) {
                        $order = $subElement->getAttribute('order');
                        $text = array(
                            'id' => $id,
                            'order' => $order
                        );
                    }

                    $value[] = $text;
                }
                unset($locale, $text, $order, $subElement);
            }

            if(in_array($key, array('Resources', 'Items'))) {
                $value = array();
                foreach($element as $subElement) {
                    $subProductData = array();
                    $subProductData['id'] = $subElement->getAttribute('id');
                    $this->_extractConfiguration($subElement, $subProductData);
                    $value[] = $subProductData;
                }
                unset($subElement);
            }

            $finalValue['value'] = $value;
            $cvl = $element->getAttribute('cvl');
            if(!empty($cvl)) {
                $finalValue['cvl'] = $cvl;
            }

            $productData[$key] = $finalValue;
            unset($cvl, $finalValue);
        }
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        $products = array();
        foreach($this->_data as $productConfig) {
            $productData = array();
            $this->_extractConfiguration($productConfig->getNode(), $productData);
            $this->_addProduct($productData);
            $products[] = $productData;
        }

        echo 'DONE';
    }

}