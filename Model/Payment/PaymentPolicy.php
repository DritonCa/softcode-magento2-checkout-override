<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Model\Payment;

use Magento\Framework\Exception\LocalizedException;

/**
 * Single source of truth for which payment methods each buyer type may use.
 *
 * Buyer types:
 *  - privat : private individuals            → ePay only
 *  - cvr    : companies (Danish CVR number)  → ePay or purchase order (invoice)
 *  - ean    : public sector (Danish EAN)     → ePay or purchase order (invoice)
 *
 * The ruleset is injected (see di.xml) so it can be changed by configuration
 * without touching the controllers, observer or documentation that depend on it.
 */
class PaymentPolicy
{
    /**
     * @param array<string, string[]> $allowedMethods buyer type => allowed payment method codes
     */
    public function __construct(
        private readonly array $allowedMethods = [
            'privat' => ['epay'],
            'cvr' => ['epay', 'purchaseorder'],
            'ean' => ['epay', 'purchaseorder'],
        ]
    ) {
    }

    public function isKnownBuyerType(string $buyerType): bool
    {
        return array_key_exists($buyerType, $this->allowedMethods);
    }

    /**
     * @return string[]
     */
    public function allowedMethods(string $buyerType): array
    {
        return $this->allowedMethods[$buyerType] ?? [];
    }

    public function isAllowed(string $buyerType, string $method): bool
    {
        return in_array($method, $this->allowedMethods($buyerType), true);
    }

    /**
     * Guard a buyer-type / payment-method combination.
     *
     * @throws LocalizedException when the buyer type is unknown or the method is not permitted
     */
    public function assertAllowed(string $buyerType, string $method): void
    {
        if (!$this->isKnownBuyerType($buyerType)) {
            throw new LocalizedException(__('Please choose a valid buyer type before paying.'));
        }

        if (!$this->isAllowed($buyerType, $method)) {
            throw new LocalizedException(
                __('The selected payment method is not available for this buyer type.')
            );
        }
    }
}
