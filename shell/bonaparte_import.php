<?php

require_once 'abstract.php';

/**
 * Bonaparte import shell script
 *
 * @category    Bonaparte
 * @package     Bonaparte_Shell
 * @author      Atelier IT Team <office@atelierit.ro>
 */
class Bonaparte_Shell_Import extends Mage_Shell_Abstract
{
    const IMPORT_TYPE_ATTRIBUTES = 'attributes';
    const IMPORT_TYPE_CATEGORIES = 'categories';
    const IMPORT_TYPE_PRODUCTS = 'products';
    const IMPORT_TYPE_PRICES = 'prices';

    public function run() {
        switch($this->_args['type']) {
            case self::IMPORT_TYPE_ATTRIBUTES:
                Mage::getModel('Bonaparte_ImportExport/Custom_Import_Attributes')->start();
                break;
            case self::IMPORT_TYPE_CATEGORIES:
                Mage::getModel('Bonaparte_ImportExport/Custom_Import_Categories')->start();
                break;
            case self::IMPORT_TYPE_PRODUCTS:
                Mage::getModel('Bonaparte_ImportExport/Custom_Import_Products')->start();
                break;
            case self::IMPORT_TYPE_PRICES:
                Mage::getModel('Bonaparte_ImportExport/Custom_Import_Prices')->start();
                break;
            default:
                $this->usageHelp();
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f bonaparte_import.php -- [options]

  --type <type>                 attributes|categories|products|prices
USAGE;
    }

}

$shell = new Bonaparte_Shell_Import();
$shell->run();