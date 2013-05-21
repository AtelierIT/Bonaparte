<?php
ini_set('memory_limit', '3072M');
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

    /**
     * Path to
     *
     * @var string
     */
    const CONFIGURATION_FILE_INVENTORY = '/dump_files/csv/ARTIKLAR.TXT';
    /**
     * Path to the product configuration XML files
     *
     * @var string
     */
//    const CONFIGURATION_FILE_PATH = '/chroot/home/stagebon/upload/xml/product';       // server configuration
    const CONFIGURATION_FILE_PATH = '/dump_files/xml/test6';         // local developer station


    /**
     * Path to the product pictures source files
     *
     * @var string
     */
//    const PICTURE_BASE_PATH = '/chroot/home/stagebon/upload/pictures/';
    const PICTURE_BASE_PATH = '/dump_files/pictures/';


    /**
     * Contains all sizes that need to be translated to short ERP name
     *
     * @var array
     */
    private $_customSizes = array();

    /**
     * Contains all product QTYs, the keys are product SKUs
     *
     * @var array
     */
    private $_productInventory = array();

    /**
     * Contains all BNP attributes
     *
     * @var array
     */
    private $_bnpAttributes = array();

    /**
     * Contains the attribute set id
     *
     * @var integer
     */
    private $_attributeSetIdd = 0;

    /**
     * Contains the attribute id of the attribute user for configurable product creation
     *
     * @var array
     */
    private $_attributeIdd = 0;
    private $_mediaGalleryId = 0;
    private $_baseImageId = 0;
    private $_smallImageId = 0;
    private $_thumbnailId = 0;


    /**
     * Contains all Websites IDs
     *
     * @var array
     */
    private $_allWebsiteIDs = array();

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_logMessage('Start PRODUCT IMPORT');
        $this->_configurationFilePath = array();
//        $configFilesPath = self::CONFIGURATION_FILE_PATH;//server
//     to be changed on local computer
      $configFilesPath = Mage::getBaseDir() . self::CONFIGURATION_FILE_PATH;

        $files = scandir($configFilesPath);
        $this->_logMessage('There are ' . (count($files)-2) . 'files');
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

        $limit = 5000;  //change the limit if the number of files is greater than 5000
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
     *
     * @param $attributeCode
     * @param $label
     *
     * @return integer
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
     * Get the attributes set id
     *
     * @param $label - attributes label
     */
    public function _getAttributeSetID($label)
    {
        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $SetId = intval(Mage::getModel('eav/entity_attribute_set')->getCollection()->setEntityTypeFilter($entityTypeId)->addFieldToFilter('attribute_set_name', $label)->getFirstItem()->getAttributeSetId());
        $this->_attributeSetIdd = $SetId ;
    }

    /**
     * Get the attribute id
     *
     * @param $label - attributes label
     */
    public function _getAttributeID($label)
    {
        $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
        $attr_id = $eavAttribute->getIdByCode('catalog_product', $label);
        return $attr_id;
    }

    /**
     * Get the custom sizes from the Translation file
     */
    public function _getCustomSize()
    {
        $customSize = array();
        $handle = fopen(Mage::getBaseDir() . self::CONFIGURATION_FILE_SIZE_TRANSLATION, 'r');
        while ($data_csv = fgetcsv($handle,null,';','"')) {
            $customSize[$data_csv[3]] = $data_csv[2];
        }
        fclose($handle);
        return $customSize;
    }

    /**
     * Get the product inventory from file
     *
     * @return array
     */
    public function _getProductInventory()
    {
        $productInventory = array();
        $handle = fopen(Mage::getBaseDir() . self::CONFIGURATION_FILE_INVENTORY, 'r');
        while ($data_csv = fgets($handle)) {
            $data_csv = explode(';', $data_csv);
            $headArticleExploded = explode('-', $data_csv[1]);
            $productInventory[$headArticleExploded[1] . '-' . $data_csv[6]] = $data_csv[8];
        }
        fclose($handle);

        return $productInventory;
    }

    /**
     * Add attribute option
     */
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

        // add AttributeOptionExternalInternalIdRelation
        $internalId = $this->_getAttributeLabelId($arg_attribute, $arg_value);
