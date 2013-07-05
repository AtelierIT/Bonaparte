<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 25.03.2013 15:29:58
 *
 * Contributors:
 * mbloss - initial contents
 */

class Dotsource_Paymentoperator_Model_System_Config_Source_Risk_Inquiryapis
    extends Dotsource_Paymentoperator_Model_System_Config_Source_Abstract
{

    /** Holds all inquiry apis*/
    public static $apis = array(
        'deltavista' => 'Deltavista',
        'scoring' => 'Scoring'
    );


    /**
     * Return the inquiry offices options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $data = array();

        foreach (self::$apis as $key => $value) {
            $data[] = array(
                'value' => $key,
                'label' => $value
            );
        }

        return $data;
    }
}