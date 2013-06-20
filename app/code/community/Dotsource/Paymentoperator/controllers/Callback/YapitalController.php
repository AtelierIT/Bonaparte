<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 07.03.2012 17:52:13
 *
 * Contributors:
 * mbloss - initial contents
 */
class Dotsource_Paymentoperator_Callback_YapitalController
    extends Dotsource_Paymentoperator_Controller_Paymentoperatorcallback
{

    /**
     * @see Dotsource_Paymentoperator_Controller_Paymentoperatorcallback::_getPaymentCode()
     *
     * @return string
     */
    protected function _getPaymentCode()
    {
        return 'paymentoperator_yapital';
    }
}