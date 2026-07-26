<?php
namespace Softcode\CheckoutOverride\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CheckoutSession $checkoutSession,
        private PriceHelper $priceHelper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $quote  = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            return $result->setData([
                'success' => false,
                'error'   => 'No active cart'
            ]);
        }

        /* ===========================
           ITEMS
        =========================== */
        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'name'  => $item->getName(),
                'qty'   => (int)$item->getQty(),
                'price' => $this->priceHelper->currency(
                    $item->getRowTotalInclTax(),
                    true,
                    false
                )
            ];
        }

        /* ===========================
           TOTALS (NULL-SAFE)
        =========================== */
        $address = $quote->getShippingAddress();

        // Normalize nullable values (VERY IMPORTANT)
        $subtotal       = (float)($quote->getSubtotal() ?? 0);
        $taxAmount      = (float)($address->getTaxAmount() ?? 0);
        $discountAmount = (float)($address->getDiscountAmount() ?? 0);
        $shippingAmount = (float)($address->getShippingAmount() ?? 0);
        $grandTotal     = (float)($quote->getGrandTotal() ?? 0);

        return $result->setData([
            'success' => true,
            'items'   => $items,
            'totals'  => [
                'subtotal_excl_tax' => $this->priceHelper->currency($subtotal, true, false),
                'tax'               => $this->priceHelper->currency($taxAmount, true, false),
                'discount'          => $this->priceHelper->currency(abs($discountAmount), true, false),
                'has_discount'      => abs($discountAmount) > 0,
                'shipping'          => $this->priceHelper->currency($shippingAmount, true, false),
                'grand_total'       => $this->priceHelper->currency($grandTotal, true, false),
            ]
        ]);
    }
}
