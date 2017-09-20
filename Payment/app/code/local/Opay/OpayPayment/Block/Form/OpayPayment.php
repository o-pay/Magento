<?php

class Opay_OpayPayment_Block_Form_OpayPayment extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('opaypayment/form/payment.phtml');
    }
}