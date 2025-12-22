<?php
namespace Softcode\CheckoutOverride\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;

class ValidateAndMapQuoteToOrder implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        /* =========================
           GLS VALIDATION
        ========================== */
        $glsMethod = $quote->getData('gls_method');

        if (!$glsMethod) {
            throw new LocalizedException(__('Please select a delivery method.'));
        }

        if ($glsMethod === 'gls_shop' && !$quote->getData('gls_shop_id')) {
            throw new LocalizedException(__('Please select a GLS pakkeshop.'));
        }

        /* =========================
           COMPANY VALIDATION
        ========================== */
        $companyType = $quote->getData('company_type');

        if (!$companyType) {
            throw new LocalizedException(__('Please select a company type.'));
        }

        if ($companyType === 'cvr' && !$quote->getData('company_cvr')) {
            throw new LocalizedException(__('CVR number is required.'));
        }

        if ($companyType === 'ean' && !$quote->getData('company_ean')) {
            throw new LocalizedException(__('EAN number is required.'));
        }

        /* =========================
           PAYMENT VALIDATION (FIXED)
        ========================== */
        $payment = $quote->getPayment();
        $method  = $payment ? $payment->getMethod() : null;

        if (!$method) {
            throw new LocalizedException(__('Please select a payment method.'));
        }

        switch ($companyType) {

            case 'privat':
            case 'cvr':
                if ($method !== 'epay') {
                    throw new LocalizedException(
                        __('Only ePay is allowed for this customer type.')
                    );
                }
                break;

            case 'ean':
                if (!in_array($method, ['epay', 'purchaseorder'], true)) {
                    throw new LocalizedException(
                        __('Selected payment method is not allowed for EAN orders.')
                    );
                }
                break;

            default:
                throw new LocalizedException(__('Invalid company type.'));
        }

        /* =========================
           MAP GLS DATA
        ========================== */
        $order->setData('gls_method',   $quote->getData('gls_method'));
        $order->setData('gls_shop_id',  $quote->getData('gls_shop_id'));
        $order->setData('gls_shop_name', $quote->getData('gls_shop_name'));
        $order->setData('gls_shop_address', $quote->getData('gls_shop_address'));

        /* =========================
           MAP COMPANY DATA
        ========================== */
        $order->setData('company_type', $quote->getData('company_type'));
        $order->setData('company_name', $quote->getData('company_name'));
        $order->setData('company_cvr',  $quote->getData('company_cvr'));
        $order->setData('company_ean',  $quote->getData('company_ean'));

        /* =========================
           MAP MAIN ADDRESS (REFERENCE)
        ========================== */
        $order->setData('main_street',   $quote->getData('main_street'));
        $order->setData('main_postcode', $quote->getData('main_postcode'));
        $order->setData('main_city',     $quote->getData('main_city'));

        /* =========================
           MAP ALT DELIVERY ADDRESS
        ========================== */
        $order->setData('alt_company',  $quote->getData('alt_company'));
        $order->setData('alt_receiver', $quote->getData('alt_receiver'));
        $order->setData('alt_street',   $quote->getData('alt_street'));
        $order->setData('alt_postcode', $quote->getData('alt_postcode'));
        $order->setData('alt_city',     $quote->getData('alt_city'));
    }
}
