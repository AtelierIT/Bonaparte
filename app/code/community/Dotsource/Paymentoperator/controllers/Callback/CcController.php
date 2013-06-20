<?php
/**
 * Copyright (c) 2008-2010 dotSource GmbH.
 * All rights reserved.
 * http://www.dotsource.de
 *
 * Contributors:
 * Erik Wohllebe - initial contents
 */
class Dotsource_Paymentoperator_Callback_CcController
    extends Dotsource_Paymentoperator_Controller_Paymentoperatorcallback
{

    /** Enable javascript redirect for breaking out the iframe */
    protected $_javaScriptRedirect = true;


    /**
     * @see Dotsource_Paymentoperator_Controller_Paymentoperatorcallback::_getPaymentCode()
     *
     * @return string
     */
    protected function _getPaymentCode()
    {
        return 'paymentoperator_cc';
    }

    /**
     * @see Dotsource_Paymentoperator_Controller_Paymentoperatorcallback::_notifySuccessProcessing()
     *
     * @param Dotsource_Paymentoperator_Model_Payment_Response_Response $response
     */
    protected function _notifySuccessProcessing(Dotsource_Paymentoperator_Model_Payment_Response_Response $response)
    {
        //Do the parent stuff first
        parent::_notifySuccessProcessing($response);

        /* @var $order Mage_Sales_Model_Order */
        $order      = Mage::registry('paymentoperator_notify_order');
        $payment    = $order->getPayment();

        //Check for storing pseudo data
        if (!$payment->getMethodInstance()->getConfigData('use_pseudo_data', $order->getStoreId())) {
            return;
        }

        //Try to store pseudo cc data
        if (!$response->getResponse()->emptyPcnr()) {
            $payment
                ->setCcNumberEnc(
                    Mage::helper('core')->getEncryptor()->encrypt(
                        $response->getResponse()->getPcnr()
                    )
                )
                ->setCcLast4(substr($response->getResponse()->getPcnr(), -3))
                ->setCcExpYear(substr($response->getResponse()->getCcexpiry(), 0, 4))
                ->setCcExpMonth(substr($response->getResponse()->getCcexpiry(), -2))
                ->setCcType($response->getResponse()->getCcbrand());
        }
    }
}