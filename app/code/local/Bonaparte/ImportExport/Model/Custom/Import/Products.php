<?php

/**
 * Stores the business logic for the custom product import
 */
class Bonaparte_ImportExport_Model_Custom_Import_Products extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    /**
     * Path to the Size Translation file
     *
     * @var string
     */
    const CONFIGURATION_FILE_SIZE_TRANSLATION = '/dump_files/xml/SizeTranslation.csv';
    private $_customSizes = array();
    private $_bnpAttributes = array();
    private $_attributeSetIdd = 0;
    private $_attributeIdd = 0;
    private $_allWebsiteIDs = array();

    /**
     * Construct import model
     */
    public function _construct()
    {
        echo 'Start' . date("h:i:s a", time());
        $this->_configurationFilePath = array();
        $configFilesPath = Mage::getBaseDir() . '/dump_files/xml/test2';
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
    public function _getAttributeSetID($label)
    {

        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $SetId = intval(Mage::getModel('eav/entity_attribute_set')->getCollection()->setEntityTypeFilter($entityTypeId)->addFieldToFilter('attribute_set_name', $label)->getFirstItem()->getAttributeSetId());
        $this->_attributeSetIdd = $SetId ;
    }

    public function _getAttributeID($label)
    {
        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        $attr_id = $eavAttribute->getIdByCode('catalog_product', $label);
        $this->_attributeIdd = $attr_id;
    }

    public function _getCustomSize()
    {
        // create Custom Sizes array
        $customSize = array();
        $data_csv = array();
        $handle = fopen(Mage::getBaseDir() . self::CONFIGURATION_FILE_SIZE_TRANSLATION, 'r');

        while ($data_csv = fgetcsv($handle,null,';','"')) {
            $customSize[$data_csv[3]] = $data_csv[2];
        }
        fclose($handle);
        return $customSize;
    }

    public function addAttributeOption($arg_attribute, $arg_value)
    {
        $attribute_model = Mage::getModel('eav/entity_attribute');
        $attribute_options_model= Mage::getModel('eav/entity_attribute_source_table') ;

        $attribute_code = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
        $attribute = $attribute_model->load($attribute_code);

        $attribute_table = $attribute_options_model->setAttribute($attribute);
        $options = $attribute_options_model->getAllOptions(false);

        $value['option'] = array($arg_value,$arg_value);
        $result = array('value' => $value);
        $attribute->setData('option',$result);
        $attribute->save();

        return $this->_getAttributeLabelId($arg_attribute, $arg_value);
    }


    /**
     * Recursive method to add unknown levels of categories
     *
     * @param mixed (integer|Mage_Catalog_Model_Category) $parentId
     * @param array $children
     */
    private function _addProduct($productData) {

        $configurable_attribute = "bnp_size";
        $attr_id = $this->_attributeIdd;

        $simpleProducts = array();
        $mediaAttributes = array (
            'image',
            'thumbnail',
            'small_image'
        );


           foreach ($productData['Items']['value'] as $productItem){
                // first step is to check if the size is custom or not
                if (in_array($productItem['Sizess']['value']['en'],array('50X100','70X140','ne size','one size','One size','One Size','ONE SIZE','onesize','Onesize','ONESIZE'))){
                    $productSizes = array('cst_'.$productItem['Sizess']['value']['en']);
                }
                elseif (!$this->_customSizes[$productItem['Sizess']['value']['en']]){
                    $productSizes =  explode("-", $productItem['Sizess']['value']['en']);
                }else{
                $productSizes = array($this->_customSizes[$productItem['Sizess']['value']['en']]);
                }

                foreach($productSizes as $productSize){

                    $attr_value = $productSize;
                    $configurableAttributeOptionId = $this->_getAttributeLabelId($configurable_attribute,$productSize);
                    if (!$configurableAttributeOptionId) {
                        $configurableAttributeOptionId = $this->addAttributeOption($configurable_attribute, $attr_value);
                    }

                        //create each simple product
                    $category_ids = array();
                    $category_idss = array();
                    $prefix_main_group = "";
                    $prefix_sub_group = "";
                    if ($productData['Program']['value']!='') $category_ids[]=$productData['Program']['value'];
                    if ($productData['ProductMainGroup']['value']!='') {
                        $prefix_main_group = $productData['Program']['value']?$productData['Program']['value']."_":"";
                        $category_ids[]= $prefix_main_group.$productData['ProductMainGroup']['value']; //tmunteanu add Program to product main group. Ex: M_001 where M = Program and 001 = Main Group
                        $prefix_sub_group = $prefix_main_group.$productData['ProductMainGroup']['value']."_";
                    }
                    if ($productData['ProductGroup']['value']!='') $category_ids[]=  $prefix_sub_group.$productData['ProductGroup']['value'];
                    foreach ($category_ids as $category_id){
                        $category = Mage::getModel('catalog/category')->getCollection()->addAttributeToFilter('old_id', $category_id)->load();
                        foreach ($category->getAllIds() as $idss) $category_idss []= $idss;

                    }

                    $productShortDescription =  explode(".", $productData['DescriptionCatalogues']['value']['en']);

                    // BEGIN external id relate to internal id
                    $externalIds = array_merge(
                        array(
                            $productItem['Color']['value'],
                            $productData['Fitting']['value']
                        ),
                        (array) $productData['Catalogue']['value'],
                        (array) $productData['Season']['value'],
                        (array) $productItem['WashIcon']['value']
                    );

                    foreach($externalIds as $key => $value) {
                        if(empty($value)) {
                            unset($externalIds[$key]);
                        }
                    }
                    $collection = Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option')
                        ->getCollection()
                        ->addFieldToFilter('external_id', array('in' => $externalIds))
                        ->load();

                    $externalIdToInternalId = array();
                    foreach($collection as $relation) {
                        $externalIdToInternalId[$relation->getExternalId()] = $relation->getInternalId();
                    }
                    // END external id relate to internal id

                    foreach ($productData['Catalogue']['value'] as $externalId){
                        $bnpCatalogueLabelIds[] = $externalIdToInternalId[$externalId];
                    }
                    foreach ($productData['Season']['value'] as $externalId){
                        $bnpSeasonLabelIds[] = $externalIdToInternalId[$externalId];
                    }
                    foreach ($productItem['WashIcon']['value'] as $externalId){
                        $bnpWashiconLabelIds[] = $externalIdToInternalId[$externalId];
                    }

                    $sProduct = Mage::getModel('catalog/product');
                    $sProduct
                        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
                        ->setTaxClassId(0) //none
                        ->setWeight(1)
                        ->setPrice("1000.00")
                        ->setMetaKeywords('MetaKeywords test')

                        ->setAttributeSetId($this->_attributeSetIdd)
                        ->setCategoryIds($category_idss)
                        ->setWebsiteIds($this->_allWebsiteIDs)

                        ->setSku($productItem['CinoNumber']['value'] . '-' . $productSize)
                        ->setBnpColor($externalIdToInternalId[$productItem['Color']['value']])
                        ->setBnpFitting($externalIdToInternalId[$productData['Fitting']['value']])

                        ->setMetaTitle($productData['HeaderWebs']['value']['en'] . 'MetaTitle')
                        ->setMetaDescription($productData['DescriptionCatalogues']['value']['en'] . 'MetaDescription')
                        ->setName($productData['HeaderWebs']['value']['en'])
                        ->setDescription($productData['DescriptionCatalogues']['value']['en'])

                        ->setShortDescription($productShortDescription[0] . '.')

                        ->setBnpCatalogue($bnpCatalogueLabelIds)
                        ->setBnpSeason($bnpSeasonLabelIds)
                        ->setBnpWashicon(implode(',', $bnpWashiconLabelIds))

                        ->setData($configurable_attribute, $configurableAttributeOptionId);

                    $sProduct->setStockData(array(
                        'is_in_stock' => 1,
                        'qty' => 99999
                    ));

                    // adding the images


                    foreach ($productItem['Resources']['value'] as $resource){
                        $picturePath = Mage::getBaseDir() . '/dump_files/pictures/' . $resource['OriginalFilename']['value'];
                        if (file_exists($picturePath) && ($resource['OriginalFilename']['value']!='')){
                            try {
                                $sProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
                            } catch (Exception $e) {
                                echo $e->getMessage();
                            }
                        } else {
                            echo "Product does not have an image or the path is incorrect. Path was: {$picturePath}<br/>\n";
                        }
                    }

                    try{
                        $sProduct->save();
                            // saving some data for configurable product creation
                        array_push(
                            $simpleProducts,
                            array(
                                "id" => $sProduct->getId(),
                                "price" => $sProduct->getPrice(),
                                "attr_code" => $configurable_attribute,
                                "attr_id" => $attr_id,
                                "value" => $configurableAttributeOptionId,
                                "label" => $attr_value
                            )
                        );
                        $sProduct->clearInstance();

                    }
                    catch (Exception $e){
                        echo "item " . $productData['HeaderWebs']['value']['en'] . " not added\n";
                        echo "exception:$e";
                    }


                }


                // create the configurable product

                $cProduct = Mage::getModel('catalog/product');
                $cProduct
                    ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
                    ->setTaxClassId(0)
                    ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                    ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                    ->setWebsiteIds($this->_allWebsiteIDs)
                    ->setCategoryIds($category_idss)
                    ->setAttributeSetId($this->_attributeSetIdd)
                    ->setSku($productItem['CinoNumber']['value'])
                    ->setName($productData['HeaderWebs']['value']['en'])
                    ->setShortDescription($productShortDescription[0].'.')
                    ->setDescription($productData['DescriptionCatalogues']['value']['en'])
                    ->setPrice("1000.00")
                    ->setUrlKey($productData['HeaderWebs']['value']['en'] . '_' . $productItem['CinoNumber']['value'])
                ;
                $cProduct->setCanSaveConfigurableAttributes(true);
                $cProduct->setCanSaveCustomOptions(true);

                $cProductTypeInstance = $cProduct->getTypeInstance();

                $cProductTypeInstance->setUsedProductAttributeIds(array($attr_id));
                $attributes_array = $cProductTypeInstance->getConfigurableAttributesAsArray();

                foreach($attributes_array as $key => $attribute_array) {
                    $attributes_array[$key]['use_default'] = 1;
                    $attributes_array[$key]['position'] = 0;

                    if (isset($attribute_array['frontend_label'])) {
                        $attributes_array[$key]['label'] = $attribute_array['frontend_label'];
                    }
                    else {
                        $attributes_array[$key]['label'] = $attribute_array['attribute_code'];
                    }
                }
                $cProduct->setConfigurableAttributesData($attributes_array);

                $dataArray = array();
                foreach ($simpleProducts as $simpleArray) {
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

                $cProduct->setConfigurableProductsData($dataArray);

                $cProduct->setStockData(array(
                    'use_config_manage_stock' => 1,
                    'is_in_stock' => 1,
                    'is_salable' => 1
                ));
                // adding the images


               foreach ($productItem['Resources']['value'] as $resource){
                   $picturePath = Mage::getBaseDir() . '/dump_files/pictures/' . $resource['OriginalFilename']['value'];
                   if (file_exists($picturePath) && ($resource['OriginalFilename']['value']!='')){
                       try {
                           $cProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
                       } catch (Exception $e) {
                           echo $e->getMessage();
                       }
                   } else {
                       echo "Product does not have an image or the path is incorrect. Path was: {$picturePath}<br/>\n";
                   }
               }
                try{
                    $cProduct->save();
                    $cProduct->clearInstance();
                }
                catch (Exception $e){
                    echo "item " . $productData['HeaderWebs']['value']['en'] . " not added\n";
                    echo "exception:$e";
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
        $this->_customSizes = $this->_getCustomSize();
        $this->_getAttributeSetID('Default');
        $this->_getAttributeID('bnp_size');
        $this->_allWebsiteIDs = Mage::getModel('core/website')->getCollection()->getAllIds();

        //$products = array();
        echo '<br>***************PRODUCT IMPORT';
        $counter = 1;
        foreach($this->_data as $productConfig) {
            $productData = array();
            $this->_extractConfiguration($productConfig->getNode(), $productData);
            echo '<br>\n ' . $counter++ . '. '. date("h:i:s a", time()) . ' ' . $productData['Items']['value'][0]['id'];
            $this->_addProduct($productData);
          //  $products[] = $productData;

        }
        echo '<br>' . date("h:i:s a", time());

        echo 'DONE';
    }

}