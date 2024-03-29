<?php
/**
 * Copyright (c) 2008-2011 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Contributors:
 * Erik Wohllebe - initial contents
 */
class Dotsource_Paymentoperator_Model_Payment_Billpay_Purchaseonaccount
    extends Dotsource_Paymentoperator_Model_Payment_Billpay_Abstract
{

    /** Holds the payment code */
    const CODE                      = "paymentoperator_billpay_purchaseonaccount";
    protected $_code                = self::CODE;

    /** Holds the block source path */
    protected $_formBlockType       = 'paymentoperator/form_billpay_purchaseonaccount';

    /** Holds the info source path */
    protected $_infoBlockType       = 'paymentoperator/info_billpay_purchaseonaccount';

    /** Holds the path to the request models */
    protected $_requestModelInfo    = 'paymentoperator/payment_request_billpay_purchaseonaccount_';


    /**
     * Return the billpay action.
     *
     * @return  string
     */
    function getBillpayAction()
    {
        return "1";
    }

    /**
     * Has the real implementation of the authorize process.
     *
     * @param   Varien_Object                                       $payment
     * @param   float                                               $amount
     * @param   boolean                                             $setTransactionInformsations
     * @return  Dotsource_Paymentoperator_Model_Payment_Response_Response
     */
    protected function _billpayAuthorize(
        Varien_Object $payment,
        $amount,
        $setTransactionInformsations = true
    )
    {
        //Get the request model
        $request = $this->_createRequestModel('authorize', $payment);

        //No need to close the transaction in any case (auth, capture)
        if ($setTransactionInformsations) {
            $payment->setIsTransactionClosed(false);
        }

        //Set the amount
        $request->setAmount($amount);

        //Send the capture request to paymentoperator
        $this->_getConnection()->sendRequest($request);

        //Check if the transaction has produce an error
        if (!$request->getResponseModel()->hasError()) {
            //Add transaction information
            if ($setTransactionInformsations) {
                $this->_getHelper()->getPaymentHelper()->addTransactionInfoToPayment(
                    $payment,
                    $request->getResponseModel()
                );
            }

            //Dispatch event success event
            Mage::dispatchEvent("{$this->getCode()}_authorize_success", array(
                'method'    => $this,
                'request'   => $request,
                'response'  => $request->getResponseModel(),
            ));

            //Return the request model
            return $request->getResponseModel();
        } else {
            $this->_getHelper()->getPaymentHelper()
                ->getPaymentErrorManager('authorize')
                ->processErrorCode($request, $this->getCode());
        }
    }

    /**
     * @see Mage_Payment_Model_Method_Abstract::_billpayCapture()
     *
     * @param   Varien_Object                               $payment
     * @param   float                                       $amount
     * @param   Mage_Sales_Model_Order_Payment_Transaction  $authorizationTransaction
     */
    protected function _billpayCapture(
        Varien_Object $payment,
        $amount,
        Mage_Sales_Model_Order_Payment_Transaction $authorizationTransaction = null
    )
    {
        //Check if we have a valid authorization
        $hasAvailableAuthorization = $this->_canCaptureAuthorization($payment, $authorizationTransaction);
        if (!$hasAvailableAuthorization) {
            $this->_getHelper()->getPaymentHelper()
                ->getPaymentErrorManager('capture')
                ->processMessage('There is no open authorization to capture.');
        }

        //Get the authorization
        if (null === $authorizationTransaction) {
            $authorizationTransaction = $payment->getAuthorizationTransaction();
        }

        //Get the request model
        $request = $this->_createRequestModel('capture', $payment);

        //Set the authorization transaction model and the invoice amount
        $request
            ->setReferencedTransactionModel($authorizationTransaction)
            ->setAmount($amount);

        //Send the capture request to paymentoperator
        $this->_getConnection()->sendRequest($request);

        //Check if the transaction has produce an error
        if (!$request->getResponseModel()->hasError()) {
            //Add transaction information
            $this->_getHelper()->getPaymentHelper()->addTransactionInfoToPayment(
                $payment,
                $request->getResponseModel()
            );

            //Don't close the capture transaction we are available to refund
            $payment->setIsTransactionClosed(false);

            //Dispatch event success event
            Mage::dispatchEvent("{$this->getCode()}_capture_success", array(
                'method'    => $this,
                'request'   => $request,
                'response'  => $request->getResponseModel(),
            ));
        } else {
            $this->_getHelper()->getPaymentHelper()
                ->getPaymentErrorManager('capture')
                ->processErrorCode($request, $this->getCode());
        }
    }
}