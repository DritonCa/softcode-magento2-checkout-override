<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Plugin\Checkout;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\PaymentFailuresInterface;
use Psr\Log\LoggerInterface;
use Softcode\CheckoutOverride\Model\Config;

/**
 * Logs a failed payment attempt instead of emailing it (configurable).
 *
 * Magento's "payment failed" email renders the shopper's own address fields. Bots
 * abuse that by filling the address with template directives such as
 * {{block class=...}} so the mail renders them. Strict mode stops execution, but a
 * real inbox still fills up with attacker-controlled text. The attempt is written
 * to the log instead; set "Send payment failed email" to Yes to restore the mail.
 */
class SuppressPaymentFailedEmail
{
    public function __construct(
        private readonly Config $config,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundHandle(
        PaymentFailuresInterface $subject,
        callable $proceed,
        int $cartId,
        string $message,
        string $checkoutType = 'onepage'
    ) {
        $this->record($cartId, $message, $checkoutType);

        if ($this->config->isPaymentFailedEmailEnabled()) {
            return $proceed($cartId, $message, $checkoutType);
        }
        return $subject;
    }

    /**
     * The address is deliberately left out — it is the untrusted part.
     */
    private function record(int $cartId, string $message, string $checkoutType): void
    {
        $context = ['cart_id' => $cartId, 'checkout' => $checkoutType, 'reason' => $message];
        try {
            $quote = $this->cartRepository->get($cartId);
            $context['email'] = (string)$quote->getCustomerEmail();
            $context['total'] = round((float)$quote->getGrandTotal(), 2);
        } catch (\Throwable $e) {
            // The cart may be gone; the line is still worth writing.
        }
        $this->logger->warning('Payment failed', $context);
    }
}
