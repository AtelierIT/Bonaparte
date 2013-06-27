﻿<?php
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
    const CONFIGURATION_FILE_INVENTORY = '/dump_files/ARTIKLAR.TXT';
    /**
     * Path to
     *
     * @var string
     */
    const CONFIGURATION_FILE_STRUCTURE = '/dump_files/structure.xml';
    /**
     * Path to the product configuration XML files
     *
     * @var string
     */
//    const CONFIGURATION_FILE_PATH = '/chroot/home/stagebon/upload/xml/product';       // server configuration
    const CONFIGURATION_FILE_PATH = '/var/www/bonaparte/magento/dump_files/xml/test6'; // local developer station

    /**
     * Path to the product pictures source files
     *
     * @var string
     */
//    const PICTURE_BASE_PATH = '/chroot/home/stagebon/upload/pictures/';
    const PICTURE_BASE_PATH = '/var/www/bonaparte/magento/dump_files/pictures/';

    /**
     * Path to the missing pictures file
     *
     * @var string
     */
    const MISSING_PICTURES_BASE_PATH = '/dump_files/missing_pictures.csv';

    /**
     * Path to temporary import files
     *
     * @var string
     */
    const RESOURCES_BASE_PATH = '/dump_files/tmp_import_resources.csv';
    const STYLES_BASE_PATH = '/dump_files/tmp_import_styles.csv';

    /**
     * Path to the missing pictures file
     *
     * @var string
     */
    const SIZE_CONFIGURATION_PATH = '/dump_files/sizeConfiguration.properties';

    /**
     * Contains all sizes that need to be translated to short ERP name
     *
     * @var array
     */
    private $_customSizes = array();

    /**
     * Contains all size translations according to the category
     *
     * @var array
     */
    private $_sizeTranslate = array();

    /**
     * Contains all product QTYs, the keys are product SKUs
     *
     * @var array
     */
    private $_productInventory = array();

    /**
     * Contains the link between products and category tree, the keys are product BNP styles
     *
     * @var array
     */
    private $_productStructure = array();

    /**
     * Contains all active BNP catalogues
     *
     * @var array
     */
    private $_activeCatalogues = array();

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
    private $_descriptionId = 0;
    private $_shortDescriptionId = 0;
    private $_nameId = 0;
    private $_metaTitleId = 0;
    private $_metaDescriptionId = 0;



    /**
     * Contains all Websites IDs
     *
     * @var array
     */
    private $_allWebsiteIDs = array();

    private $_newProductCounter = 0;
    private $_productEntityTypeId = 0;
    private $_missingPictureFilePath = '';
    private $_fileHandlerPictures;
    private $_fileHandlerResources;
    private $_fileHandlerStyles;


    /**
     * Maps the website code the its store view id
     *
     * @var array
     */
    private $_websiteStoreView = array();


    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_logMessage('Start PRODUCT IMPORT');
        $this->_configurationFilePath = array();
        $configFilesPath = self::CONFIGURATION_FILE_PATH;
        $files = scandir($configFilesPath);
        $this->_logMessage('There are ' . (count($files) - 2) . 'files');
        foreach ($files as $fileName) {
            if (strlen($fileName) < 3) {
                continue;
            }
            $this->_configurationFilePath[] = $configFilesPath . '/' . $fileName;
        }
        unset($fileName);

        if (!is_array($this->_configurationFilePath)) {
            return parent::_initialize();
        }

        $limit = 5000; //change the limit if the number of files is greater than 5000
        $counter = 0;
        foreach ($this->_configurationFilePath as $filePath) {
            if ($counter == $limit) {
                break;
            }
            $this->_data[] = new Varien_Simplexml_Config($filePath);
            $counter++;
        }

        $this->_productEntityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        $this->_descriptionId = $this->_getAttributeID('description');
        $this->_shortDescriptionId = $this->_getAttributeID('short_description');
        $this->_nameId = $this->_getAttributeID('name');
        $this->_metaTitleId = $this->_getAttributeID('meta_title');
        $this->_metaDescriptionId = $this->_getAttributeID('meta_description');

        foreach(Mage::app()->getWebsites() as $website) {
            $this->_websiteStoreView[strtolower($website->getCode())] = array_pop($website->getStoreIds());
        }

        $productsStructure = new Varien_Simplexml_Config(Mage::getBaseDir() . self::CONFIGURATION_FILE_STRUCTURE);
        $this->_getProductFolder($productsStructure);
