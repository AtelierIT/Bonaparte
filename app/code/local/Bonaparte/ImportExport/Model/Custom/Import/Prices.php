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
        ini_set('memory_limit', '2048M');
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
            'traffic',
            'light',
            'available_date',
            'preview'
        );

        $row = 0;
        $currentSKU = null;
        $temporaryData = array();
        while($line = fgets($fileHandler)) {
            $row++;
            $this->_logMessage('Processing row ' . $row);
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
                    $this->_data[$currentSKU] = $lowestPrice;
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
    {//var_dump($this->_skuAdCodes);exit;
        $this->_logMessage('Started importing prices' . "\n" );


        $storeViews = array();
        foreach(Mage::app()->getWebsites() as $website) {
            $storeIds = $website->getStoreIds();
            $storeViews[strtolower($website->getCode())] = array_pop($storeIds);
        }

        $row = 0;
        foreach($this->_data as $sku => $price) {
            $this->_logMemoryUsage();
            $sku = explode('-', $sku);
            $countryCode = strtolower($sku[2]);
            $sku = $sku[0] . '-' . $sku[1];

            $this->_logMessage('Sku: ' . $sku . "\n" );

            $model = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
            if(empty($model)) {
               continue;
            }

            $this->_logMessage('Price set on sku: ' . $sku . ' on store view ' . $countryCode . "\n" );
            $model->setStoreId($storeViews[$countryCode])
                    ->setPrice($price)
                    ->save()
                    ->clearInstance();
        }

        $this->_logMessage('Finished importing prices' . "\n" );
    }

}