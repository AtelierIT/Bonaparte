<?php

/**
 * Stores the business logic for the custom product import
 */
class Bonaparte_ImportExport_Model_Custom_Import_Products extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{

    private $_bnpAttributes = array();
    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_configurationFilePath = array();
        $configFilesPath = Mage::getBaseDir() . '/dump_files/xml/last_product';
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
     * Construct array with attributes options ids
     */
    public function _getAttributeLabelId($attributeCode,$label)
    {
        if (isset($this->_bnpAttributes[$attributeCode])){
            return $this->_bnpAttributes[$attributeCode][$label];
        }

        $productModel = Mage::getModel('catalog/product');
        $attributeBnpCatalogue = $productModel->getResource()->getAttribute($attributeCode);

        foreach ($attributeBnpCatalogue->getSource()->getAllOptions() as $option){
            $this->_bnpAttributes[$attributeCode][$option['label']] = $option['value'];
        }

        return $this->_bnpAttributes[$attributeCode][$label];

    }








    /**
     * Recursive method to add unknown levels of categories
     *
     * @param mixed (integer|Mage_Catalog_Model_Category) $parentId
     * @param array $children
     */
    private function _addProduct($productData) {
        $_simpleProductsIds = array();
            // for testing, to be removed
        if ($productData['ProductGroup']['value'] == '4901'){

            foreach ($productData['Items']['value'] as $productItem){
                $productSizes =  explode("-", $productItem['Sizess']['value']['en']);
                    //create each simple product
                foreach($productSizes as $productSize){

                        $product = Mage::getModel('catalog/product');
                        $product->setTypeId('simple');
                        $product->setTaxClassId(0); //none
                        $product->setWebsiteIds(array(1));  // store id
                        $product->setAttributeSetId(9); //product Attribute Set
                        $product->setSku($productItem['CinoNumber']['value'] . '_' . $productSize);
                        $product->setName($productData['HeaderWebs']['value']['en']);
                        $product->setDescription($productData['DescriptionCatalogues']['value']['en']);
                        $product->setPrice("1000.00");
                        $product->setShortDescription($productData['DescriptionCatalogues']['value']['en']);
                        $product->setWeight(0);
                        $product->setStatus(1); //enabled
                        $product->setVisibility(1); //nowhere
                        $product->setMetaDescription('MetaDescription test');
                        $product->setMetaTitle('MetaTitle test');
                        $product->setMetaKeywords('MetaKeywords test');

                            foreach ($productData['Catalogue']['value'] as $label ){
                                $bnpCatalogueLabelIds[]=$this->_getAttributeLabelId('bnp_catalogue',$label);
                            }
                            $product->setBnpCatalogue($bnpCatalogueLabelIds);
                            foreach ($productData['Season']['value'] as $label ){
                                $bnpCatalogueLabelIds[]=$this->_getAttributeLabelId('bnp_season',$label);
                            }
                            $product->setBnpSeason($bnpCatalogueLabelIds);
                            foreach ($productData['WashIcon']['value'] as $label ){
                                $bnpCatalogueLabelIds[]=$this->_getAttributeLabelId('bnp_washicon',$label);
                            }
                            $product->setWashicon($bnpCatalogueLabelIds);

                            $product->setBnpFitting($this->_getAttributeLabelId('bnp_fitting',$productData['Fitting']['value']));
                            $product->setColor($this->_getAttributeLabelId('bnp_color',$productData['Color']['value']));

                        $category_ids = array();
                        $category_idss = array();
                        if ($productData['Program']['value']!='') $category_ids[]=$productData['Program']['value'];
                        if ($$productData['ProductMainGroup']['value']!='') $category_ids[]=$productData['ProductMainGroup']['value'];
                        if ($productData['ProductSubGroup']['value']!='') $category_ids[]=$productData['ProductSubGroup']['value'];
                        foreach ($category_ids as $category_id){
                            $category = Mage::getModel('catalog/category')->getCollection()->addAttributeToFilter('old_id', $category_id)->load();
                            foreach ($category->getAllIds() as $idss) $category_idss []= $idss;

                        }

                        $product->setCategoryIds($category_idss);

                        try{
                            $product->save();
                            $_simpleProductsIds[$productItem['CinoNumber']['value'] . '_' . $productSize] = $product->getId();

                        }
                        catch (Exception $e){
                            echo "item " . $productData['HeaderWebs']['value']['en'] . " not added\n";
                            echo "exception:$e";
                        }


                }


                // create the configurable product
                $product = Mage::getModel('catalog/product');
                $product->setTypeId('configurable');
                $product->setTaxClassId(0); //none
                $product->setWebsiteIds(array(1));  // store id
                $product->setAttributeSetId(9); //product Attribute Set
                $product->setSku($productItem['CinoNumber']['value']);
                $product->setName($productData['HeaderWebs']['value']['en']);
                $product->setDescription($productData['DescriptionCatalogues']['value']['en']);
                $product->setPrice("1000.00");
                $product->setShortDescription($productData['DescriptionCatalogues']['value']['en']);
                $product->setWeight(0);
                $product->setStatus(1); //enabled
                $product->setVisibility(1); //nowhere
                $product->setMetaDescription('MetaDescription test');
                $product->setMetaTitle('MetaTitle test');
                $product->setMetaKeywords('MetaKeywords test');
                foreach ($productData['Catalogue']['value'] as $label ){
                    $bnpCatalogueLabelIds[]=$this->_getAttributeLabelId('bnp_catalogue',$label);
                }
                $product->setBnpCatalogue($bnpCatalogueLabelIds);
                foreach ($productData['Season']['value'] as $label ){
                    $bnpCatalogueLabelIds[]=$this->_getAttributeLabelId('bnp_season',$label);
                }
                $product->setBnpSeason($bnpCatalogueLabelIds);
                foreach ($productData['WashIcon']['value'] as $label ){
                    $bnpCatalogueLabelIds[]=$this->_getAttributeLabelId('bnp_washicon',$label);
                }
                $product->setWashicon($bnpCatalogueLabelIds);

                $product->setBnpFitting($this->_getAttributeLabelId('bnp_fitting',$productData['Fitting']['value']));
                $product->setColor($this->_getAttributeLabelId('bnp_color',$productData['Color']['value']));

                $category_ids = array();
                $category_idss = array();
                if ($productData['Program']['value']!='') $category_ids[]=$productData['Program']['value'];
                if ($$productData['ProductMainGroup']['value']!='') $category_ids[]=$productData['ProductMainGroup']['value'];
                if ($productData['ProductSubGroup']['value']!='') $category_ids[]=$productData['ProductSubGroup']['value'];
                foreach ($category_ids as $category_id){
                    $category = Mage::getModel('catalog/category')->getCollection()->addAttributeToFilter('old_id', $category_id)->load();
                    foreach ($category->getAllIds() as $idss) $category_idss []= $idss;

                }
                $product->setCategoryIds($category_idss);

                $dataArray = array();
                foreach ($_simpleProductsIds as $simpleArray) {
                    $dataArray[$simpleArray['id']] = array();
                    foreach ($attributes_array as $attrArray) {
                        array_push(
                            $dataArray[$simpleArray['id']],
                            array(
                                "attribute_id" => $simpleArray['attr_id'],
                                "label" => $simpleArray['label'],
                                "is_percent" => false,
                                "pricing_value" => $simpleArray['price']
                            )
                        );
                    }
                }





                try{
                    $product->save();

                }
                catch (Exception $e){
                    echo "item " . $productData['HeaderWebs']['value']['en'] . " not added\n";
                    echo "exception:$e";
                }











            }

        }

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
                        case 'SizeGroup':
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