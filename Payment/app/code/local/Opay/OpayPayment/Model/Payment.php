<?php
class OPay_OpayPayment_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'opaypayment';
    protected $_formBlockType = 'opaypayment/form_opaypayment';
    protected $_infoBlockType = 'payment/info';

    protected $_isGateway                   = true;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canCaptureOnce              = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canReviewPayment            = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_canManageRecurringProfiles  = true;

    private $moduleName = 'opaypayment';
    private $prefix = 'opay_';
    private $libraryList = array('OpayCartLibrary.php');

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl($this->moduleName . '/payment/redirect', array('_secure' => false));
    }

    public function assignData($data)
    {
        $opayHelper = Mage::helper($this->moduleName . '');
        $opayHelper->destroyChoosenPayment();
        $choosenPayment = $data->getOpayChoosenPayment();
        $opayHelper->setChoosenPayment($choosenPayment);
        return $this;
    }

    public function getValidPayments()
    {
        $payments = $this->getOpayConfig('payment_methods', true);
        $trimed = trim($payments);
        return explode(',', $trimed);
    }

    public function isValidPayment($choosenPayment)
    {
        $payments = $this->getValidPayments();
        return (in_array($choosenPayment, $payments));
    }

    public function getOpayConfig($name)
    {
        return $this->getMagentoConfig($this->prefix . $name);
    }

    public function getMagentoConfig($name)
    {
        return $this->getConfigData($name);
    }

    public function loadLibrary() {
        foreach ($this->libraryList as $path) {
            include_once($path);
        }
    }

    public function getHelper() {
        $merchant_id = $this->getOpayConfig('merchant_id');
        return new OpayCartLibrary(array('merchantId' => $merchant_id));
    }

    public function getModuleUrl($action = '')
    {
        if ($action !== '') {
            $route = $this->_code . '/payment/' . $action;
        } else {
            $route = '';
        }
        return $this->getMagentoUrl($route);
    }

    public function getMagentoUrl($route)
    {
        return Mage::getUrl($route);
    }
}
