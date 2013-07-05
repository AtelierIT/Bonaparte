<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Created:
 * 07.03.2013 10:29:20
 *
 * Contributors:
 * mbloss - initial contents
 */

class Dotsource_Paymentoperator_Model_Payment_Request_Yapital_Authorize
    extends Dotsource_Paymentoperator_Model_Payment_Request_Request
{

    /**
     * @see Dotsource_Paymentoperator_Model_Payment_Request_Request::getRequestFile()
     *
     * @return  string
     */
    public function getRequestFile()
    {
        return 'yapital.aspx';
    }


    /**
     * @see Dotsource_Paymentoperator_Model_Payment_Request_Request::_preProcessRequestData()
     *
     * @return  null
     */
    protected function _preProcessRequestData()
    {
        //Do the parent stuff first
        Dotsource_Paymentoperator_Model_Payment_Request_Request::_preProcessRequestData();

        //Get the request objects
        $encryptData    = $this->_getEncryptionDataObject();
        $userData       = $this->_getUserDataObject();

        //Authorize is always a async payment
        $this
            ->getPayment()
            ->setIsTransactionPending(true)
            ->setTransactionPendingStatus(Dotsource_Paymentoperator_Model_Payment_Abstract::WAITING_CAPTURE);

        //Add the mode to the user data and the payment code
        //The information are needed to check if the right controller is called
        $userData
            ->setCapture(Dotsource_Paymentoperator_Model_System_Config_Source_Paymentaction::BOOKING)
            ->setPaymentCode($this->getPaymentMethod()->getCode());
    }


    /**
     * @see Dotsource_Paymentoperator_Model_Payment_Request_Request::_getRequestData()
     */
    protected function _getRequestData()
    {
        //Get the request objects
        $encryptData    = $this->_getEncryptionDataObject();
        /* @var $conv Dotsource_Paymentoperator_Helper_Converter */
        $conv = $this->_getConverter();

        //Get the data we need
        $order          = $this->getPayment()->getOrder();
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $billingStreet          = $conv->getFullStreet($billingAddress);
        $shippingStreet         = $conv->getFullStreet($shippingAddress);

        //Get the quote to a converted order desc
        $convertedQuoteOrderDesc = $conv->convertOrderToInformationOrderDesc($order);

        //Set the encrypt data
        $encryptData['TransID']      = $this->_getIncrementId();
        $encryptData['RefNr']        = $this->getPaymentoperatorTransactionModel()->getTransactionCode();
        $encryptData['ReqID']        = $encryptData['TransID'].time();

        $encryptData['Amount']       = $conv->formatPrice($this->getAmount(), $this->_getCurrencyCode());
        $encryptData['Currency']     = $conv->convertToUtf8($this->_getCurrencyCode());

        $encryptData['Response']     = 'encrypt';

        // Billing Address.
        $encryptData['bdTitle']             = $billingAddress->getPrefix();
        $encryptData['bdFirstName']         = $billingAddress->getFirstname();
        $encryptData['bdLastName']          = $billingAddress->getLastname();
        $encryptData['bdStreet']            = $billingStreet;
        $encryptData['bdAddressAddition']   = $billingAddress->getStreet2();
        $encryptData['bdCity']              = $billingAddress->getCity();
        $encryptData['bdZip']               = $billingAddress->getPostcode();
        $encryptData['bdCountryCode']       = $billingAddress->getCountryModel()->getIso3Code();
        $encryptData['bdState']             = $billingAddress->getRegion();
        $encryptData['bdReadOnly']          = 'true';

        // Shipping Address
        $encryptData['sdTitle']             = $shippingAddress->getPrefix();
        $encryptData['sdFirstName']         = $shippingAddress->getFirstname();
        $encryptData['sdLastName']          = $shippingAddress->getLastname();
        $encryptData['sdStreet']            = $shippingStreet;
        $encryptData['sdAddressAddition']   = $shippingAddress->getStreet2();
        $encryptData['sdCity']              = $shippingAddress->getCity();
        $encryptData['sdZip']               = $shippingAddress->getPostcode();
        $encryptData['sdCountryCode']       = $shippingAddress->getCountryModel()->getIso3Code();
        $encryptData['sdState']             = $shippingAddress->getRegion();
        $encryptData['sdReadOnly']          = 'true';

        //Callback urls for paymentoperator
        $encryptData['URLSuccess'] = Mage::getUrl('paymentoperator/callback_yapital/success', array('_forced_secure' => true));
        $encryptData['URLFailure'] = Mage::getUrl('paymentoperator/callback_yapital/failure', array('_forced_secure' => true));
        $encryptData['URLNotify']  = Mage::getUrl('paymentoperator/callback_yapital/notify', array('_forced_secure' => true));
    }
}