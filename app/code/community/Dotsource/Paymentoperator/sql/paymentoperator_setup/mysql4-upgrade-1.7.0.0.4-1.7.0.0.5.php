<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 25.03.2013 10:10:56
 *
 * Contributors:
 * mbloss - initial contents
 */
/* @var $this Dotsource_Paymentoperator_Model_Mysql4_Setup */
$this->startSetup();

$this->addAttribute(
    'customer',
    'paymentoperator_risk_check_dv',
    array(
        'type'  => 'text',
    )
);

$this->endSetup();