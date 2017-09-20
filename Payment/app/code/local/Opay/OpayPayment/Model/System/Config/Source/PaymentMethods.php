<?php

class Opay_OpayPayment_Model_System_Config_Source_PaymentMethods
{
    public function toOptionArray()
    {
        $optionArray = array();
        $list = array(
            'credit',
            'credit_3',
            'credit_6',
            'credit_12',
            'credit_18',
            'credit_24',
            'webatm',
            'atm',
            'cvs',
            'tenpay',
            'topupused',
        );
        foreach ($list as $payment) {
            array_push($optionArray, array('value' => $payment, 'label' => Mage::helper('adminhtml')->__('opay_payment_text_' . $payment)));
        }
        return $optionArray;
    }
}