//        $this->_missingPictureFilePath = Mage::getBaseDir() . self::MISSING_PICTURES_BASE_PATH;
    }

    /**
     * Contruct array with links between the products and category tree
     *
     */
    public function _getProductFolder($node){

        if ($node instanceof Varien_Simplexml_Config) {
            $folder = $node->getNode('Folder');
        } else {
            $folder = $node->Folder;
        }

        if (empty($folder)) {
            return;
        }

        foreach ($folder as $node) {
            $categoryId = $node->getAttribute('groupId');
            $Products = (array)$node->Products;
            foreach ($Products['Product'] as $BNPstyle){
                $this->_productStructure[$BNPstyle->getAttribute('idRef')][]= $categoryId;
            }
            $this->_getProductFolder($node);
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
    public function _getAttributeLabelId($attributeCode, $label)
    {
        if (isset($this->_bnpAttributes[$attributeCode])) {
            return $this->_bnpAttributes[$attributeCode][$label];
        }

        $productModel = Mage::getModel('catalog/product');
        $attributeBnpCatalogue = $productModel->getResource()->getAttribute($attributeCode);

        foreach ($attributeBnpCatalogue->getSource()->getAllOptions() as $option) {
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
        $SetId  = intval(Mage::getModel('eav/entity_attribute_set')->getCollection()->setEntityTypeFilter($this->_productEntityTypeId)->addFieldToFilter('attribute_set_name', $label)->getFirstItem()->getAttributeSetId());
        $this->_attributeSetIdd = $SetId ;
    }

    /**
     * Get the attribute id
     *
     * @param $label - attributes label
     * @return integer
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
        while ($data_csv = fgetcsv($handle, null, ';', '"')) {
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
     * Get the product inventory from file
     *
     * @return array
     */
    public function _getSizeConfiguration()
    {
        $sizeConfig = array();
        $handle = fopen(Mage::getBaseDir() . self::SIZE_CONFIGURATION_PATH, 'r');
        while ($data_csv = fgets($handle)) {
            if (($data_csv[0]=='#') or (trim($data_csv)=='')) continue;
            $data_csv = explode('=', trim($data_csv));
            $sizesPairsExploded = explode('/', $data_csv[1]);
            foreach ($sizesPairsExploded as $sizePair){
                $explodedSizes = explode (',', $sizePair);
                $sizeConfig[str_replace('-','_',$data_csv[0])][$explodedSizes[0]]=$explodedSizes[1];
            }
        }
        fclose($handle);

        return $sizeConfig;
    }

    /**
     * Add attribute option
     */
    public function addAttributeOption($arg_attribute, $arg_value)
    {
        $attribute_model = Mage::getModel('eav/entity_attribute');
        $attribute_options_model = Mage::getModel('eav/entity_attribute_source_table');

        $attribute_code = $attribute_model->getIdByCode('catalog_product', $arg_attribute);
        $attribute = $attribute_model->load($attribute_code);

        $attribute_table = $attribute_options_model->setAttribute($attribute);
        $options = $attribute_options_model->getAllOptions(false);

        $value['option'] = array($arg_value, $arg_value);
        $result = array('value' => $value);
        $attribute->setData('option', $result);
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


        $images = scandir(self::PICTURE_BASE_PATH);
        $_mediaBase = Mage::getBaseDir('media') . '/catalog/product/';

        $pictureNumber = count($images);
        $this->_logMessage($pictureNumber . ' in ' . $_mediaBase);
        $counter = 1;
        foreach ($images as $image) {
            if (in_array($image, array('.', '..')))
                continue;

            $firstDir = $_mediaBase . $image[0];
            $secondDir = $firstDir . '/' . $image[1];
            $path = $secondDir . '/' . $image;

            if (!file_exists($path)) {
                if (!file_exists($secondDir)) mkdir($secondDir, 0775, true);

                $this->_logMessage('Creating ' . $counter++ . ' of ' . $pictureNumber . ' - from ' . self::PICTURE_BASE_PATH . $image . ' - to ' . $path);
                copy(self::PICTURE_BASE_PATH . $image, $path); // Stage version
                //copy(Mage::getBaseDir().self::PICTURE_BASE_PATH.$image, $path); //localhost version

            } else $this->_logMessage('Existing ' . $counter++ . ' of ' . $pictureNumber . ' - ' . $image);
        }


    }

    /**
     * Function build to replace the MAGENTO addImageToMediaGallery
     *
     */
    private function _addProductImage($productID, $pictureName, $isLeadPicture)
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $connW = Mage::getSingleton('core/resource')->getConnection('core_write');
        $pictureValueField = '/' . $pictureName[0] . '/' . $pictureName[1] . '/' . $pictureName;

        /*
           *    Check the existing images
           */

        $sql = "SELECT * FROM catalog_product_entity_media_gallery WHERE entity_id IN (" . $productID . ") AND value IN ('" . $pictureValueField . "');";
        $_galleryImgs = $conn->fetchAll($sql);

        if (!$_galleryImgs) {
            $sql = "INSERT INTO catalog_product_entity_media_gallery (attribute_id, entity_id, value) VALUES (" . $this->_mediaGalleryId . "," . $productID . ",'" . $pictureValueField . "');";
            $connW->query($sql);
        }


        if ($isLeadPicture){

            $sql = "DELETE FROM catalog_product_entity_varchar WHERE entity_id IN (" . $productID . ") AND attribute_id IN (" . $this->_baseImageId . "," . $this->_smallImageId . "," . $this->_thumbnailId . ");
                    INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_baseImageId . ",0," . $productID . ",'" . $pictureValueField . "'), (" . $this->_productEntityTypeId . "," . $this->_smallImageId . ",0," . $productID . ",'" . $pictureValueField . "'), (" . $this->_productEntityTypeId . "," . $this->_thumbnailId . ",0," . $productID . ",'" . $pictureValueField . "');";
            $connW->query($sql);

        }else{

            $sql = "SELECT * FROM catalog_product_entity_varchar WHERE entity_id IN (" . $productID . ") AND attribute_id IN (" . $this->_baseImageId . "," . $this->_smallImageId . "," . $this->_thumbnailId . ");";
            $_imageAssoc = $conn->fetchAll($sql);

            if (!$_imageAssoc) {
                $sql = "INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_baseImageId . ",0," . $productID . ",'" . $pictureValueField . "'), (" . $this->_productEntityTypeId . "," . $this->_smallImageId . ",0," . $productID . ",'" . $pictureValueField . "'), (" . $this->_productEntityTypeId . "," . $this->_thumbnailId . ",0," . $productID . ",'" . $pictureValueField . "');";
                $connW->query($sql);
            }
        }

    }

    /**
     * Function to return an array of active Catalogues
     *
     */
    private function _getActiveCatalogues()
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');

        $sql = "SELECT name FROM bonaparte_importexport_catalogue WHERE end_date is NULL;";
        $_activeCatalogues = $conn->fetchAll($sql);
        $_list = array();
        foreach ($_activeCatalogues as $catalog){
            $_list[] = $catalog[name];
        }
        return $_list;
    }


    /**
     * Function to delete all products
     */
    private function _deleteAllProducts()
    {
        $products = Mage::getResourceModel('catalog/product_collection')->getAllIds();
        foreach ($products as $key => $productId) {
            try {
                $product = Mage::getSingleton('catalog/product')->load($productId);
                Mage::dispatchEvent('catalog_controller_product_delete', array('product' => $product));
                $this->_logMessage('Deleting product id' . $productId);
                $product->delete();
            } catch (Exception $e) {
                $this->_logMessage('Could not delete product id' . $productId);
            }
        }
    }

    /**
     * Add new option to the attribute options and return the id
     *
     * @param array $attributeData
     *
     * @return integer
     */
    private function _addAttributeOptionsForBnpStylenbr($attributeData) {
        $model = Mage::getModel('catalog/resource_eav_attribute')->load('bnp_stylenbr', 'attribute_code');

        $options = $model->getSource()->getAllOptions(false);
        $optionLabels = array();
        foreach($options as $option) {
            $optionLabels[$option['label']] = true;
        }
        unset($option);

        if(!isset($optionLabels[$attributeData['option']['value']['option0'][0]])) {
            $model->addData($attributeData);
            $model->save();
        }

        $options = $model->getSource()->getAllOptions(false);
        foreach($options as $option) {
            if($option['label'] == $attributeData['option']['value']['option0'][0]) {
                $attributeOptionId = $option['value'];
                break;
            }
        }

        return $attributeOptionId;
    }

    /**
     * Function build to add product to database
     *
     * @param array $productData
     */
    private function _addProduct($productData)
    {
        $connW = Mage::getSingleton('core/resource')->getConnection('core_write');
        $configurable_attribute = "bnp_size";
        $attr_id = $this->_attributeIdd;
        $cino_picture_directory = Mage::getBaseDir('media') . '/cino/';

        $pictureBasePath = self::PICTURE_BASE_PATH;

        //$mediaAttributes = array('image','thumbnail','small_image');

        $this->_logMessage('Editing ' . count($productData['Items']['value']) . ' from this file');
        $productCounter = 1;
        foreach ($productData['Items']['value'] as $productItem) {

            $simpleProducts = array();
            $this->_logMessage($productCounter++ . ' Configuring product details ...');

            $productSizes = array();

            // first step is to check if the size is custom or not
            //if (in_array($productItem['Sizess']['value']['en'], array('50X100', '70X140', 'ne size', 'one size', 'One size', 'One Size', 'ONE SIZE', 'onesize', 'Onesize', 'ONESIZE'))) {
            //    $productSizes = array('cst_' . $productItem['Sizess']['value']['en']);
            //} elseif (!$this->_customSizes[$productItem['Sizess']['value']['en']]) {
            //    $productItemSizess = $productItem['Sizess']['value']['en'] == $productItem['Sizess']['value']['de'] ? $productItem['Sizess']['value']['en'] : $productItem['Sizess']['value']['en'] . '-' . $productItem['Sizess']['value']['de'];
            //    $productSizesTemp = explode("-", $productItemSizess);
            //    foreach ($productSizesTemp as $productSizeTemp)
            //        if (!$this->_customSizes[$productSizeTemp]) {
            //            $productSizes[] = $productSizeTemp;
            //        } else {
            //            $productSizes[] = $this->_customSizes[$productSizeTemp];
            //        }
            //
            //} else {
            //    $productSizes = array($this->_customSizes[$productItem['Sizess']['value']['en']]);
            //}

            //take the sizes from the <Price> tag
            //foreach ($productItem['Prices']['value']['Catalogue'][0]['value'] as $productSizeTemp => $sizeCountry)
            //    if (!$this->_customSizes[$productSizeTemp]) {
            //        $productSizes[] = $productSizeTemp;
            //    } else {
            //        if (in_array($productSizeTemp, array('50X100', '70X140', 'ne size', 'one size', 'One size', 'One Size', 'ONE SIZE', 'onesize', 'Onesize', 'ONESIZE'))) {
            //               $productSizes = array('cst_' . $productSizeTemp);
            //        } else {
            //           $productSizes[] = $this->_customSizes[$productSizeTemp];
            //        }
            //    }

            //all products have EU sizes and frontend custom size
            if (in_array($productItem['Sizess']['value']['da'], array('50X100', '70X140', 'ne size', 'one size', 'One size', 'One Size', 'ONE SIZE', 'onesize', 'Onesize', 'ONESIZE'))) {
                    $productSizes = array('cst_' . $productItem['Sizess']['value']['da']);
                } elseif (!$this->_customSizes[$productItem['Sizess']['value']['da']]) {
                    $productItemSizess = $productItem['Sizess']['value']['da'];
                    $productSizesTemp = explode("-", $productItemSizess);
                    foreach ($productSizesTemp as $productSizeTemp)
                        if (!$this->_customSizes[$productSizeTemp]) {
                            $productSizes[] = $productSizeTemp;
                        } else {
                            $productSizes[] = $this->_customSizes[$productSizeTemp];
                        }

                } else {
                    $productSizes = array($this->_customSizes[$productItem['Sizess']['value']['da']]);
            }





            //$justUK = 0;
            //if ($productItem['Sizess']['value']['en'] != $productItem['Sizess']['value']['de']) $justUK = 1;

            $this->_logMessage('Editing ' . count($productSizes) . ' simple products');
            $productOneSize = (count($productSizes) == 1)? 1 : 0;

            $sizeCounter = 0;
            $simpleProductEntityIds = array();

            foreach ($productSizes as $productSize) {
                $this->_logMessage('.', false);
                $sizeCounter++;
                $attr_value = $productSize;
                $configurableAttributeOptionId = $this->_getAttributeLabelId($configurable_attribute, $productSize);
                if (!$configurableAttributeOptionId) {
                    $configurableAttributeOptionId = $this->addAttributeOption($configurable_attribute, $attr_value);
                }

                //create each simple product
                $category_ids = array();
                $category_idss = array();

                // $prefix_main_group = "";
                // $prefix_sub_group = "";
                // if ($productData['Program']['value'] != '') $category_ids[] = $productData['Program']['value'];
                // if ($productData['ProductMainGroup']['value'] != '') {
                //     $prefix_main_group = $productData['Program']['value'] ? $productData['Program']['value'] . "_" : "";
                //     $category_ids[] = $prefix_main_group . $productData['ProductMainGroup']['value']; //tmunteanu add Program to product main group. Ex: M_001 where M = Program and 001 = Main Group
                //     $prefix_sub_group = $prefix_main_group . $productData['ProductMainGroup']['value'] . "_";
                // }
                // if ($productData['ProductGroup']['value'] != '') $category_ids[] = $prefix_sub_group . $productData['ProductGroup']['value'];

                $category_ids = $this->_productStructure[$productData['StyleNbr']['value']];
                foreach ($category_ids as $category_id) {
                    $category = Mage::getModel('catalog/category')->getCollection()->addAttributeToFilter('old_id', $category_id)->load();
                    foreach ($category->getAllIds() as $idss) $category_idss [] = $idss;

                }

                $productShortDescription = explode(".", $productData['DescriptionCatalogues']['value']['en']);

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
                    (array)$productData['Catalogue']['value'],
                    (array)$productData['Season']['value'],
                    (array)$productItem['WashIcon']['value']
                );

                foreach ($externalIds as $key => $value) {
                    if (empty($value)) {
                        unset($externalIds[$key]);
                    } else {
                        $externalIds[$key] = (string)$externalIds[$key];
                    }
                }
                $collection = Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option')
                    ->getCollection()
                    ->addFieldToFilter('external_id', array('in' => $externalIds))
                    ->addFieldToFilter('attribute_code', array('in' => array(
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_CATALOGUE,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_COLOR,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_COMPOSITION,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_CONCEPT,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_FITTING,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_GROUP,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_MAIN_GROUP,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PROGRAM,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_SEASON,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_SUB_GROUP,
                        Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_WASH_ICON
                    )))
                    ->load();

                $externalIdToInternalId = array();
                foreach ($collection as $relation) {
                    $externalIdToInternalId[$relation->getExternalId() . '_' . $relation->getAttributeCode()] = $relation->getInternalId();
                }
                // END external id relate to internal id

                $bnpCatalogueLabelIds = array();
                foreach ($productData['Catalogue']['value'] as $externalId) {
                    $bnpCatalogueLabelIds[] = $externalIdToInternalId[$externalId . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_CATALOGUE];
                }
                $bnpSeasonLabelIds = array();
                foreach ($productData['Season']['value'] as $externalId) {
                    $bnpSeasonLabelIds[] = $externalIdToInternalId[$externalId . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_SEASON];
                }
                $bnpWashiconLabelIds = array();
                foreach ($productItem['WashIcon']['value'] as $externalId) {
                    $bnpWashiconLabelIds[] = $externalIdToInternalId[$externalId . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_WASH_ICON];
                }

                $productSKU = $productOneSize ? $productItem['CinoNumber']['value'] : $productItem['CinoNumber']['value'] . '-' . $productSize;

               //check if the product exists in magento then get product and update else create product
                //  $sProduct = Mage::getModel('catalog/product')->loadByAttribute('sku',$productSKU);

                $sProduct = Mage::getModel('catalog/product');
                $productId = Mage::getModel('catalog/product')->getIdBySku($productSKU);
                if ($productId) {
                    $sProduct->load($productId);
                }else{
                    $sProduct = Mage::getModel('catalog/product');
                    $sProduct
                        ->setSku($productSKU)
                        ->setAttributeSetId($this->_attributeSetIdd)
						->setPrice("1000.00")
						->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
						->setTaxClassId(0) //none
						->setWeight(1)
                        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
                    $this->_newProductCounter++;
                    $productQTY = (!is_null($this->_productInventory[$productSKU])) ? $this->_productInventory[$productSKU] : "0";

                    $sProduct->setStockData(array(
                        'is_in_stock' => (($productQTY > 0) ? 1 : 0),
                        'qty' => $productQTY
                    ));
                };


                //switch (count($productItem['Prices']['value']['Catalogue'][0]['value'][$productSize])) {
                //    case '6':
                //        $sProduct -> setWebsiteIds($this->_allWebsiteIDs);
                 //       break;
                //    case '5':
                //        $sProduct -> setWebsiteIds(array($this->_allWebsiteIDs['base'],$this->_allWebsiteIDs['dk'],$this->_allWebsiteIDs['ch'],$this->_allWebsiteIDs['de'],$this->_allWebsiteIDs['nl'],$this->_allWebsiteIDs['se']));
                //        break;
                 //   case '1':
                 //       $sProduct -> setWebsiteIds(array($this->_allWebsiteIDs['base'],$this->_allWebsiteIDs['uk']));
                 //       break;
                //}

                //if (!$justUK) {....
                //    $sProduct -> setWebsiteIds($this->_allWebsiteIDs);
                //}elseif($sizeCounter<=($productSizes)/2)){
                //    $sProduct -> setWebsiteIds(array($this->_allWebsiteIDs['base'],$this->_allWebsiteIDs['uk']));
                //}else{
                //    $sProduct -> setWebsiteIds(array($this->_allWebsiteIDs['base'],$this->_allWebsiteIDs['dk'],$this->_allWebsiteIDs['ch'],$this->_allWebsiteIDs['de'],$this->_allWebsiteIDs['nl'],$this->_allWebsiteIDs['se']));
                //}

                $sProduct -> setWebsiteIds($this->_allWebsiteIDs);

                //UK size translation
                $sProduct -> setBnpSizetranslate($productSize);
                foreach ($category_ids as $category_id){
                    if ($this->_sizeTranslate[$category_id]){
                        $sProduct -> setBnpSizetranslate($this->_sizeTranslate[$category_id][$productSize]);
                    }
                }

                // add stylenbr to select options
                $bnpStylenbrAttributeOptionId = $this->_addAttributeOptionsForBnpStylenbr(array(
                    'option' => array(
                        'value' => array(
                            'option0' => array(
                                0 => $productData['StyleNbr']['value']
                            )
                        )
                    )
                ));

                $sProduct
                    ->setName($productData['HeaderWebs']['value']['en'])
                    ->setDescription($productData['DescriptionCatalogues']['value']['en'])
                    ->setShortDescription($productShortDescription[0] . '.')

                    ->setMetaTitle($productData['HeaderWebs']['value']['en'])
                    ->setMetaKeywords('')
                    ->setMetaDescription($productData['DescriptionCatalogues']['value']['en'])

                    ->setCategoryIds($category_idss)

                    //->setBnpStylenbr($bnpStylenbrAttributeOptionId)
                    ->setBnpColor($externalIdToInternalId[$productItem['Color']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_COLOR])
                    ->setBnpFitting($externalIdToInternalId[$productData['Fitting']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_FITTING])
                    ->setBnpCatalogue(implode(',', $bnpCatalogueLabelIds))
                    ->setBnpSeason(implode(',', $bnpSeasonLabelIds))
                    ->setBnpWashicon(implode(',', $bnpWashiconLabelIds))
                    ->setBnpColorgroup($this->_getAttributeLabelId("bnp_colorgroup", $productItem['ColorGroup']['value']))
                    ->setBnpMeasurechartabrv($productData['MeasureChartAbrv']['value'])
                    ->setBnpMeasurementchart($productItem['MeasurementChart']['value'])
                    ->setBnpProgram($externalIdToInternalId[$productData['Program']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PROGRAM])
                    ->setBnpProductmaingroup($externalIdToInternalId[$productData['ProductMainGroup']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_MAIN_GROUP])
                    ->setBnpProductgroup($externalIdToInternalId[$productData['ProductGroup']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_GROUP])
                    ->setBnpProductsubgroup($externalIdToInternalId[$productData['ProductSubGroup']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_SUB_GROUP])
                    ->setBnpComposition($externalIdToInternalId[$productData['Composition']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_COMPOSITION])
                    ->setBnpConcept($externalIdToInternalId[$productData['Concept']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_CONCEPT])


                    ->setUrlKey($productData['HeaderWebs']['value']['en'] . '_' . $productItem['CinoNumber']['value'] . '_' . $productSize)

                    ->setData($configurable_attribute, $configurableAttributeOptionId);



                try {
                    $sProduct->save();
                    // saving some data for configurable product creation
                    $sProductId = $sProduct->getId();
                    array_push(
                        $simpleProducts,
                        array(
                            "id" => $sProductId,
                            "price" => $sProduct->getPrice(),
                            "attr_code" => $configurable_attribute,
                            "attr_id" => $attr_id,
                            "value" => $configurableAttributeOptionId,
                            "label" => $attr_value
                        )
                    );
                    $simpleProductEntityIds[] = $sProductId;

                    // adding the item images

                    foreach ($productItem['Resources']['value'] as $resource) {
                        $isLeadPicture = 0;
                        if (count($productItem['LeadPicture']['value'])==2 && $resource['ImageType']['value']=="packshots")
                        {
                            if ($productItem['LeadPicture']['value'][0]['id']==$resource['id'])
                                {
                                $isLeadPicture = 1;
                            } elseif ($productItem['LeadPicture']['value'][1]['id']==$resource['id']) {
                                $isLeadPicture = 1;
                            }
                        }elseif (count($productItem['LeadPicture']['value'])==1 && $productItem['LeadPicture']['value'][0]['id']==$resource['id']) $isLeadPicture = 1;
                        $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
                        if (file_exists($picturePath) && ($resource['OriginalFilename']['value'] != '')) {
                            try {
                                $this->_addProductImage($sProductId, $resource['OriginalFilename']['value'], $isLeadPicture);
                                $this->_logMessage('O', false);
                                fputcsv($this->_fileHandlerResources,array($sProductId, $resource['ResourceFileId']['value'], $resource['OriginalFilename']['value'], 0,$resource['ImageType']['value'], $isLeadPicture));

                            } catch (Exception $e) {
                                echo $e->getMessage();
                            }
                        } else {
                            $this->_logMessage('X', false);
                        }
                    }
                    // adding the BNP 'style' images
//                    foreach ($productData['Resources']['value'] as $resource) {
//                        $isLeadPicture = 0;
//                        if ($productItem['LeadPicture']['value'][0]['id']==$resource['id']) $isLeadPicture = 1;
//                        $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
//                        if (file_exists($picturePath) && ($resource['OriginalFilename']['value'] != '')) {
//                            try {
//                                $this->_addProductImage($sProductId, $resource['OriginalFilename']['value'], $isLeadPicture);
//                                $this->_logMessage('O', false);
//                            } catch (Exception $e) {
//                                echo $e->getMessage();
//                            }
//                        } else {
//                            $this->_logMessage('X', false);
//                        }
//                    }

                    // adding the different attribute values per store view
                    $productShortDescriptionn = array();
                    foreach ($productData['DescriptionCatalogues']['value'] as $key => $description){
                        $temp = explode('.',$description);
                        $productShortDescriptionn [$key] = $temp[0].'.';
                    }
                    $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:short_descr_id,:store_id,:entity_id,:short_description)ON DUPLICATE KEY UPDATE `value` = :short_description;
                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:descr_id,:store_id,:entity_id,:description)ON DUPLICATE KEY UPDATE `value` = :description;
                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:meta_descr_id,:store_id,:entity_id,:meta_description)ON DUPLICATE KEY UPDATE `value` = :meta_description;
                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:meta_title_id,:store_id,:entity_id,:meta_title)ON DUPLICATE KEY UPDATE `value` = :meta_title;
                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:name_id,:store_id,:entity_id,:short_description)ON DUPLICATE KEY UPDATE `value` = :name;
                            ";
                    $binds = array(
                        'entity_type_id'    => $this->_productEntityTypeId,
                        'short_descr_id'    => $this->_shortDescriptionId,
                        'descr_id'          => $this->_descriptionId,
                        'meta_descr_id'     => $this->_metaDescriptionId,
                        'meta_title_id'     => $this->_metaTitleId,
                        'name_id'           => $this->_nameId,
                        'store_id'          => $this->_websiteStoreView['uk'],
                        'entity_id'         => $sProductId,
                        'short_description' => $productShortDescriptionn['en'],
                        'description'       => $productData['DescriptionCatalogues']['value']['en'],
                        'meta_description'  => $productShortDescriptionn['en'],
                        'meta_title'        => $productData['HeaderWebs']['value']['en'],
                        'name'              => $productData['HeaderWebs']['value']['en'],
                    );
                    $connW->query($sql, $binds);

                    $binds = array(
                        'entity_type_id'    => $this->_productEntityTypeId,
                        'short_descr_id'    => $this->_shortDescriptionId,
                        'descr_id'          => $this->_descriptionId,
                        'meta_descr_id'     => $this->_metaDescriptionId,
                        'meta_title_id'     => $this->_metaTitleId,
                        'name_id'           => $this->_nameId,
                        'store_id'          => $this->_websiteStoreView['dk'],
                        'entity_id'         => $sProductId,
                        'short_description' => $productShortDescriptionn['da'],
                        'description'       => $productData['DescriptionCatalogues']['value']['da'],
                        'meta_description'  => $productShortDescriptionn['da'],
                        'meta_title'        => $productData['HeaderWebs']['value']['da'],
                        'name'              => $productData['HeaderWebs']['value']['da'],
                    );
                    $connW->query($sql, $binds);

                    $binds = array(
                        'entity_type_id'    => $this->_productEntityTypeId,
                        'short_descr_id'    => $this->_shortDescriptionId,
                        'descr_id'          => $this->_descriptionId,
                        'meta_descr_id'     => $this->_metaDescriptionId,
                        'meta_title_id'     => $this->_metaTitleId,
                        'name_id'           => $this->_nameId,
                        'store_id'          => $this->_websiteStoreView['ch'],
                        'entity_id'         => $sProductId,
                        'short_description' => $productShortDescriptionn['de_CH'],
                        'description'       => $productData['DescriptionCatalogues']['value']['de_CH'],
                        'meta_description'  => $productShortDescriptionn['de_CH'],
                        'meta_title'        => $productData['HeaderWebs']['value']['de_CH'],
                        'name'              => $productData['HeaderWebs']['value']['de_CH'],
                    );
                    $connW->query($sql, $binds);

                    $binds = array(
                        'entity_type_id'    => $this->_productEntityTypeId,
                        'short_descr_id'    => $this->_shortDescriptionId,
                        'descr_id'          => $this->_descriptionId,
                        'meta_descr_id'     => $this->_metaDescriptionId,
                        'meta_title_id'     => $this->_metaTitleId,
                        'name_id'           => $this->_nameId,
                        'store_id'          => $this->_websiteStoreView['de'],
                        'entity_id'         => $sProductId,
                        'short_description' => $productShortDescriptionn['de'],
                        'description'       => $productData['DescriptionCatalogues']['value']['de'],
                        'meta_description'  => $productShortDescriptionn['de'],
                        'meta_title'        => $productData['HeaderWebs']['value']['de'],
                        'name'              => $productData['HeaderWebs']['value']['de'],
                    );
                    $connW->query($sql, $binds);

                    $binds = array(
                        'entity_type_id'    => $this->_productEntityTypeId,
                        'short_descr_id'    => $this->_shortDescriptionId,
                        'descr_id'          => $this->_descriptionId,
                        'meta_descr_id'     => $this->_metaDescriptionId,
                        'meta_title_id'     => $this->_metaTitleId,
                        'name_id'           => $this->_nameId,
                        'store_id'          => $this->_websiteStoreView['nl'],
                        'entity_id'         => $sProductId,
                        'short_description' => $productShortDescriptionn['nl'],
                        'description'       => $productData['DescriptionCatalogues']['value']['nl'],
                        'meta_description'  => $productShortDescriptionn['nl'],
                        'meta_title'        => $productData['HeaderWebs']['value']['nl'],
                        'name'              => $productData['HeaderWebs']['value']['nl'],
                    );
                    $connW->query($sql, $binds);

                    $binds = array(
                        'entity_type_id'    => $this->_productEntityTypeId,
                        'short_descr_id'    => $this->_shortDescriptionId,
                        'descr_id'          => $this->_descriptionId,
                        'meta_descr_id'     => $this->_metaDescriptionId,
                        'meta_title_id'     => $this->_metaTitleId,
                        'name_id'           => $this->_nameId,
                        'store_id'          => $this->_websiteStoreView['se'],
                        'entity_id'         => $sProductId,
                        'short_description' => $productShortDescriptionn['se'],
                        'description'       => $productData['DescriptionCatalogues']['value']['se'],
                        'meta_description'  => $productShortDescriptionn['se'],
                        'meta_title'        => $productData['HeaderWebs']['value']['se'],
                        'name'              => $productData['HeaderWebs']['value']['se'],
                    );
                    $connW->query($sql, $binds);

                    $sProduct->clearInstance();

                } catch (Exception $e) {
                    echo "item " . $productData['HeaderWebs']['value']['en'] . " not added\n";
                    echo "exception:$e";
                }


            }

            // create the configurable product


            //check if the product exists in magento then get product and update else create product

            $cProduct = Mage::getModel('catalog/product');
            $productId = Mage::getModel('catalog/product')->getIdBySku($productItem['CinoNumber']['value'] . 'c');
            if ($productId) {
                $cProduct->load($productId);
            }else{
                $cProduct
                    ->setSku($productItem['CinoNumber']['value'] . 'c')
                    ->setAttributeSetId($this->_attributeSetIdd)
                    ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                    ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
					->setTaxClassId(0)
					->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
					->setWebsiteIds($this->_allWebsiteIDs)
                	->setPrice("1000.00");
                $this->_newProductCounter++;
                $cProduct->setCanSaveConfigurableAttributes(true);
                $cProduct->setCanSaveCustomOptions(true);

                $cProductTypeInstance = $cProduct->getTypeInstance();

                $cProductTypeInstance->setUsedProductAttributeIds(array($attr_id));
                $attributes_array = $cProductTypeInstance->getConfigurableAttributesAsArray();

                foreach ($attributes_array as $key => $attribute_array) {
                    $attributes_array[$key]['use_default'] = 1;
                    $attributes_array[$key]['position'] = 0;

                    if (isset($attribute_array['frontend_label'])) {
                        $attributes_array[$key]['label'] = $attribute_array['frontend_label'];
                    } else {
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
            };

            $cProduct

                ->setName($productData['HeaderWebs']['value']['en'])
                ->setDescription($productData['DescriptionCatalogues']['value']['en'])
                ->setShortDescription($productShortDescription[0] . '.')

                ->setMetaTitle($productData['HeaderWebs']['value']['en'])
                ->setMetaKeywords('')
                ->setMetaDescription($productData['DescriptionCatalogues']['value']['en'])

                ->setCategoryIds($category_idss)

                ->setBnpColor($externalIdToInternalId[$productItem['Color']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_COLOR])
                ->setBnpFitting($externalIdToInternalId[$productData['Fitting']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_FITTING])
                ->setBnpCatalogue(implode(',', $bnpCatalogueLabelIds))
                ->setBnpSeason(implode(',', $bnpSeasonLabelIds))
                ->setBnpWashicon(implode(',', $bnpWashiconLabelIds))
                ->setBnpColorgroup($this->_getAttributeLabelId("bnp_colorgroup", $productItem['ColorGroup']['value']))
                ->setBnpMeasurechartabrv($productData['MeasureChartAbrv']['value'])
                ->setBnpMeasurementchart($productItem['MeasurementChart']['value'])
                ->setBnpProgram($externalIdToInternalId[$productData['Program']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PROGRAM])
                ->setBnpProductmaingroup($externalIdToInternalId[$productData['ProductMainGroup']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_MAIN_GROUP])
                ->setBnpProductgroup($externalIdToInternalId[$productData['ProductGroup']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_GROUP])
                ->setBnpProductsubgroup($externalIdToInternalId[$productData['ProductSubGroup']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_PRODUCT_SUB_GROUP])
                ->setBnpComposition($externalIdToInternalId[$productData['Composition']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_COMPOSITION])
                ->setBnpConcept($externalIdToInternalId[$productData['Concept']['value'] . '_' . Bonaparte_ImportExport_Model_Custom_Import_Attributes::CUSTOM_ATTRIBUTE_CODE_CONCEPT])

                ->setUrlKey($productData['HeaderWebs']['value']['en'] . '_' . $productItem['CinoNumber']['value']);



            $this->_logMessage('Saving configurable product');
            try {
                $cProduct->save();
                $cProductId = $cProduct->getId();


                foreach ($simpleProductEntityIds as $simpleProductEntityId){
                    fputcsv($this->_fileHandlerStyles, array($productData['StyleNbr']['value'],$cProductId,$simpleProductEntityId));
                }

                // adding the images
                $resourceList = array();


                foreach ($productItem['Resources']['value'] as $resource) {
                    $isLeadPicture = 0;
                    if (count($productItem['LeadPicture']['value'])==2 && $resource['ImageType']['value']=="packshots")
                    {
                        if ($productItem['LeadPicture']['value'][0]['id']==$resource['id'])
                        {
                            $isLeadPicture = 1;
                        } elseif ($productItem['LeadPicture']['value'][1]['id']==$resource['id']) {
                            $isLeadPicture = 1;
                        }
                    }elseif (count($productItem['LeadPicture']['value'])==1 && $productItem['LeadPicture']['value'][0]['id']==$resource['id']) $isLeadPicture = 1;
                    $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
                    if (file_exists($picturePath) && ($resource['OriginalFilename']['value'] != '')) {
                        try {
                            $this->_addProductImage($cProductId, $resource['OriginalFilename']['value'], $isLeadPicture);
                            $this->_logMessage('O', false);
                            fputcsv($this->_fileHandlerResources,array($cProductId, $resource['ResourceFileId']['value'], $resource['OriginalFilename']['value'], 1,$resource['ImageType']['value'], $isLeadPicture));

                        } catch (Exception $e) {
                            echo $e->getMessage();
                        }
                    } else {
                        $this->_logMessage('X', false);
                        fputcsv($this->_fileHandlerPictures,array($productData['StyleNbr']['value'],$productItem['CinoNumber']['value'],$resource['OriginalFilename']['value']?$resource['OriginalFilename']['value']:'empty OriginalFilename tag'));
                    }
                    $resourceList[$resource['id']] = $resource['OriginalFilename']['value'];
                }



//                foreach ($productItem['Resources']['value'] as $resource) {
//                    $isLeadPicture = 0;
//                    if ($productItem['LeadPicture']['value'][0]['id']==$resource['id']) $isLeadPicture = 1;
//                    $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
//                    if (file_exists($picturePath) && ($resource['OriginalFilename']['value'] != '')) {
//                        try {
//                            $this->_addProductImage($cProductId, $resource['OriginalFilename']['value'], $isLeadPicture);
////                              $cProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
//                            $this->_logMessage('O', false);
//                        } catch (Exception $e) {
//                            echo $e->getMessage();
//                        }
//                    } else {
//                        $this->_logMessage('X', false);
//                        fputcsv($this->_fileHandlerPictures,array($productData['StyleNbr']['value'],$productItem['CinoNumber']['value'],$resource['OriginalFilename']['value']?$resource['OriginalFilename']['value']:'empty OriginalFilename tag'));
//                    }
//                    $resourceList[$resource['id']] = $resource['OriginalFilename']['value'];
//                }

                // create cino pictures
                $this->_logMessage('Creating lead pictures files ');
                if (count($productItem['LeadPicture']['value']) == 2) {
                    $picture1Name = $resourceList[$productItem['LeadPicture']['value'][0]['id']];
                    $picture1Path = $pictureBasePath . $picture1Name;
                    $picture2Name = $resourceList[$productItem['LeadPicture']['value'][1]['id']];
                    $picture2Path = $pictureBasePath . $picture2Name;
                    // if first picture is Plus size it is copied to cino_p
                    if ($picture1Name[3] == 'P') {
                        if (file_exists($picture1Path)) copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '_p.jpg');
                        if (file_exists($picture2Path)) copy($picture2Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                        $this->_logMessage('Pp', false);

                    } else {
                        if (file_exists($picture1Path)) copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                        if (file_exists($picture2Path)) copy($picture2Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '_p.jpg');
                        $this->_logMessage('Pp', false);

                    }

                } elseif ($resourceList[$productItem['LeadPicture']['value'][0]['id']][3] == 'P') {
                    $picture1Name = $resourceList[$productItem['LeadPicture']['value'][0]['id']];
                    $picture1Path = $pictureBasePath . $picture1Name;

                    if (file_exists($picture1Path)) {
                        copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                        copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '_p.jpg');
                        $this->_logMessage('Pp', false);
                    }
                } else {
                    $picture1Name = $resourceList[$productItem['LeadPicture']['value'][0]['id']];
                    $picture1Path = $pictureBasePath . $picture1Name;

                    if (file_exists($picture1Path)) {
                        copy($picture1Path, $cino_picture_directory . $productItem['CinoNumber']['value'] . '.jpg');
                        $this->_logMessage('P', false);
                    }
                }

                // end create cino picture


                // adding the BNP 'style' images
//                foreach ($productData['Resources']['value'] as $resource) {
//                    $picturePath = $pictureBasePath . $resource['OriginalFilename']['value'];
//                    if (file_exists($picturePath) && ($resource['OriginalFilename']['value'] != '')) {
//                        try {
//                            $this->_addProductImage($cProductId, $resource['OriginalFilename']['value'], 0);
////                              $cProduct->addImageToMediaGallery($picturePath,$mediaAttributes, false, false);
//                            $this->_logMessage('O', false);
//                        } catch (Exception $e) {
//                            echo $e->getMessage();
//                        }
//                    } else {
//                        $this->_logMessage('X', false);
//                    }
//                }


                // adding the different attribute values per store view
                $productShortDescriptionn = array();
                foreach ($productData['DescriptionCatalogues']['value'] as $key => $description){
                    $temp = explode('.',$description);
                    $productShortDescriptionn [$key] = $temp[0].'.';
                }

//                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_shortDescriptionId . "," . $this->_websiteStoreView['uk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['en']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['en']) ."';
//                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_descriptionId . "," . $this->_websiteStoreView['uk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['DescriptionCatalogues']['value']['en']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['DescriptionCatalogues']['value']['en']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaDescriptionId . "," . $this->_websiteStoreView['uk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['en']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['en']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaTitleId . "," . $this->_websiteStoreView['uk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['en']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['en']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_nameId . "," . $this->_websiteStoreView['uk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['en']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['en']) ."';
//                            ";
//                $connW->query($sql);
//
//                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_shortDescriptionId . "," . $this->_websiteStoreView['dk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['da']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['da']) ."';
//                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_descriptionId . "," . $this->_websiteStoreView['dk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['DescriptionCatalogues']['value']['da']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['DescriptionCatalogues']['value']['da']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaDescriptionId . "," . $this->_websiteStoreView['dk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['da']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['da']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaTitleId . "," . $this->_websiteStoreView['dk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['da']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['da']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_nameId . "," . $this->_websiteStoreView['dk'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['da']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['da']) ."';
//                            ";
//                $connW->query($sql);
//
//                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_shortDescriptionId . "," . $this->_websiteStoreView['ch'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['de_CH']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['de_CH']) ."';
//                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_descriptionId . "," . $this->_websiteStoreView['ch'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['DescriptionCatalogues']['value']['de_CH']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['DescriptionCatalogues']['value']['de_CH']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaDescriptionId . "," . $this->_websiteStoreView['ch'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['de_CH']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['de_CH']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaTitleId . "," . $this->_websiteStoreView['ch'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['de_CH']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['de_CH']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_nameId . "," . $this->_websiteStoreView['ch'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['de_CH']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['de_CH']) ."';
//                            ";
//                $connW->query($sql);
//
//                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_shortDescriptionId . "," . $this->_websiteStoreView['de'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['de']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['de']) ."';
//                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_descriptionId . "," . $this->_websiteStoreView['de'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['DescriptionCatalogues']['value']['de']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['DescriptionCatalogues']['value']['de']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaDescriptionId . "," . $this->_websiteStoreView['de'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['de']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['de']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaTitleId . "," . $this->_websiteStoreView['de'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['de']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['de']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_nameId . "," . $this->_websiteStoreView['de'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['de']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['de']) ."';
//                            ";
//                $connW->query($sql);
//
//                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_shortDescriptionId . "," . $this->_websiteStoreView['nl'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['nl']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['nl']) ."';
//                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_descriptionId . "," . $this->_websiteStoreView['nl'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['DescriptionCatalogues']['value']['nl']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['DescriptionCatalogues']['value']['nl']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaDescriptionId . "," . $this->_websiteStoreView['nl'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['nl']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['nl']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaTitleId . "," . $this->_websiteStoreView['nl'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['nl']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['nl']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_nameId . "," . $this->_websiteStoreView['nl'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['nl']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['nl']) ."';
//                            ";
//                $connW->query($sql);
//
//                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_shortDescriptionId . "," . $this->_websiteStoreView['se'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['sv']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['sv']) ."';
//                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_descriptionId . "," . $this->_websiteStoreView['se'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['DescriptionCatalogues']['value']['sv']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['DescriptionCatalogues']['value']['sv']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaDescriptionId . "," . $this->_websiteStoreView['se'] . "," . $cProductId . ",'" . mysql_real_escape_string($productShortDescriptionn['sv']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productShortDescriptionn['sv']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_metaTitleId . "," . $this->_websiteStoreView['se'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['sv']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['sv']) ."';
//                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (" . $this->_productEntityTypeId . "," . $this->_nameId . "," . $this->_websiteStoreView['se'] . "," . $cProductId . ",'" . mysql_real_escape_string($productData['HeaderWebs']['value']['sv']) . "')ON DUPLICATE KEY UPDATE `value` = '". mysql_real_escape_string($productData['HeaderWebs']['value']['sv']) ."';
//
//                    ";
//                $connW->query($sql);

                $sql = "INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:short_descr_id,:store_id,:entity_id,:short_description)ON DUPLICATE KEY UPDATE `value` = :short_description;
                            INSERT INTO catalog_product_entity_text (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:descr_id,:store_id,:entity_id,:description)ON DUPLICATE KEY UPDATE `value` = :description;
                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:meta_descr_id,:store_id,:entity_id,:meta_description)ON DUPLICATE KEY UPDATE `value` = :meta_description;
                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:meta_title_id,:store_id,:entity_id,:meta_title)ON DUPLICATE KEY UPDATE `value` = :meta_title;
                           INSERT INTO catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (:entity_type_id,:name_id,:store_id,:entity_id,:short_description)ON DUPLICATE KEY UPDATE `value` = :name;
                            ";
                $binds = array(
                    'entity_type_id'    => $this->_productEntityTypeId,
                    'short_descr_id'    => $this->_shortDescriptionId,
                    'descr_id'          => $this->_descriptionId,
                    'meta_descr_id'     => $this->_metaDescriptionId,
                    'meta_title_id'     => $this->_metaTitleId,
                    'name_id'           => $this->_nameId,
                    'store_id'          => $this->_websiteStoreView['uk'],
                    'entity_id'         => $cProductId,
                    'short_description' => $productShortDescriptionn['en'],
                    'description'       => $productData['DescriptionCatalogues']['value']['en'],
                    'meta_description'  => $productShortDescriptionn['en'],
                    'meta_title'        => $productData['HeaderWebs']['value']['en'],
                    'name'              => $productData['HeaderWebs']['value']['en'],
                );
                $connW->query($sql, $binds);

                $binds = array(
                    'entity_type_id'    => $this->_productEntityTypeId,
                    'short_descr_id'    => $this->_shortDescriptionId,
                    'descr_id'          => $this->_descriptionId,
                    'meta_descr_id'     => $this->_metaDescriptionId,
                    'meta_title_id'     => $this->_metaTitleId,
                    'name_id'           => $this->_nameId,
                    'store_id'          => $this->_websiteStoreView['dk'],
                    'entity_id'         => $cProductId,
                    'short_description' => $productShortDescriptionn['da'],
                    'description'       => $productData['DescriptionCatalogues']['value']['da'],
                    'meta_description'  => $productShortDescriptionn['da'],
                    'meta_title'        => $productData['HeaderWebs']['value']['da'],
                    'name'              => $productData['HeaderWebs']['value']['da'],
                );
                $connW->query($sql, $binds);

                $binds = array(
                    'entity_type_id'    => $this->_productEntityTypeId,
                    'short_descr_id'    => $this->_shortDescriptionId,
                    'descr_id'          => $this->_descriptionId,
                    'meta_descr_id'     => $this->_metaDescriptionId,
                    'meta_title_id'     => $this->_metaTitleId,
                    'name_id'           => $this->_nameId,
                    'store_id'          => $this->_websiteStoreView['ch'],
                    'entity_id'         => $cProductId,
                    'short_description' => $productShortDescriptionn['de_CH'],
                    'description'       => $productData['DescriptionCatalogues']['value']['de_CH'],
                    'meta_description'  => $productShortDescriptionn['de_CH'],
                    'meta_title'        => $productData['HeaderWebs']['value']['de_CH'],
                    'name'              => $productData['HeaderWebs']['value']['de_CH'],
                );
                $connW->query($sql, $binds);

                $binds = array(
                    'entity_type_id'    => $this->_productEntityTypeId,
                    'short_descr_id'    => $this->_shortDescriptionId,
                    'descr_id'          => $this->_descriptionId,
                    'meta_descr_id'     => $this->_metaDescriptionId,
                    'meta_title_id'     => $this->_metaTitleId,
                    'name_id'           => $this->_nameId,
                    'store_id'          => $this->_websiteStoreView['de'],
                    'entity_id'         => $cProductId,
                    'short_description' => $productShortDescriptionn['de'],
                    'description'       => $productData['DescriptionCatalogues']['value']['de'],
                    'meta_description'  => $productShortDescriptionn['de'],
                    'meta_title'        => $productData['HeaderWebs']['value']['de'],
                    'name'              => $productData['HeaderWebs']['value']['de'],
                );
                $connW->query($sql, $binds);

                $binds = array(
                    'entity_type_id'    => $this->_productEntityTypeId,
                    'short_descr_id'    => $this->_shortDescriptionId,
                    'descr_id'          => $this->_descriptionId,
                    'meta_descr_id'     => $this->_metaDescriptionId,
                    'meta_title_id'     => $this->_metaTitleId,
                    'name_id'           => $this->_nameId,
                    'store_id'          => $this->_websiteStoreView['nl'],
                    'entity_id'         => $cProductId,
                    'short_description' => $productShortDescriptionn['nl'],
                    'description'       => $productData['DescriptionCatalogues']['value']['nl'],
                    'meta_description'  => $productShortDescriptionn['nl'],
                    'meta_title'        => $productData['HeaderWebs']['value']['nl'],
                    'name'              => $productData['HeaderWebs']['value']['nl'],
                );
                $connW->query($sql, $binds);

                $binds = array(
                    'entity_type_id'    => $this->_productEntityTypeId,
                    'short_descr_id'    => $this->_shortDescriptionId,
                    'descr_id'          => $this->_descriptionId,
                    'meta_descr_id'     => $this->_metaDescriptionId,
                    'meta_title_id'     => $this->_metaTitleId,
                    'name_id'           => $this->_nameId,
                    'store_id'          => $this->_websiteStoreView['se'],
                    'entity_id'         => $cProductId,
                    'short_description' => $productShortDescriptionn['se'],
                    'description'       => $productData['DescriptionCatalogues']['value']['se'],
                    'meta_description'  => $productShortDescriptionn['se'],
                    'meta_title'        => $productData['HeaderWebs']['value']['se'],
                    'name'              => $productData['HeaderWebs']['value']['se'],
                );
                $connW->query($sql, $binds);

                $cProduct->clearInstance();
            } catch (Exception $e) {
                echo "item " . $productData['HeaderWebs']['value']['en'] . " not added\n";
                echo "exception:$e";
            }


        }


    }


    private function _extractConfiguration($node, &$productData)
    {
        foreach ($node as $element) {
            $key = $element->getName();

            $value = (string)$element->value;
            if (empty($value)) {
                $value = (string)$element;
                if (in_array($key, array('SizeGroup', 'AdCodes', 'Prices'))) {
                    $stringXML = new Varien_Simplexml_Element('<general_bracket>' . $value . '</general_bracket>'); //simplexml_load_string($value, null);
                    $value = array();
                    switch ($key) {
                        case 'SizeGroup':
                            $stringXML = $stringXML->SizeRange;
                            $value[$stringXML->getName()] = array(
                                'name' => $stringXML->getAttribute('name'),
                                'value' => (string)$stringXML
                            );
                            break;
                        case 'AdCodes':
                            foreach ($stringXML->AdCode as $subElement) {
                                $value[] = array(
                                    'catalogue' => $subElement->getAttribute('catalogue'),
                                    'value' => (string)$subElement,
                                    'key' => 'AdCode'
                                );
                            }
                            unset($subElement);
                            break;
                        case 'Prices':
                            foreach ($stringXML->Catalogue as $subElement) {
                                $subValue = array();
                                foreach ($subElement->Country as $country) {
//                                    $subValue[] = array(
//                                        'code' => ,
//                                        'currency' => $country->getAttribute('currency'),
//                                        'size' => (string)$country->Size
//                                    );
                                    foreach ($country->Size as $sizePrice){
                                        $subValue[$sizePrice->getAttribute('name')][]=$country->getAttribute('code');
                                    }
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

            if (empty($value)) {
                foreach ($element as $subElement) {
                    $locale = $subElement->getAttribute('locale');
                    $text = (string)$subElement;
                    if ($locale) {
                        $value[$locale] = $text;
                        continue;
                    }

                    $id = $subElement->getAttribute('id');
                    if (!empty($id)) {
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

            if (in_array($key, array('Resources', 'Items'))) {
                $value = array();
                foreach ($element as $subElement) {
                    $subProductData = array();
                    $subProductData['id'] = $subElement->getAttribute('id');
                    $this->_extractConfiguration($subElement, $subProductData);
                    $value[] = $subProductData;
                }
                unset($subElement);
            }

            $finalValue['value'] = $value;
            $cvl = $element->getAttribute('cvl');
            if (!empty($cvl)) {
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

        $this->_logMessage('Size parsing start');
        $this->_sizeTranslate = $this->_getSizeConfiguration();
        $this->_logMessage('Size parsing end');

        $this->_logMessage('Inventory parsing start');
        $this->_productInventory = $this->_getProductInventory();
        $this->_logMessage('Inventory end');


        $this->_customSizes = $this->_getCustomSize();
        $this->_getAttributeSetID('Default');
        $this->_attributeIdd = $this->_getAttributeID('bnp_size');
//        $this->_allWebsiteIDs = Mage::getModel('core/website')->getCollection()->getAllIds();

        $this->_allWebsiteIDs['base'] = Mage::getModel('core/website')->load('base')->getWebsiteId();
        $this->_allWebsiteIDs['uk'] = Mage::getModel('core/website')->load('uk')->getWebsiteId();
        $this->_allWebsiteIDs['dk'] = Mage::getModel('core/website')->load('dk')->getWebsiteId();
        $this->_allWebsiteIDs['se'] = Mage::getModel('core/website')->load('se')->getWebsiteId();
        $this->_allWebsiteIDs['de'] = Mage::getModel('core/website')->load('de')->getWebsiteId();
        $this->_allWebsiteIDs['ch'] = Mage::getModel('core/website')->load('ch')->getWebsiteId();
        $this->_allWebsiteIDs['nl'] = Mage::getModel('core/website')->load('nl')->getWebsiteId();


        $numberOfFiles = count($this->_data);
        $counter = 0;
        $this->_activeCatalogues = $this->_getActiveCatalogues();

        $this->_missingPictureFilePath = Mage::getBaseDir() . self::MISSING_PICTURES_BASE_PATH;

        $this->_fileHandlerPictures = fopen($this->_missingPictureFilePath, 'w');
        $this->_fileHandlerResources = fopen(Mage::getBaseDir() . self::RESOURCES_BASE_PATH, 'w');
        $this->_fileHandlerStyles = fopen(Mage::getBaseDir() . self::STYLES_BASE_PATH, 'w');

        foreach ($this->_data as $productConfig) {
            $toImport=0;
            $counter++;
//	    if ($counter<1000) continue;
            $productData = array();
            $this->_extractConfiguration($productConfig->getNode(), $productData);
            //check if the product is in active catalogue
            foreach ($productData['Catalogue']['value'] as $productCatalog){
                if (in_array($productCatalog,$this->_activeCatalogues)){
                    $toImport = 1;
                }
            }
            if ($toImport){
                $this->_logMessage($counter . ' / ' . $numberOfFiles . ' - Adding product file');
                $this->_addProduct($productData);
            }else{
                $this->_logMessage($counter . ' / ' . $numberOfFiles . ' - Skipping product file');
            }
            //if ($counter==10) break;
        }
        fclose($this->_fileHandlerPictures);
        fclose($this->_fileHandlerResources);
        fclose($this->_fileHandlerStyles);

        $config  = Mage::getConfig()->getResourceConnectionConfig("default_setup");
        $this->_logMessage('Importing resources....');
        if ($fp = popen("mysql -u ".$config->username." -p".$config->password." ".$config->dbname." -e \"LOAD DATA LOCAL INFILE '".Mage::getBaseDir()."/dump_files/tmp_import_resources.csv' INTO TABLE bonaparte_resources FIELDS TERMINATED BY ',';\";", "r"))  {
            while( !feof($fp) ){
                echo fread($fp, 1024);
                flush(); 
            }
            fclose($fp);
            $this->_logMessage('Done!' . "\n");
        }
        $this->_logMessage('Importing styles....');
        if ($fp = popen("mysql -u ".$config->username." -p".$config->password." ".$config->dbname." -e \"LOAD DATA LOCAL INFILE '".Mage::getBaseDir()."/dump_files/tmp_import_styles.csv' INTO TABLE bonaparte_styles FIELDS TERMINATED BY ',';\";", "r"))  {
            $this->_logMessage('Done!' . "\n");
            while( !feof($fp) ){
                echo fread($fp, 1024);
                flush();
            }
            fclose($fp);
        }

        $this->_logMessage('ALL DONE!!!' . "\n");
        $this->_logMessage('There were ' . $this->_newProductCounter . ' new products created!' . "\n");
    }

}