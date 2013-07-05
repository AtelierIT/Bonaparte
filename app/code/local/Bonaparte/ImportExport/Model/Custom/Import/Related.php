<?php

/**
 * Stores the business logic for the custom price import
 *
 * @category    Bonaparte
 * @package     Bonaparte_ImportExport
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_ImportExport_Model_Custom_Import_Related extends Bonaparte_ImportExport_Model_Custom_Import_Abstract
{
    /**
     * Path at which the category configuration is found
     *
     * @var string
     */


    /**
     * The magento code for attibute adcodes1
     *
     * @var string
    */

    /**
     * Store current ad code for the sku
     *
     * @var array
     */

    /**
     * Construct import model
     */
    public function _construct()
    {

    }

    /**
     * Set relation between configurable products
     */
    /**
     * Specific category functionality
     */
    public function start($options = array())
    {
        $this->_logMessage('Started relate products' . "\n" );


		$eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
		$conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $connW = Mage::getSingleton('core/resource')->getConnection('core_write');
        
        $config  = Mage::getConfig()->getResourceConnectionConfig("default_setup");


        //Read data from bonaparte_sources
        $sql = "SELECT DISTINCT picture_name FROM bonaparte_resources";
        $results = $conn->fetchAll($sql);

        if ($results) {
            foreach ($results as $res) {

                //get entities associated to this picture

                $sql2 = "SELECT * FROM bonaparte_resources WHERE `picture_name` = '".$res['picture_name']."' AND product_type = 1 ORDER BY entity_id";
                $results2 = $conn->fetchAll($sql2);

                #print_r($results2);
                //
                if (count($results2) > 1) {
                    echo $res['picture_name']."\n";
                    $no_products = count($results2);

                    /*
                    $main_product_id = $results2[0]['entity_id'];

                    //get related products
                    $prod = Mage::getModel('catalog/product');
                    $prod->load($main_product_id);

                    $related_prods = $prod->getRelatedProductIds();
                    $related_news  = array(); // store new related prods

                    #var_dump($related_prods);



                    for ($i=1;$i<$no_products;$i++) {

                        //check if already related
                        if (!in_array($results2[$i]['entity_id'], $related_prods)) {
                            //Not related
                            echo "Add to related \n";
                            $related_news[$results2[$i]['entity_id']] = array( "position"=> 0);
                        }
                    }

                    #print_r($related_news);

                    if (count($related_news)) {
                        //Add related products and save
                        $prod->setRelatedLinkData($related_news);
                        #$prod->setUpSellLinkData($param);
                        #$prod->setCrossSellLinkData($param);
                        $prod->save();
                    }

                    */

                    for ($i=0;$i<$no_products;$i++) {

                        $main_product_id = $results2[$i]['entity_id'];

                        //get related products
                        $prod = Mage::getModel('catalog/product');
                        $prod->load($main_product_id);

                        $related_prods = $prod->getRelatedProductIds();
                        $related_news  = array(); // store new related prods

                        for ($j=0;$j<$no_products;$j++) {
                            if ($i == $j) continue;

                            if (!in_array($results2[$j]['entity_id'], $related_prods)) {
                                //Not related
                                echo "Add to related \n";
                                $related_news[$results2[$j]['entity_id']] = array( "position"=> 0);
                            }
                        }

                        if (count($related_news)) {
                            //Add related products and save
                            $prod->setRelatedLinkData($related_news);
                            #$prod->setUpSellLinkData($param);
                            #$prod->setCrossSellLinkData($param);
                            $prod->save();
                        }

                    }

                }
            }
        }


		
        $this->_logMessage('Finished relate products' . "\n" );
    }

}
