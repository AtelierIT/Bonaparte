<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 07.03.2012 17:36:32
 *
 * Contributors:
 * mbloss - initial contents
 */
class Dotsource_Paymentoperator_Block_Form_Yapital
    extends Dotsource_Paymentoperator_Block_Form_Abstract
{

    protected function _construct()
    {
        parent::_construct();

        //Change the template
        $this->setTemplate('paymentoperator/form/yapital.phtml');
    }

    /**
     * Init the logo.
     */
    protected function _initLogos()
    {
        $this->addLogo(
            $this->getSkinUrl('images/paymentoperator/paymentoperator_yapital.png'),
            $this->_getHelper()->__('Yapital')
        );
    }
}