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
            $this->_logMessage($row++);
            $this->_logMemoryUsage();
            $line = explode(';', $line);
            $priceData = array();
            foreach($dataKeys as $key => $value) {
                $priceData[$value] = $line[$key];
            }
            unset($key, $value);

            $headArticleExploded = explode('-', $priceData['headarticle']);
            $rowSKU = $headArticleExploded[1] . '-' . $priceData['size'];
            if($currentSKU != $rowSKU) {
                if(count($temporaryData)) {
                    $lowestPrice = null;
                    foreach($temporaryData as $data) {
                        if(($data['price'] < $lowestPrice) || $lowestPrice === null) {
                            $lowestPrice = $data['price'];
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
            if($row == '30000') {
                break;
            }
        }
        fclose($fileHandler);

        foreach($this->_data as $sku => $price) {
            $model = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
            $model->setPrice($price);
            $model->save();
            $model->clearInstance();
        }
    }

    /**
     * Specific category functionality
     */
    public function start()
    {
        $this->_logMessage('Started importing prices' . "\n" );

        $this->_logMessage('Finished importing prices' . "\n" );
    }

}