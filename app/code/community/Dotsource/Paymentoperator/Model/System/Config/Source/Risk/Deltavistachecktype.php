<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 22.03.2013 15:25:13
 *
 * Contributors:
 * mbloss - initial contents
 */

class Dotsource_Paymentoperator_Model_System_Config_Source_Risk_Deltavistachecktype
    extends Dotsource_Paymentoperator_Model_System_Config_Source_Abstract
{

    /** Holds all check types*/
    public static $checkTypes = array(
        'QuickCheckConsumer',
        'CreditCheckConsumer',
        'QuickCheckBusiness',
        'CreditCheckBusiness',
        'IdentificationSearch',
    );


    /**
     * Return the inquiry offices options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $data = array();

        foreach (self::$checkTypes as $checkType) {
            $data[] = array(
                'value' => $checkType,
                'label' => $checkType
            );
        }

        return $data;
    }
}