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

/**
 * Persists the buyer type (private / company-CVR / public-EAN) on the quote.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    use FormKeyValidationTrait;

    private const ALLOWED_TYPES = ['privat', 'cvr', 'ean'];

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

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                throw new LocalizedException(__('There is no active cart.'));
            }

            $type = (string) $this->request->getParam('company_type');
            $name = trim((string) $this->request->getParam('company_name', ''));
            $cvr = trim((string) $this->request->getParam('company_cvr', ''));
            $ean = trim((string) $this->request->getParam('company_ean', ''));

            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                throw new LocalizedException(__('Please choose a valid buyer type.'));
            }
            if ($type === 'cvr' && $cvr === '') {
                throw new LocalizedException(__('A CVR number is required for company orders.'));
            }
            if ($type === 'ean' && $ean === '') {
                throw new LocalizedException(__('An EAN number is required for public-sector orders.'));
            }

            $quote->setData('company_type', $type);
            $quote->setData('company_name', $name ?: null);
            $quote->setData('company_cvr', $cvr ?: null);
            $quote->setData('company_ean', $ean ?: null);
            $this->quoteRepository->save($quote);

            return $result->setData(['success' => true]);
        } catch (LocalizedException $e) {
            // Expected validation errors: safe to show to the buyer.
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout saveBuyerType failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'error' => __('Your details could not be saved.')]);
        }
    }
}
