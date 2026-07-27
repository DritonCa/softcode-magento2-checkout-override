<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Softcode\CheckoutOverride\Model\Payment\PaymentPolicy;

/**
 * Final server-side gate before an order is created:
 *  - the buyer type and its required company/EAN number are present, and
 *  - the chosen payment method is allowed for that buyer type (central PaymentPolicy).
 *
 * It then copies the buyer-type fields from the quote onto the order. Shipping
 * (GLS) validation and mapping are intentionally left to the shipping module, so
 * this checkout has no hidden dependency on it.
 *
 * Bound to sales_model_service_quote_submit_before.
 */
class ValidateAndMapQuoteToOrder implements ObserverInterface
{
    public function __construct(
        private readonly PaymentPolicy $paymentPolicy
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        $buyerType = (string) $quote->getData('company_type');
        if ($buyerType === '') {
            throw new LocalizedException(__('Please select a buyer type.'));
        }
        if ($buyerType === 'cvr' && !$quote->getData('company_cvr')) {
            throw new LocalizedException(__('A CVR number is required.'));
        }
        if ($buyerType === 'ean' && !$quote->getData('company_ean')) {
            throw new LocalizedException(__('An EAN number is required.'));
        }

        $method = (string) ($quote->getPayment() ? $quote->getPayment()->getMethod() : '');
        if ($method === '') {
            throw new LocalizedException(__('Please select a payment method.'));
        }

        // Single source of truth — same policy the controllers validated against.
        $this->paymentPolicy->assertAllowed($buyerType, $method);

        foreach (['company_type', 'company_name', 'company_cvr', 'company_ean'] as $field) {
            $order->setData($field, $quote->getData($field));
        }
    }
}
