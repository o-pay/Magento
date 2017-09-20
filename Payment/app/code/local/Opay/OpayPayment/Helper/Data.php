<?php

include_once('Library/AllPay.Payment.Integration.php');
include_once('Library/OpayCartLibrary.php');

class Opay_OpayPayment_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $paymentModel = null;
    private $prefix = 'opay_';
    private $moduleName = 'opaypayment';
    private $resultNotify = true;
    private $obtainCodeNotify = false;

    private $errorMessages = array();

    public function __construct()
    {
        $this->paymentModel = Mage::getModel($this->moduleName . '/payment');
        $this->errorMessages = array(
            'invalidPayment' => $this->__($this->prefix . 'payment_checkout_invalid_payment'),
            'invalidOrder' => $this->__($this->prefix . 'payment_checkout_invalid_order'),
        );
    }

    public function getPaymentGatewayUrl()
    {
        return Mage::getUrl($this->moduleName . '/payment/gateway', array('_secure' => false));
    }

    public function getPostPaymentParameter($name)
    {
        $posts = Mage::app()->getRequest()->getParams();
        return $posts['payment'][$name];
    }

    public function setChoosenPayment($choosenPayment)
    {
        $this->getCheckoutSession()->setOpayChoosenPayment($choosenPayment);
    }

    public function getChoosenPayment()
    {
        $session = $this->getCheckoutSession();
        if (empty($session->getOpayChoosenPayment()) === true) {
            return '';
        } else {
            return $session->getOpayChoosenPayment();
        }
    }

    public function destroyChoosenPayment()
    {
        $this->getCheckoutSession()->unsOpayChoosenPayment();
    }

    public function isValidPayment($choosenPayment)
    {
        return $this->paymentModel->isValidPayment($choosenPayment);
    }

    public function getErrorMessage($name, $value)
    {
        $message = $this->errorMessages[$name];
        if ($value !== '') {
            return sprintf($message, $value);
        } else {
            return $message;
        }
    }

    public function getRedirectHtml($posts)
    {
        try {
            $this->paymentModel->loadLibrary();
            $sdkHelper = $this->paymentModel->getHelper();
            // Validate choose payment
            $choosenPayment = $this->getChoosenPayment();
            if ($this->isValidPayment($choosenPayment) === false) {
                throw new Exception($this->getErrorMessage('invalidPayment', $choosenPayment));
            }

            // Validate the order id
            $orderId = $this->getOrderId();
            if (!$orderId) {
                throw new Exception($this->getErrorMessage('invalidOrder', ''));
            }

            // Update order status and comments
            $order = $this->getOrder($orderId);
            $createStatus = $this->paymentModel->getOpayConfig('create_status');
            $pattern = $this->__($this->prefix . 'payment_order_comment_payment_method');
            $paymentName = $this->getPaymentTranslation($choosenPayment);
            $comment = sprintf($pattern, $paymentName);
            $order->setState($createStatus, $createStatus, $comment, false)->save();

            $checkoutSession = $this->getCheckoutSession();
            $checkoutSession->setOpayPaymentQuoteId($checkoutSession->getQuoteId());
            $checkoutSession->setOpayPaymentRealOrderId($orderId);
            $checkoutSession->getQuote()->setIsActive(false)->save();
            $checkoutSession->clear();

            // Checkout
            $helperData = array(
                'choosePayment' => $choosenPayment,
                'hashKey' => $this->paymentModel->getOpayConfig('hash_key'),
                'hashIv' => $this->paymentModel->getOpayConfig('hash_iv'),
                'returnUrl' => $this->paymentModel->getModuleUrl('response'),
                'clientBackUrl' =>$this->paymentModel->getMagentoUrl('sales/order/view/order_id/' . $orderId),
                'orderId' => $orderId,
                'total' => $order->getGrandTotal(),
                'itemName' => $this->__($this->prefix . 'payment_redirect_text_item_name'),
                'version' => $this->prefix . 'module_magento_2.0.0821',
            );
            $sdkHelper->checkout($helperData);
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
        return ;
    }

    public function getPaymentResult()
    {
        $resultMessage = '1|OK';
        $error = '';
        try {
            $this->paymentModel->loadLibrary();
            $sdkHelper = $this->paymentModel->getHelper();

            // Get valid feedback
            $helperData = array(
                'hashKey' => $this->paymentModel->getOpayConfig('hash_key'),
                'hashIv' => $this->paymentModel->getOpayConfig('hash_iv'),
            );
            $feedback = $sdkHelper->getValidFeedback($helperData);
            unset($helperData);

            $orderId = $sdkHelper->getOrderId($feedback['MerchantTradeNo']);
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

            // Check transaction amount and currency
            if ($this->paymentModel->getMagentoConfig('use_store_currency')) {
                $orderTotal = $order->getGrandTotal();
                $currency = $order->getOrderCurrencyCode();
            } else {
                $orderTotal = $order->getBaseGrandTotal();
                $currency = $order->getBaseCurrencyCode();
            }

            // Check the amounts
            if ($sdkHelper->validAmount($feedback['TradeAmt'], $orderTotal) === false) {
                throw new Exception($sdkHelper->getAmountError($orderId));
            }

            // Get the response status
            $orderStatus = $order->getState();
            $createStatus = $this->paymentModel->getOpayConfig('create_status');
            $helperData = array(
                'validStatus' => ($orderStatus === $createStatus),
                'orderId' => $orderId,
            );
            $responseStatus = $sdkHelper->getResponseStatus($feedback, $helperData);
            unset($helperData);

            // Update the order status
            $patterns = array(
                2 => $this->__($this->prefix . 'payment_order_comment_atm'),
                3 => $this->__($this->prefix . 'payment_order_comment_cvs'),
                4 => $this->__($this->prefix . 'payment_order_comment_barcode'),
            );
            switch($responseStatus) {
                // Paid
                case 1:
                    $status = $this->paymentModel->getOpayConfig('success_status');
                    $pattern = $this->__($this->prefix . 'payment_order_comment_payment_result');
                    $comment = $sdkHelper->getPaymentSuccessComment($pattern, $feedback);
                    $order->setState($status, $status, $comment, $this->resultNotify)->save();
                    unset($status, $pattern, $comment);
                    break;
                case 2:// ATM get code
                case 3:// CVS get code
                case 4:// Barcode get code
                    $status = $orderStatus;
                    $pattern = $patterns[$responseStatus];
                    $comment = $sdkHelper->getObtainingCodeComment($pattern, $feedback);
                    $order->setState($status, $status, $comment, $this->obtainCodeNotify)->save();
                    unset($status, $pattern, $comment);
                    break;
                default:
            }
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
            $this->_getCheckout()->addError($error);
        } catch (Exception $e) {
            $error = $e->getMessage();
            Mage::logException($e);
        }

        if ($error !== '') {
            if (is_null($orderId) === false) {
                $status = $this->paymentModel->getOpayConfig('failed_status');
                $pattern = $this->__($this->prefix . 'payment_order_comment_payment_failure');
                $comment = $sdkHelper->getFailedComment($pattern, $error);
                $order->setState($status, $status, $comment, $this->resultNotify)->save();
                unset($status, $pattern, $comment);
            }
            
            // Set the failure result
            $resultMessage = '0|' . $error;
        }
        echo $resultMessage;
        exit;
    }

    public function getPaymentTranslation($payment)
    {
        return $this->__($this->prefix . 'payment_text_' . strtolower($payment));
    }


    private function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    private function getOrderId()
    {
        return $this->getCheckoutSession()->getLastRealOrderId();
    }

    private function getOrder($orderId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }
}