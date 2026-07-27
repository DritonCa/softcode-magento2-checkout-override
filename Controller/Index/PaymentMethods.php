<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Returns the payment methods available for the current quote and store.
 */
class PaymentMethods implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly PaymentHelper $paymentHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                return $result->setData(['success' => true, 'methods' => []]);
            }

            $storeId = (int) $this->storeManager->getStore()->getId();

            $methods = [];
            foreach ($this->paymentHelper->getStoreMethods($storeId, $quote) as $method) {
                if ($method->isAvailable($quote)) {
                    $methods[] = ['code' => $method->getCode(), 'title' => $method->getTitle()];
                }
            }

            return $result->setData(['success' => true, 'methods' => $methods]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout paymentMethods failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'error' => __('Payment methods could not be loaded.')]);
        }
    }
}
