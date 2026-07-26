<?php
namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;

class SavePayment extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $method = (string)$this->getRequest()->getParam('method');
            if (!$method) {
                throw new \Exception('Missing payment method');
            }

            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                throw new \Exception('No active quote');
            }

            $quote->getPayment()->setMethod($method);
            $this->quoteRepository->save($quote);

            return $result->setData(['success' => true]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
