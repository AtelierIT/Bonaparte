<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 20.03.2013 15:20:07
 *
 * Contributors:
 * mbloss - initial contents
 */

class Dotsource_Paymentoperator_Model_Check_Risk_Request_Deltavista
    extends Dotsource_Paymentoperator_Model_Check_Risk_Request_Abstract
{

    /**
     * @see Dotsource_Paymentoperator_Model_Payment_Request_Request::getRequestFile()
     */
    public function getRequestFile()
    {
        return 'deltavista.aspx';
    }


    /**
     * Return special risk response model.
     *
     * @return string
     */
    public function getResponseModelCode()
    {
        return 'paymentoperator/check_risk_response_deltavista';
    }


    /**
     * Setup the given response model as response for the current request object.
     * @param $response
     */
    public function setResponseModel(Dotsource_Paymentoperator_Model_Payment_Response_Response $response)
    {
        $this->_responseModel = $response;
    }


    /**
     * @see Dotsource_Paymentoperator_Model_Payment_Request_Request::_getRequestData()
     */
    protected function _getRequestData()
    {
        //Global data
        $oracle         = $this->getOracle();
        $encryptData    = $this->_getEncryptionDataObject();
        $riskModel      = $this->_getRiskModel();

        //Informations we need
        $billingAddress = $oracle->getBillingAddress();
        $street         = $this->_getHelper()->getConverter()->splitStreet($billingAddress);

        //Fill the request data
        $encryptData['TransID']         = $this->_getIncrementId();
        $encryptData['RefNr']           = $this->getPaymentoperatorTransactionModel()->getTransactionCode();

        $encryptData['OrderDesc']       = $encryptData['TransID'];

        $encryptData['ProductName']     = $riskModel->getConfigData('deltavista_check_type');


        $encryptData['ProductCountry']  = strtoupper($billingAddress->getCountryModel()->getIso3Code());
        $encryptData['Language']        = $encryptData['ProductCountry'];

        $encryptData['LegalForm']       = $this->_getLegalForm($billingAddress);


        $encryptData['FirstName']       = $billingAddress->getFirstname();
        $encryptData['LastName']        = $billingAddress->getLastname();
        $encryptData['Nationality']     = $encryptData['ProductCountry'];
        $encryptData['DateOfBirth']     = $oracle->getDob('yyyyMMdd');

        $encryptData['AddrStreet']      = $street->getStreetName();
        $encryptData['AddrStreetNr']    = $street->getStreetNumber();
        $encryptData['AddrCity']        = $billingAddress->getCity();
        $encryptData['AddrZip']         = $billingAddress->getPostcode();
        $encryptData['AddrCountryCode'] = $encryptData['ProductCountry'];
    }

    /**
     * Return the legal form.
     *
     * @param $billingAddress Mage_Customer_Model_Address_Abstract
     * @return  string
     */
    protected function _getLegalForm($billingAddress)
    {
        if ($billingAddress->getCompany() != '') {
            return 'COMPANY';
        }
        return 'PERSON';
    }
}