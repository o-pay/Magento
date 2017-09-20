<?php

class Opay_OpayPayment_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function redirectAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'opaypayment',
            array('template' => 'opaypayment/redirect.phtml')
        );
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    public function responseAction()
    {
        echo Mage::helper('opaypayment')->getPaymentResult();
        exit;
    }
}