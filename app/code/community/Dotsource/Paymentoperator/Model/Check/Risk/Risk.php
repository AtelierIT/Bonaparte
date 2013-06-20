<?php
/**
 * Copyright (c) 2008-2011 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Contributors:
 * Erik Wohllebe - initial contents
 * mbloss - preparing for multiple risk check types
 */

/**
 * Central model for risk check
 */
class Dotsource_Paymentoperator_Model_Check_Risk_Risk
    extends Dotsource_Paymentoperator_Model_Check_Risk_Abstract
{

    /** Holds a flag if the risk logic should use the fallback payments */
    protected static $_useFallbackPayments      = false;

    /** Holds the risk check code */
    protected $_code                            = 'risk_check';

    /** Holds the available risk model paths */
    protected $_riskModelPath                = array(
        'deltavista',
        'scoring'
    );

    /** Holds the risk models */
    protected $_riskModels        = null;


    /**
     * Init the risk models.
     *
     * @param $payment
     * @return Dotsource_Paymentoperator_Model_Check_Risk_Risk
     */
    public function init($payment)
    {
        if (null === $this->_riskModels) {
            $this->_riskModels = array();
            $riskApis = $this->getConfigData('inquiry_apis');
            $riskApis = explode(',', $riskApis);
            foreach ($riskApis as $riskApi) {
                $this->_riskModels[] = Mage::getModel('paymentoperator/check_risk_' . $riskApi)->init($payment);
            }
        }
        return $this;
    }

    /**
     * Process the risk request for all available risk check types. The response is saved in the storage system.
     * To get the response use the getResponse-Method.
     *
     * @return Dotsource_Paymentoperator_Model_Check_Risk_Risk
     */
    public function process()
    {
        if (null === $this->_riskModels) {
            return $this;
        }
        /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
        foreach ($this->_riskModels as $riskModel) {
            $riskModel->process();
        }

        return $this;
    }


    /**
     * Return true if the current payment can use. If the method return false
     * the user is not allowed to use the payment method. If the return value
     * is null we don't know if the user is allowed to use the payment method.
     *
     * @param mixed $payment
     * @return boolean
     */
    public function isPaymentAvailable($payment = null)
    {
        $hasUnknownState = false;

        if (null === $this->_riskModels) {
            //Undefined
            return null;
        }

        /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
        foreach ($this->_riskModels as $riskModel) {
            $lastResult = $riskModel->isPaymentAvailable($payment);
            if ($lastResult === false) {
                return false;
            }
            if ($lastResult === null) {
                $hasUnknownState = true;
            }
        }

        if ($hasUnknownState) {
            //Undefined
            return null;
        }
        return true;

    }

    /**
     * Check if at least one of the the risk models can process.
     *
     * @return boolean
     */
    public function isAvailable()
    {
        if (null === $this->_riskModels) {
            return false;
        }
        /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
        foreach ($this->_riskModels as $riskModel) {
            if ($riskModel->isAvailable()) {
                return true;
            }
        }

        return false;
    }


    /**
     * Return true if all conditions ok to send a request to paymentoperator
     * to get a risk value.
     *
     * @return boolean
     */
    public function isAllowToSendRequest()
    {
        if (!is_array($this->_riskModels)) {
            return false;
        }
        /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */

        // Because each risk model do this check with same data, check of only one risk model is nessessary.
        $riskModel = reset($this->_riskModels);
        return $riskModel->isAllowToSendRequest();
    }

    /**
     * Checks risk models for errors
     *
     * @return boolean
     */
    public function hasError()
    {
        if (null === $this->_riskModels) {
            return false;
        }
        /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
        foreach ($this->_riskModels as $riskModel) {
            if ($riskModel->hasError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a valid previous response.
     *
     * @return array of Dotsource_Paymentoperator_Model_Check_Risk_Response_Response || null
     */
    public function getResponses()
    {
        $responses = array();
        if (null !== $this->_riskModels) {
            /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
            foreach ($this->_riskModels as $riskModel) {
                $responses[] = $riskModel->getResponse();
            }
        }

        return $responses;
    }

    /**
     * Return the available risk models.
     *
     * @return array of Dotsource_Paymentoperator_Model_Check_Risk_Abstract || null
     */
    public function getRiskModels()
    {
        return $this->_riskModels;
    }

    /**
     * Return true if we have a valid response for each risk model.
     *
     * @return boolean
     */
    public function hasResponse()
    {
       foreach ($this->getResponses() as $response) {
           if (!($response instanceof Dotsource_Paymentoperator_Model_Payment_Response_Response)) {
               return false;
           }
       }

       return true;
    }

    /**
     * Sync the storge models for each risk model.
     * If $shouldSave is true the configured save method from the primary object will called.
     *
     * @param boolean $shouldSave
     * @return Dotsource_Paymentoperator_Model_Check_Risk_Risk
     */
    public function sync($shouldSave = false)
    {
        if (null !== $this->_riskModels) {
            /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
            foreach ($this->_riskModels as $riskModel) {
                if ($riskModel->isAvailable() && $riskModel->getStorageModel()->hasPrimaryObject()) {
                    $riskModel->sync($shouldSave);
                }
            }
        }
        return $this;
    }

    /**
     * Return the check moment of the risk check.
     *
     * @string
     */
    protected function _getRiskCheckMoment()
    {
        return $this->getConfigData('check_moment');
    }

    /**
     * Return true if the risk check should use by order place.
     *
     * @return boolean
     */
    public function isRiskCheckAtOrderPlace()
    {
        return Dotsource_Paymentoperator_Model_System_Config_Source_Risk_Checkposition::PLACE_ORDER
            === $this->_getRiskCheckMoment();
    }

    /**
     * Return true if the risk check should use by the payment methods.
     *
     * @return boolean
     */
    public function isRiskCheckAtPaymentsMethods()
    {
        return Dotsource_Paymentoperator_Model_System_Config_Source_Risk_Checkposition::PAYMENT
            === $this->_getRiskCheckMoment();
    }

    /**
     * Set a flag if the risk method should use fallback payments.
     *
     * @param boolean $flag
     * @return Dotsource_Paymentoperator_Model_Check_Risk_Risk
     */
    public function setUseFallbackFlag($flag)
    {
        if (null !== $this->_riskModels) {
            /* @var $riskModel Dotsource_Paymentoperator_Model_Check_Risk_Abstract */
            foreach ($this->_riskModels as $riskModel) {
                $riskModel->setUseFallbackFlag($flag);
            }
        }
        return $this;
    }
}