<?php
namespace Softcode\CheckoutOverride\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Psr\Log\LoggerInterface;

/**
 * Injicerer varelinjerne som en færdig HTML-variabel ({{var items_html}}) i
 * ordre-bekræftelses-mailen. Bruges frem for {{layout handle=...}}, som ikke
 * renderer pålideligt i denne mail. Tabellen matcher success-siden og renderes
 * via en generisk Template-blok med modul-templaten
 * Softcode_CheckoutOverride::email/order_items.phtml — ingen afhængighed af
 * tema-resolution eller Sales-renderer-lister.
 */
class AddOrderEmailItems implements ObserverInterface
{
    public function __construct(
        private LayoutInterface $layout,
        private LoggerInterface $logger
    ) {}

    public function execute(Observer $observer): void
    {
        $transport = $observer->getData('transportObject') ?: $observer->getData('transport');
        if (!$transport) {
            return;
        }
        $order = $transport->getData('order');
        if (!$order) {
            return;
        }

        // Formaterede totaler som færdige strenge — |price-filteret virker ikke
        // i denne mail (renderer rå floats). formatPriceTxt() giver samme
        // format som success-sidens priceHelper->currency() ("125,00 ,-").
        $discount = abs((float)$order->getDiscountAmount());
        $transport->setData('total_discount', $discount > 0 ? $order->formatPriceTxt($discount) : '');
        $transport->setData('total_shipping', $order->formatPriceTxt((float)$order->getShippingInclTax()));
        $transport->setData('total_tax', $order->formatPriceTxt((float)$order->getTaxAmount()));
        $transport->setData('total_grand', $order->formatPriceTxt((float)$order->getGrandTotal()));

        try {
            $html = $this->layout
                ->createBlock(\Magento\Framework\View\Element\Template::class)
                ->setData('order', $order)
                ->setTemplate('Softcode_CheckoutOverride::email/order_items.phtml')
                ->toHtml();
            $transport->setData('items_html', $html);
        } catch (\Throwable $e) {
            $this->logger->error('Softcode_CheckoutOverride: order email items render failed: ' . $e->getMessage());
        }
    }
}
