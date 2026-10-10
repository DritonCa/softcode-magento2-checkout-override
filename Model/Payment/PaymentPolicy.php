<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Softcode\CheckoutOverride\Model\Config;

/**
 * Single source of truth for which payment methods each buyer type may use.
 *
 * Buyer types: privat (private), cvr (company), ean (public sector).
 *
 * Rules come from the admin (Sales > Checkout > Softcode Checkout > Payment rules).
 * When the admin has none, the `allowedMethods` argument from di.xml is used. A buyer
 * type without rules may use every payment method that Magento makes available.
 */
class PaymentPolicy
{
    /**
     * @param array<string, string[]> $allowedMethods fallback rules: buyer type => method codes
     */
    public function __construct(
        private readonly array $allowedMethods = [],
        private readonly ?Config $config = null
    ) {
    }

    public function isKnownBuyerType(string $buyerType): bool
    {
        return in_array($buyerType, Config::BUYER_TYPES, true);
    }

    /**
     * @return string[]|null allowed method codes, or null when the buyer type is unrestricted
     */
    public function allowedMethods(string $buyerType): ?array
    {
        if (!$this->isKnownBuyerType($buyerType)) {
            return [];
        }
        $rules = $this->config?->getPaymentRules() ?: $this->allowedMethods;
        $methods = array_values((array)($rules[$buyerType] ?? []));
        return $methods !== [] ? $methods : null;
    }

    public function isAllowed(string $buyerType, string $method): bool
    {
        if ($method === '' || !$this->isKnownBuyerType($buyerType)) {
            return false;
        }
        $allowed = $this->allowedMethods($buyerType);
        return $allowed === null || in_array($method, $allowed, true);
    }

    /**
     * @throws LocalizedException when the buyer type is unknown or the method is not permitted
     */
    public function assertAllowed(string $buyerType, string $method): void
    {
        if (!$this->isKnownBuyerType($buyerType)) {
            throw new LocalizedException(__('Please choose a valid buyer type before paying.'));
        }
        if (!$this->isAllowed($buyerType, $method)) {
            throw new LocalizedException(__('The selected payment method is not available for this buyer type.'));
        }
    }
}
