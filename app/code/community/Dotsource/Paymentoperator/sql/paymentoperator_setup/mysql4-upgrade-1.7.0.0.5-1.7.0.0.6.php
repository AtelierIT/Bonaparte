<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 29.04.2013 10:21:34
 *
 * Contributors:
 * mbloss - initial contents
 */
// Remove address check attributes
/* @var $this Dotsource_Paymentoperator_Model_Mysql4_Setup */
$this->startSetup();

try {
    $this->removeAttribute('quote_address', 'po_checked_address_hash');
    $this->removeAttribute('customer_address', 'po_checked_address_hash');
    $this->removeAttribute('order_address', 'paymentoperator_checked_address_hash');
} catch (Exception $e) {
    // nothing to do here, exception might be thrown if attributes are already removed
}

$this->endSetup();
