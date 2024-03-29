<?php
/**
 * Copyright (c) 2008-2011 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Contributors:
 * Erik Wohllebe - initial contents
 */
class Dotsource_Paymentoperator_Block_Form_Klarna_Financing
    extends Dotsource_Paymentoperator_Block_Form_Klarna_Abstract
{

    protected function _construct()
    {
        parent::_construct();

        //Template
        $this->setTemplate('paymentoperator/form/klarna/financing.phtml');
    }

    /**
     * Init the logo.
     */
    protected function _initLogos()
    {
        $this->addLogo(
            $this->getSkinUrl('images/paymentoperator/paymentoperator_klarna-financing.png'),
            $this->_getHelper()->__('Klarna')
        );
    }
}