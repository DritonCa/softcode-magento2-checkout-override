<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Softcode\CheckoutOverride\Controller\FormKeyValidationTrait;
use Softcode\CheckoutOverride\Model\Payment\PaymentPolicy;

/**
 * Stores the chosen payment method on the quote after checking it against the
 * central PaymentPolicy for the current buyer type. This gives the buyer an
 * early, friendly error instead of a late failure at order submission.
 */
class SavePayment implements HttpPostActionInterface, CsrfAwareActionInterface
{
    use FormKeyValidationTrait;

    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly PaymentPolicy $paymentPolicy,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $method = trim((string) $this->request->getParam('method', ''));

        if ($method === '') {
            return $result->setData(['success' => false, 'error' => __('A payment method is required.')]);
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                throw new LocalizedException(__('There is no active cart.'));
            }

            $this->paymentPolicy->assertAllowed((string) $quote->getData('company_type'), $method);

            $quote->getPayment()->setMethod($method);
            $this->quoteRepository->save($quote);

            return $result->setData(['success' => true]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout savePayment failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'error' => __('The payment method could not be saved.')]);
        }
    }
}
