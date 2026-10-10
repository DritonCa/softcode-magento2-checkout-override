<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Plugin\Email;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Softcode\CheckoutOverride\Model\Config;

/**
 * Adds a Reply-To to order-lifecycle emails.
 *
 * Order mail is often sent from an address the mail host is allowed to send for,
 * which is not the address customers should reply to. Reply-To is not checked by
 * SPF, DKIM or DMARC, so it can carry the shop's own domain without risking delivery.
 *
 * Only the order confirmation and Magento's invoice/shipment/credit memo mails are
 * touched, so a Reply-To that another mail (e.g. the contact form) sets is kept.
 */
class SalesEmailReplyTo
{
    private ?string $templateId = null;

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function afterSetTemplateIdentifier(TransportBuilder $subject, $result, $templateIdentifier)
    {
        $this->templateId = (string)$templateIdentifier;
        return $result;
    }

    public function beforeGetTransport(TransportBuilder $subject): void
    {
        $replyTo = $this->config->getReplyTo();
        if ($replyTo !== '' && $this->isOrderEmail()) {
            $subject->setReplyTo(
                $replyTo,
                (string)$this->scopeConfig->getValue('trans_email/ident_sales/name') ?: null
            );
        }
        // getTransport() resets the builder; do not leak the id into the next message.
        $this->templateId = null;
    }

    private function isOrderEmail(): bool
    {
        $id = (string)$this->templateId;
        return $id === 'softcode_order_new' || str_starts_with($id, 'sales_email_');
    }
}