//        Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option')
//            ->setType(Bonaparte_ImportExport_Model_External_Relation_Attribute_Option::TYPE_ATTRIBUTE_OPTION)
//            ->setExternalId($arg_value)
//            ->setInternalId($internalId)
//            ->setAttributeCode($arg_attribute)
//            ->save()
//            ->clearInstance();

        return $internalId;
    }


    /**
     * Function copy all images from feeding folder to magento /media folder
     *
     */
    private function _getProductImageReady()
    {

        $images = scandir(Mage::getBaseDir().self::PICTURE_BASE_PATH); //localhost
//        $images = scandir(self::PICTURE_BASE_PATH); //STAGE
        $_mediaBase = Mage::getBaseDir('media').'/catalog/product/';

        $pictureNumber = count($images);
        $this->_logMessage($pictureNumber.' in '.$_mediaBase);
        $counter = 1;
        foreach($images as $image) {
            if (in_array($image,array('.','..')))
                continue;

            $firstDir = $_mediaBase.$image[0];
            $secondDir = $firstDir.'/'.$image[1];
            $path = $secondDir.'/'.$image;

            if(!file_exists($path)) {
                if (!file_exists($secondDir)) mkdir($secondDir, 0775, true);
                $this->_logMessage('Creating ' . $counter++ . ' of ' . $pictureNumber . ' - from ' . self::PICTURE_BASE_PATH.$image . ' - to ' . $path);
//                copy(self::PICTURE_BASE_PATH.$image, $path); // Stage version
                copy(Mage::getBaseDir().self::PICTURE_BASE_PATH.$image, $path); //localhost version

            }
            else $this->_logMessage('Existing ' . $counter++ . ' of ' . $pictureNumber . ' - ' . $image);
       }


    }

    /**
     * Function build to replace the MAGENTO addImageToMediaGallery
     *
     */
    private function _addProductImage($productID, $pictureName)
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $connW = Mage::getSingleton('core/resource')->getConnection('core_write');
        $pictureValueField = '/'.$pictureName[0].'/'.$pictureName[1].'/'.$pictureName;

        /*
           *    Check the existing images
           */

        $sql = "SELECT * FROM catalog_product_entity_media_gallery WHERE entity_id IN (".$productID.") AND value IN ('".$pictureValueField."');";
        $_galleryImgs = $conn->fetchAll($sql);

        if (!$_galleryImgs){
            $sql = "INSERT INTO catalog_product_entity_media_gallery (attribute_id, entity_id, value) VALUES (".$this->_mediaGalleryId.",".$productID.",'".$pictureValueField."');";
            $connW->query($sql);
        }

        $sql = "SELECT * FROM catalog_product_entity_varchar WHERE entity_id IN (".$productID.") AND attribute_id IN (".$this->_baseImageId.",".$this->_smallImageId.",".$this->_thumbnailId.");";
        $_imageAssoc = $conn->fetchAll($sql);

        if (!$_imageAssoc){
            $sql = "INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (4,".$this->_baseImageId.",0,".$productID.",'".$pictureValueField."'), (4,".$this->_smallImageId.",0,".$productID.",'".$pictureValueField."'), (4,".$this->_thumbnailId.",0,".$productID.",'".$pictureValueField."');";
            $connW->query($sql);
        }

    }


    /**
     * Function to delete all products
     */
    private function _deleteAllProducts(){
        $products = Mage::getResourceModel('catalog/product_collection')->getAllIds();
        foreach ($products as $key => $productId)
        {
            try {
                $product = Mage::getSingleton('catalog/product')->load($productId);
                Mage::dispatchEvent('catalog_controller_product_delete', array('product' => $product));
                $this->_logMessage('Deleting product id'.$productId);
                $product->delete();
            } catch (Exception $e) {
                $this->_logMessage('Could not delete product id'.$productId);
            }
        }
    }


    /**
     * Function build to add product to database
     *
     * @param array $productData
     */
    private function _addProduct($productData) {

        $configurable_attribute = "bnp_size";
        $attr_id = $this->_attributeIdd;
        $cino_picture_directory = Mage::getBaseDir('media') . '/cino/';
//        $cino_picture_directory = Mage::getBaseDir() . '/dump_files/temp/';

//        $pictureBasePath = '/chroot/home/stagebon/upload/pictures/';
        $pictureBasePath = Mage::getBaseDir() . '/dump_files/pictures/';

//        $mediaAttributes = array('image','thumbnail','small_image');
        $this->_logMessage('Creating ' . count($productData['Items']['value']) . ' from this file');
        $productCounter = 1;
           foreach ($productData['Items']['value'] as $productItem){

               $simpleProducts = array();
               $productSizes = array();
               $this->_logMessage( $productCounter++ . ' Configuring product details ...');
                // first step is to check if the size is custom or not
                if (in_array($productItem['Sizess']['value']['en'],array('50X100','70X140','ne size','one size','One size','One Size','ONE SIZE','onesize','Onesize','ONESIZE'))){
                    $productSizes = array('cst_'.$productItem['Sizess']['value']['en']);
                }
                elseif (!$this->_customSizes[$productItem['Sizess']['value']['en']]){
                    $productItemSizess = $productItem['Sizess']['value']['en'] == $productItem['Sizess']['value']['de']?$productItem['Sizess']['value']['en']:$productItem['Sizess']['value']['en'] . '-' . $productItem['Sizess']['value']['de'];
                    $productSizesTemp =  explode("-", $productItemSizess);
                    foreach ($productSizesTemp as $productSizeTemp)
                        if (!$this->_customSizes[$productSizeTemp]) {
                            $productSizes[] = $productSizeTemp;
                        } else{
                            $productSizes[] = $this->_customSizes[$productSizeTemp];
                        }

                }else{
                $productSizes = array($this->_customSizes[$productItem['Sizess']['value']['en']]);
                }

                $this->_logMessage('Creating ' . count($productSizes) . ' simple products');
               if (count($productSizes)==1) $productOneSize=1;
               else $productOneSize=0;

                foreach($productSizes as $productSize){
                    $this->_logMessage('.', false);
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
                            $productData['Fitting']['value'],
                            $productData['Composition']['value'],
                            $productData['Concept']['value'],
                            $productData['Program']['value'],
                            $productData['ProductMainGroup']['value'],
                            $productData['ProductGroup']['value'],
                            $productData['ProductSubGroup']['value']
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

                    $bnpCatalogueLabelIds = array();
                    foreach ($productData['Catalogue']['value'] as $externalId){
                        $bnpCatalogueLabelIds[] = $externalIdToInternalId[$externalId];
                    }
                    $bnpSeasonLabelIds = array();
                    foreach ($productData['Season']['value'] as $externalId){
                        $bnpSeasonLabelIds[] = $externalIdToInternalId[$externalId];
                    }
                    $bnpWashiconLabelIds = array();
                    foreach ($productItem['WashIcon']['value'] as $externalId){
                        $bnpWashiconLabelIds[] = $externalIdToInternalId[$externalId];
                    }
                    $productSKU= $productItem['CinoNumber']['value'] . '-' . $productSize;
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

                        ->setSku($productOneSize?$productItem['CinoNumber']['value']:$productSKU)
                        ->setBnpColor($externalIdToInternalId[$productItem['Color']['value']])
                        ->setBnpFitting($externalIdToInternalId[$productData['Fitting']['value']])

                        ->setMetaTitle($productData['HeaderWebs']['value']['en'] . 'MetaTitle')
                        ->setMetaDescription($productData['DescriptionCatalogues']['value']['en'] . 'MetaDescription')
                        ->setName($productData['HeaderWebs']['value']['en'])
                        ->setDescription($productData['DescriptionCatalogues']['value']['en'])

                        ->setShortDescription($productShortDescription[0] . '.')
                        ->setUrlKey($productData['HeaderWebs']['value']['en'] . '_' . $productItem['CinoNumber']['value'] . '_' . $productSize)

                        ->setBnpCatalogue(implode(',', $bnpCatalogueLabelIds))
                        ->setBnpSeason(implode(',', $bnpSeasonLabelIds))
                        ->setBnpWashicon(implode(',', $bnpWashiconLabelIds))
                        ->setBnpColorgroup($this->_getAttributeLabelId("bnp_colorgroup",$productItem['ColorGroup']['value']))
                        ->setBnpMeasurechartabrv($productData['MeasureChartAbrv']['value'])
                        ->setBnpMeasurementchart($productItem['MeasurementChart']['value'])
                        ->setBnpProgram($externalIdToInternalId[$productData['Program']['value']])
                        ->setBnpProductmaingroup($externalIdToInternalId[$productData['ProductMainGroup']['value']])
                        ->setBnpProductgroup($externalIdToInternalId[$productData['ProductGroup']['value']])
                        ->setBnpProductsubgroup($externalIdToInternalId[$productData['ProductSubGroup']['value']])

                        ->setBnpComposition($externalIdToInternalId[$productData['Composition']['value']])
                        ->setBnpConcept($externalIdToInternalId[$productData['Concept']['value']])

                        ->setData($configurable_attribute, $configurableAttributeOptionId);
                    $productQTY = (!is_null($this->_productInventory[$productSKU]))?$this->_productInventory[$productSKU]:"99999";

                    $sProduct->setStockData(array(
                        'is_in_stock' => (($productQTY>0)?1:0),
                        'qty' => $productQTY
                    ));




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

                        // adding the item images
                        foreach ($productItem['Resources']['value'] as $resource){
                            $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
                            if (file_exists($picturePath) && ($resource['OriginalFilename']['value']!='')){
                                try {
                                    $this->_addProductImage($sProduct->getId(),$resource['OriginalFilename']['value']);
//                                $sProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
                                    $this->_logMessage('O', false);
                                } catch (Exception $e) {
                                    echo $e->getMessage();
                                }
                            } else {
                                $this->_logMessage('X', false);
                            }
                        }
                        // adding the BNP 'style' images
                        foreach ($productData['Resources']['value'] as $resource){
                            $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
                            if (file_exists($picturePath) && ($resource['OriginalFilename']['value']!='')){
                                try {
                                    $this->_addProductImage($sProduct->getId(),$resource['OriginalFilename']['value']);
//                              $sProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
                                    $this->_logMessage('O', false);
                                } catch (Exception $e) {
                                    echo $e->getMessage();
                                }
                            } else {
                                $this->_logMessage('X', false);
                            }
                        }




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
                    ->setSku($productItem['CinoNumber']['value'].'c')
                    ->setName($productData['HeaderWebs']['value']['en'])
                    ->setShortDescription($productShortDescription[0].'.')
                    ->setDescription($productData['DescriptionCatalogues']['value']['en'])
                    ->setPrice("1000.00")

                    ->setBnpColor($externalIdToInternalId[$productItem['Color']['value']])
                    ->setBnpFitting($externalIdToInternalId[$productData['Fitting']['value']])
                    ->setBnpColorgroup($this->_getAttributeLabelId("bnp_colorgroup",$productItem['ColorGroup']['value']))
                    ->setBnpMeasurechartabrv($productData['MeasureChartAbrv']['value'])
                    ->setBnpMeasurementchart($productItem['MeasurementChart']['value'])
                    ->setBnpProgram($externalIdToInternalId[$productData['Program']['value']])
                    ->setBnpProductmaingroup($externalIdToInternalId[$productData['ProductMainGroup']['value']])
                    ->setBnpProductgroup($externalIdToInternalId[$productData['ProductGroup']['value']])
                    ->setBnpProductsubgroup($externalIdToInternalId[$productData['ProductSubGroup']['value']])

                    ->setBnpComposition($externalIdToInternalId[$productData['Composition']['value']])
                    ->setBnpConcept($externalIdToInternalId[$productData['Concept']['value']])

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



               $this->_logMessage('Saving configurable product');
               try{
                    $cProduct->save();


                   // adding the images
                   $resourceList = array();

                   foreach ($productItem['Resources']['value'] as $resource){
                       $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
                       if (file_exists($picturePath) && ($resource['OriginalFilename']['value']!='')){
                           try {
                               $this->_addProductImage($cProduct->getId(),$resource['OriginalFilename']['value']);
//                              $cProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
                               $this->_logMessage('O', false);
                           } catch (Exception $e) {
                               echo $e->getMessage();
                           }
                       } else {
                           $this->_logMessage('X', false);
                       }
                       $resourceList[$resource['id']]=$resource['OriginalFilename']['value'];
                   }

                   // create cino pictures
                   $this->_logMessage('Creating lead pictures files ');
                   if (count($productItem['LeadPicture']['value'])==2){
                       $picture1Name = $resourceList[$productItem['LeadPicture']['value'][0]['id']];
                       $picture1Path = $pictureBasePath . $picture1Name;
                       $picture2Name = $resourceList[$productItem['LeadPicture']['value'][1]['id']];
                       $picture2Path = $pictureBasePath . $picture2Name;
                       // if first picture is Plus size it is copied to cino_p
                       if ($picture1Name[3]=='P'){
                           if (file_exists($picture1Path)) copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '_p.jpg');
                           if (file_exists($picture2Path)) copy($picture2Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                           $this->_logMessage('Pp', false);

                       }else{
                           if (file_exists($picture1Path)) copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                           if (file_exists($picture2Path)) copy($picture2Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '_p.jpg');
                           $this->_logMessage('Pp', false);

                       }

                   }elseif ($resourceList[$productItem['LeadPicture']['value'][0]['id']][3]=='P'){
                       $picture1Name = $resourceList[$productItem['LeadPicture']['value'][0]['id']];
                       $picture1Path = $pictureBasePath . $picture1Name;

                       if (file_exists($picture1Path)){
                           copy ($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                           copy ($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '_p.jpg');
                           $this->_logMessage('Pp', false);
                       }
                   }else{
                       $picture1Name = $resourceList[$productItem['LeadPicture']['value'][0]['id']];
                       $picture1Path = $pictureBasePath . $picture1Name;

                       if (file_exists($picture1Path)) {
                           copy ($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                           $this->_logMessage('P', false);
                       }
                   }

                   // end create cino picture


                   // adding the BNP 'style' images
                   foreach ($productData['Resources']['value'] as $resource){
                       $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
                       if (file_exists($picturePath) && ($resource['OriginalFilename']['value']!='')){
                           try {
                               $this->_addProductImage($cProduct->getId(),$resource['OriginalFilename']['value']);
//                              $cProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
                               $this->_logMessage('O', false);
                           } catch (Exception $e) {
                               echo $e->getMessage();
                           }
                       } else {
                           $this->_logMessage('X', false);
                       }
                   }

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
    public function start($options = array())
    {
//        $this->_logMessage('Starting product deleting');
//        $this->_deleteAllProducts();
//        exit;

        $this->_mediaGalleryId = $this->_getAttributeID('media_gallery');
        $this->_baseImageId = $this->_getAttributeID('image');
        $this->_smallImageId = $this->_getAttributeID('small_image');
        $this->_thumbnailId = $this->_getAttributeID('thumbnail');

//        $this->_logMessage('Getting the pictures ready');
//        $this->_getProductImageReady();
//        $this->_logMessage('Finished');

        $this->_logMessage('Inventory parsing start');
        $this->_productInventory = $this->_getProductInventory();
        $this->_logMessage('Inventory end');

        $this->_customSizes = $this->_getCustomSize();
        $this->_getAttributeSetID('Default');
        $this->_attributeIdd = $this->_getAttributeID('bnp_size');
        $this->_allWebsiteIDs = Mage::getModel('core/website')->getCollection()->getAllIds();
        $numberOfFiles = count($this->_data);
        $counter = 0;
        foreach($this->_data as $productConfig) {
//            if ($counter++<294) continue;
            $productData = array();
            $this->_extractConfiguration($productConfig->getNode(), $productData);
            $this->_logMessage($counter . ' / '. $numberOfFiles . ' - Adding product file');
            $this->_addProduct($productData);
            if ($counter==1000) break;
        }
        $this->_logMessage('ALL DONE!!!'. "\n");
    }

}