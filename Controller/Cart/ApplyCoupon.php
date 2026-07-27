<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Softcode\CheckoutOverride\Controller\FormKeyValidationTrait;

/**
 * Applies or clears a coupon code on the current quote.
 */
class ApplyCoupon implements HttpPostActionInterface, CsrfAwareActionInterface
{
    use FormKeyValidationTrait;

    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            return $result->setData(['success' => false, 'message' => __('There is no active cart.')]);
        }

        $code = trim((string) $this->request->getParam('code', ''));

        try {
            $quote->setCouponCode($code);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);

            if ($code !== '' && $quote->getCouponCode() !== $code) {
                return $result->setData(['success' => false, 'message' => __('That discount code is not valid.')]);
            }

            return $result->setData([
                'success' => true,
                'message' => $code !== '' ? __('Discount code applied.') : __('Discount code removed.'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout applyCoupon failed', ['exception' => $e, 'quote_id' => $quote->getId()]);
            return $result->setData(['success' => false, 'message' => __('The discount code could not be applied.')]);
        }
    }
}
