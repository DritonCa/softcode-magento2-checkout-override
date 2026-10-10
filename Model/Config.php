<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to Stores > Configuration > Sales > Checkout > Softcode Checkout.
 */
class Config
{
    public const BUYER_TYPES = ['privat', 'cvr', 'ean'];

    private const BASE = 'softcode_checkout/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Payment methods allowed per buyer type, as configured in the admin. Buyer types
     * with nothing selected are left out (meaning: no restriction).
     *
     * @return array<string, string[]>
     */
    public function getPaymentRules(): array
    {
        $rules = [];
        foreach (self::BUYER_TYPES as $type) {
            $codes = array_values(array_filter(array_map(
                'trim',
                explode(',', $this->value('payment/allowed_' . $type))
            )));
            if ($codes !== []) {
                $rules[$type] = $codes;
            }
        }
        return $rules;
    }

    public function isCartRedirectEnabled(): bool
    {
        return $this->flag('general/redirect_cart');
    }

    public function getDefaultCountry(): string
    {
        $country = $this->value('general/country') ?: (string)$this->scopeConfig->getValue(
            'general/country/default',
            ScopeInterface::SCOPE_STORE
        );
        return strtoupper($country ?: 'DK');
    }

    public function getReplyTo(): string
    {
        return $this->value('email/reply_to');
    }

    public function isPaymentFailedEmailEnabled(): bool
    {
        return $this->flag('email/payment_failed_enabled');
    }

    private function value(string $path): string
    {
        return trim((string)$this->scopeConfig->getValue(self::BASE . $path, ScopeInterface::SCOPE_STORE));
    }

    private function flag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag(self::BASE . $path, ScopeInterface::SCOPE_STORE);
    }
}
