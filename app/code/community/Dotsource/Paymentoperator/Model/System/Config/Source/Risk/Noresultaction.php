<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 25.04.2013 12:43:25
 *
 * Contributors:
 * mbloss - initial contents
 */

class Dotsource_Paymentoperator_Model_System_Config_Source_Risk_Noresultaction
    extends Dotsource_Paymentoperator_Model_System_Config_Source_Abstract
{
    /**
     * Return the available actions for no-result.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'green',
                'label' => $this->_getHelper()->__('Green')
            ),
            array(
                'value' => 'yellow',
                'label' => $this->_getHelper()->__('Yellow')
            ),
            array(
                'value' => 'red',
                'label' => $this->_getHelper()->__('Red')
            )
        );
    }
}