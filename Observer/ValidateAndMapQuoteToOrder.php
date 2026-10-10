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
 *  - the buyer type and its required CVR/EAN number are present and well-formed, and
 *  - the chosen payment method is allowed for that buyer type (central PaymentPolicy).
 *
 * It then copies the buyer-type and delivery fields from the quote onto the order.
 * Shipping validation is left to the shipping module, so this checkout has no hidden
 * dependency on it.
 *
 * Bound to sales_model_service_quote_submit_before.
 */
class ValidateAndMapQuoteToOrder implements ObserverInterface
{
    /** Danish EAN location numbers (public-sector e-invoicing) are 13 digits. */
    private const EAN_LENGTH = 13;

    private const ORDER_FIELDS = [
        'company_type', 'company_name', 'company_cvr', 'company_ean',
        'main_street', 'main_postcode', 'main_city',
        'alt_company', 'alt_receiver', 'alt_street', 'alt_postcode', 'alt_city',
    ];

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

        $buyerType = (string)$quote->getData('company_type');
        if ($buyerType === '') {
            throw new LocalizedException(__('Please select a buyer type.'));
        }
        if ($buyerType === 'cvr' && !$quote->getData('company_cvr')) {
            throw new LocalizedException(__('A CVR number is required.'));
        }
        if ($buyerType === 'ean') {
            $ean = preg_replace('/\s+/', '', (string)$quote->getData('company_ean'));
            if ($ean === '') {
                throw new LocalizedException(__('An EAN number is required.'));
            }
            if (!preg_match('/^\d{' . self::EAN_LENGTH . '}$/', $ean)) {
                throw new LocalizedException(__(
                    'The EAN number must be exactly %1 digits. You entered %2.',
                    self::EAN_LENGTH,
                    mb_strlen($ean)
                ));
            }
        }

        $method = (string)($quote->getPayment() ? $quote->getPayment()->getMethod() : '');
        if ($method === '') {
            throw new LocalizedException(__('Please select a payment method.'));
        }

        // Single source of truth — same policy the controllers validated against.
        $this->paymentPolicy->assertAllowed($buyerType, $method);

        foreach (self::ORDER_FIELDS as $field) {
            $order->setData($field, $quote->getData($field));
        }
    }
}
