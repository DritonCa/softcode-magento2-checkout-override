<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Softcode\CheckoutOverride\Controller\FormKeyValidationTrait;
use Softcode\CheckoutOverride\Model\Payment\PaymentPolicy;

/**
 * Places the order from the current quote.
 *
 * The buyer's chosen payment method is respected (never silently overwritten);
 * it is only validated against the central PaymentPolicy. Purchase-order buyers
 * get a PO number derived from their EAN/CVR. When ePay is selected the response
 * carries the URL that starts the ePay payment window.
 */
class PlaceOrder implements HttpPostActionInterface, CsrfAwareActionInterface
{
    use FormKeyValidationTrait;

    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly PaymentPolicy $paymentPolicy,
        private readonly UrlInterface $urlBuilder,
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

            $payment = $quote->getPayment();
            $method = (string) $payment->getMethod();
            if ($method === '') {
                throw new LocalizedException(__('Please choose a payment method.'));
            }

            // Final server-side gate: the same policy the buyer was validated against earlier.
            $this->paymentPolicy->assertAllowed((string) $quote->getData('company_type'), $method);

            if ($method === 'purchaseorder') {
                $payment->importData([
                    'method' => 'purchaseorder',
                    'po_number' => $quote->getData('company_ean')
                        ?: $quote->getData('company_cvr')
                        ?: 'PO-' . $quote->getId(),
                ]);
            } else {
                $payment->importData(['method' => $method]);
            }

            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);
            $quote->getBillingAddress()->setEmail($quote->getCustomerEmail());
            $this->quoteRepository->save($quote);

            $orderId = (int) $this->cartManagement->placeOrder($quote->getId());

            if ($method === 'epay') {
                return $result->setData([
                    'success' => true,
                    'order_id' => $orderId,
                    'epay_start' => $this->urlBuilder->getUrl(
                        'epay/epay/checkout',
                        ['_secure' => $this->request->isSecure()]
                    ),
                ]);
            }

            return $result->setData(['success' => true, 'order_id' => $orderId]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout placeOrder failed', ['exception' => $e, 'quote_id' => $this->checkoutSession->getQuoteId()]);
            return $result->setData(['success' => false, 'error' => __('Your order could not be completed. Please try again.')]);
        }
    }
}
