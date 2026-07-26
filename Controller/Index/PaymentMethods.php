<?php
namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\StoreManagerInterface;

class PaymentMethods extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CheckoutSession $checkoutSession,
        private PaymentHelper $paymentHelper,
        private StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                throw new \Exception('No active quote');
            }

            // 🔑 Correct store context
            $storeId = (int) $this->storeManager->getStore()->getId();


            // ✅ Magento-native way
            $methods = $this->paymentHelper->getStoreMethods(
                $storeId,
                $quote
            );

            $data = [];

            foreach ($methods as $method) {
                if (!$method->isAvailable($quote)) {
                    continue;
                }

                $data[] = [
                    'code'  => $method->getCode(),
                    'title' => $method->getTitle()
                ];
            }

            return $result->setData([
                'success' => true,
                'methods' => $data
            ]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
