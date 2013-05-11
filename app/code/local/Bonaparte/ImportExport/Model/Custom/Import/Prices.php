<?php

/**
 * Stores the business logic for the custom price import
 *
 * @category    Bonaparte
 * @package     Bonaparte_ImportExport
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_ImportExport_Model_Custom_Import_Prices extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    /**
     * Path at which the category configuration is found
     *
     * @var string
     */
    const CONFIGURATION_FILE_PATH = '/dump_files/ARTIKLAR.TXT';

    /**
     * The magento code for attibute adcodes
     *
     * @var string
     */
    const ATTRIBUTE_CODE_ADCODES = 'bnp_adcodes';

    const MEMORY_LIMIT = '2048M';

    /**
     * Store current ad code for the sku
     *
     * @var array
     */
    private $_skuAdCodes = array();

    /**
     * Construct import model
     */
    public function _construct()
    {
        ini_set('memory_limit', self::MEMORY_LIMIT);

        $this->_logMessage('Reading configuration files');
        $this->_configurationFilePath = Mage::getBaseDir() . self::CONFIGURATION_FILE_PATH;

        $fileHandler = fopen($this->_configurationFilePath, 'r');
        $prices = array();
        $dataKeys = array(
            'articleno',
            'headarticle',
            'countrycode',
            'articleno2',
            'headarticle2',
            'description',
            'size',
            'price',
            'stockdisp',
            'status',
            'length',
            'width',
            'height',
            'weight',
            'showcode',
            'timestamp',
            'metrecode',
            'waitlist',
            'reg_price',
            'traffic_light',
            'available_date',
            'preview'
        );

        $row = 0;
        $currentSKU = null;
        $temporaryData = array();
        while($line = fgets($fileHandler)) {
            $row++;
            $this->_logMessage('Row ' . $row);
            $line = explode(';', $line);
            $priceData = array();
            foreach($dataKeys as $key => $value) {
                $priceData[$value] = $line[$key];
            }
            unset($key, $value);

            $headArticleExploded = explode('-', $priceData['headarticle']);
            $rowSKU = $headArticleExploded[1] . '-' . $priceData['size'] . '-' . $priceData['countrycode'];
            if($currentSKU != $rowSKU) {
                if(count($temporaryData)) {
                    $lowestPrice = null;
                    foreach($temporaryData as $data) {
                        if(($data['price'] < $lowestPrice) || $lowestPrice === null) {
                            $lowestPrice = $data['price'];
                            $this->_skuAdCodes[$currentSKU] = substr($data['articleno2'], 5, 1);
                        }
                    }
                    $this->_data[$currentSKU] = array(
                        'regular' => $data['price'],
                        'special' => $lowestPrice
                    );
                    unset($data, $lowestPrice);
                }

                $currentSKU = $rowSKU;
                unset($temporaryData);
            }

            $temporaryData[] = $priceData;
            unset($priceData);
        }
        fclose($fileHandler);
    }

    /**
     * Specific category functionality
     */
    public function start($options = array())
    {
        $this->_logMessage('Started importing prices' . "\n" );

        $storeViews = array();
        foreach(Mage::app()->getWebsites() as $website) {
            $storeIds = $website->getStoreIds();
            $storeViews[strtolower($website->getCode())] = array_pop($storeIds);
        }

        $row = 0;
        foreach($this->_data as $sku => $price) {
            $this->_logMemoryUsage();
            $countrySku = $sku;
            $sku = explode('-', $sku);
            $countryCode = strtolower($sku[2]);
            $sku = $sku[0] . '-' . $sku[1];

            $this->_logMessage('Sku: ' . $sku . "\n" );

            $model = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
            if(empty($model)) {
                continue;
            }

            $relationCollection = Mage::getModel('Bonaparte_ImportExport/External_Relation_Attribute_Option')
                ->getCollection()
                ->addFieldToFilter('attribute_code', self::ATTRIBUTE_CODE_ADCODES)
                ->addFieldToFilter('external_id', $this->_skuAdCodes[$countrySku]);
            $relationModel = $relationCollection->load()->getFirstItem();

            $this->_logMessage('Sku: ' . $sku . ' on ' . $countryCode . "\n" );
            $model->setStoreId($storeViews[$countryCode])
                    ->setPrice($price['regular']/100)
                    ->setSpecialPrice($price['special']/100)
                    ->setBnpAdcodes($relationModel->getInternalId())
                    ->save();
            $model->clearInstance();
            $relationModel->clearInstance();
            unset($relationCollection);
            break;
        }

        $this->_logMessage('Finished importing prices' . "\n" );
    }

}
