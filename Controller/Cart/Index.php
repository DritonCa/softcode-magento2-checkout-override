<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Returns the current cart contents and totals as JSON for the checkout summary.
 *
 * Totals come straight from the quote's own collectors, so store tax settings,
 * discounts and shipping are respected without any hard-coded assumptions.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId() || !$quote->getItemsCount()) {
            return $result->setData($this->emptyCart());
        }

        return $result->setData([
            'success' => true,
            'items' => $this->mapItems($quote),
            'totals' => $this->mapTotals($quote),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapItems(CartInterface $quote): array
    {
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'quote_item_id' => (int) $item->getItemId(),
                'name' => (string) $item->getName(),
                'qty' => (int) $item->getQty(),
                'row_total' => $this->format((float) $item->getRowTotalInclTax()),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTotals(CartInterface $quote): array
    {
        $address = $quote->getShippingAddress();
        $discount = abs((float) $address->getDiscountAmount());

        return [
            'subtotal_excl_tax' => $this->format((float) $quote->getSubtotal()),
            'discount' => $this->format($discount),
            'has_discount' => $discount > 0.0,
            'shipping' => $this->format((float) $address->getShippingInclTax()),
            'tax' => $this->format((float) $address->getTaxAmount()),
            'grand_total' => $this->format((float) $quote->getGrandTotal()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCart(): array
    {
        $zero = $this->format(0.0);

        return [
            'success' => true,
            'items' => [],
            'totals' => [
                'subtotal_excl_tax' => $zero,
                'discount' => $zero,
                'has_discount' => false,
                'shipping' => $zero,
                'tax' => $zero,
                'grand_total' => $zero,
            ],
        ];
    }

    private function format(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }
}
