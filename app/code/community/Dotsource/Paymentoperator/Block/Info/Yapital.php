<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 07.03.2012 17:52:32
 *
 * Contributors:
 * mbloss - initial contents
 */

class Dotsource_Paymentoperator_Block_Info_Yapital
    extends Dotsource_Paymentoperator_Block_Info_Abstract
{

    /**
     * @see Mage_Payment_Block_Info::_construct()
     */
    protected function _construct()
    {
        parent::_construct();

        //Change the template
        $this->setTemplate('paymentoperator/info/callback.phtml');
    }
}