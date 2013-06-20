<?php
/**
 * Copyright (c) 2008-2011 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Contributors:
 * Erik Wohllebe - initial contents
 */
class Dotsource_Paymentoperator_Model_Check_Risk_Response_Deltavista
    extends Dotsource_Paymentoperator_Model_Check_Risk_Response_Response
{

    /** Holds the mapping of the "aktion" values */
    protected $_mappingValues = array(
        'red'   => 'red',
        'yellow'  => 'yellow',
        'green' => 'green',
        'no result' => 'noresult'
    );

    /** Holds the name of the action/result field */
    protected $_actionField = 'result';

    /**
     * Return true if the value "aktion" is green.
     *
     * @return boolean
     */
    public function isGreen()
    {
        return ('green' === $this->getActionValue() || $this->_isNoResultConfigured($this->getActionValue(), 'green'));
    }


    /**
     * Return true if the value "aktion" is yellow.
     *
     * @return boolean
     */
    public function isYellow()
    {
        return ('yellow' === $this->getActionValue() || $this->_isNoResultConfigured($this->getActionValue(), 'yellow'));
    }


    /**
     * Return true if the value "aktion" is red.
     *
     * @return boolean
     */
    public function IsRed()
    {
        return ('red' === $this->getActionValue() || $this->_isNoResultConfigured($this->getActionValue(), 'red'));
    }

    /**
     * Return the mapped "aktion" value.
     *
     * @return string || null
     */
    public function getMappedActionValue()
    {
        //Get the aktion value
        $aktion = $this->getActionValue();

        if ($aktion == 'no result') {
            return Mage::helper('paymentoperator/config')->getNoResultAction();
        }
        //Return the mapped value if exists
        if (isset($this->_mappingValues[$aktion])) {
            return $this->_mappingValues[$aktion];
        }

        return null;
    }

    /**
     * Checks if action value is "no result" and configurated action equals given status.
     *
     * @param string $actionValue
     * @param string $status
     * @return bool
     */
    protected function _isNoResultConfigured($actionValue, $status)
    {
        if ($actionValue != 'no result') {
            return false;
        }
        return Mage::helper('paymentoperator/config')->getNoResultAction() == $status;
    }
}