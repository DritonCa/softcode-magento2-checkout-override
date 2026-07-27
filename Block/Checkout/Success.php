<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Block\Checkout;

use Magento\Checkout\Block\Onepage\Success as MagentoSuccess;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;

/**
 * View model / block for the custom order-success page.
 *
 * Exposes a small, presentation-ready view of the placed order (items, totals,
 * addresses, buyer type) so the template stays free of business logic.
 */
class Success extends MagentoSuccess
{
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderConfig $orderConfig,
        HttpContext $httpContext,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
    }

    public function getOrder(): ?Order
    {
        $order = $this->_checkoutSession->getLastRealOrder();
        return $order->getId() ? $order : null;
    }

    public function getOrderDate(): string
    {
        $order = $this->getOrder();
        return $order ? (new \DateTime($order->getCreatedAt()))->format('d-m-Y') : '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrderItems(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'name' => (string) $item->getName(),
                'qty' => (int) $item->getQtyOrdered(),
                'price' => $this->fmt((float) $item->getPriceInclTax()),
                'total' => $this->fmt((float) $item->getRowTotalInclTax()),
                'tax' => $this->fmt((float) $item->getTaxAmount()),
            ];
        }

        return $items;
    }

    public function getShippingMethodLabel(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        return (string) ($order->getShippingDescription() ?: $order->getShippingMethod());
    }

    public function getPaymentTitle(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }
        try {
            return (string) $order->getPayment()->getMethodInstance()->getTitle();
        } catch (\Throwable) {
            return (string) $order->getPayment()->getMethod();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrderTotals(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $discount = abs((float) $order->getDiscountAmount());

        return [
            'subtotal' => $this->fmt((float) $order->getSubtotal()),
            'discount' => $discount > 0 ? $this->fmt($discount) : null,
            'shipping' => $this->fmt((float) $order->getShippingInclTax()),
            'tax' => $this->fmt((float) $order->getTaxAmount()),
            'grand_total' => $this->fmt((float) $order->getGrandTotal()),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBillingAddress(): ?array
    {
        $order = $this->getOrder();
        $address = $order?->getBillingAddress();
        if (!$address) {
            return null;
        }

        return [
            'name' => trim($address->getFirstname() . ' ' . $address->getLastname()),
            'street' => implode(', ', $address->getStreet()),
            'postcode' => (string) $address->getPostcode(),
            'city' => (string) $address->getCity(),
            'email' => (string) ($address->getEmail() ?: $order->getCustomerEmail()),
            'phone' => (string) $address->getTelephone(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getShippingAddress(): ?array
    {
        $order = $this->getOrder();
        $address = $order?->getShippingAddress();
        if (!$address) {
            return null;
        }

        return [
            'name' => trim($address->getFirstname() . ' ' . $address->getLastname()),
            'street' => implode(', ', $address->getStreet()),
            'postcode' => (string) $address->getPostcode(),
            'city' => (string) $address->getCity(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getCompanyData(): array
    {
        $order = $this->getOrder();
        if (!$order) {
            return [];
        }

        $billing = $order->getBillingAddress();

        return [
            'type' => (string) $order->getData('company_type'),
            'name' => (string) $order->getData('company_name'),
            'cvr' => (string) $order->getData('company_cvr'),
            'ean' => (string) $order->getData('company_ean'),
            'ref' => $billing ? trim($billing->getFirstname() . ' ' . $billing->getLastname()) : '',
        ];
    }

    /**
     * Store name from configuration, for headings / footers.
     */
    public function getStoreName(): string
    {
        return (string) $this->_scopeConfig->getValue(
            'general/store_information/name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    private function fmt(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }
}
