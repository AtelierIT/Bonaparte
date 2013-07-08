<?php

/**
 * Stores the business logic for the custom attribute import
 *
 * @category    Bonaparte
 * @package     Bonaparte_ImportExport
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_ImportExport_Model_Custom_Import_Stockincr extends Bonaparte_ImportExport_Model_Custom_Import_AbstractCsv
{
	private $_bnpAttributes = array();
    /**
     * Path at which the category configuration is found
     *
     * @var string
     */
    //const CONFIGURATION_FILE_PATH = '/dump_files/csv/Pulsen_incremental_inventory.TXT';
    const CONFIGURATION_FILE_PATH = '/dump_files/WBN2435';

    /**
     * Construct import model
     */
    public function _construct()
    {
        $this->_source = Mage::getBaseDir() . self::CONFIGURATION_FILE_PATH;
        $this->_init();

        
    }
	
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
     * Specific category functionality
     */
    public function start($options = array())
    {
		$csv_data = array();
        $csv_data = &$this->_data;
        
        $product = Mage::getModel('catalog/product');
        
        //remove duplicates and create sku from info
        $arr_sku_stock = array();
        while ($this->next() == true) {
			$item = $this->_currentRow;
			$sku = "";
			$sku = $item[0]."-".$item[1];
			
			switch ($item[7]) {
				case "0000001": $traffic_light = "01";
				case "9999999": $traffic_light = "03";
				default: $traffic_light = "02";
			}
			
			#if (array_key_exists($sku,$arr_sku_stock))
			$arr_sku_stock[$sku] = array("qty" => $item[2], "is_in_stock" => ($item[2]>0)?1:0, "traffic_light" => $traffic_light);
		}
		
		//print_r($arr_sku_stock);
		
		//return;
        
        foreach ($arr_sku_stock as $key => $item) {
			 if (!$key) continue;
			
			 $productId = $product->getIdBySku($key);
			 if ($productId) {
				$product->load($productId);
				//set traffic light
				$product->setBnpTrafficlight($this->_getAttributeLabelId('bnp_trafficlight',$item['traffic_light']));
				// get product's stock data such quantity, in_stock etc
				//$stockData = $product->getStockData();
		 
				// update stock data using new data
				//$stockData['qty'] = $item[1]; //second column from csv file
				//$stockData['is_in_stock'] = 1;
			 
				// then set product's stock data to update
				$product->setStockData($item);
				try {
					$product->save();
					echo "Product stock updated for sku " . $key . "\n";
				}
				catch (Exception $ex) {
					echo $ex->getMessage();
				}
			 } 
			
		}

        echo 'DONE';
    }

}
