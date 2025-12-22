<?php
namespace Softcode\CheckoutOverride\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;

class ApplyCoupon extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            return $result->setData([
                'success' => false,
                'message' => __('Ingen aktiv kurv')
            ]);
        }

        $code = trim((string)$this->getRequest()->getParam('code'));

        try {
            $quote->setCouponCode($code);
            $quote->collectTotals();
            $quote->save();

            if ($code && $quote->getCouponCode() !== $code) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Ugyldig rabatkode')
                ]);
            }

            return $result->setData([
                'success' => true,
                'message' => $code
                    ? __('Rabatkode anvendt')
                    : __('Rabatkode fjernet')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => __('Der opstod en fejl')
            ]);
        }
    }
}
