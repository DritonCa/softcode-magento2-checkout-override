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
use Magento\Newsletter\Model\SubscriptionManagerInterface;
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
 *
 * A failure is logged with a short reference that is also shown to the customer,
 * so support can find the exact log line.
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
        private readonly LoggerInterface $logger,
        private readonly SubscriptionManagerInterface $subscriptionManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        $quote = null;
        $method = '';

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                throw new LocalizedException(__('There is no active cart.'));
            }

            $payment = $quote->getPayment();
            $method = (string)$payment->getMethod();
            if ($method === '') {
                throw new LocalizedException(__('Please choose a payment method.'));
            }

            // Final server-side gate: the same policy the buyer was validated against earlier.
            $this->paymentPolicy->assertAllowed((string)$quote->getData('company_type'), $method);

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

            $orderId = (int)$this->cartManagement->placeOrder($quote->getId());

            if ((int)$this->request->getParam('newsletter') === 1) {
                $this->subscribe((string)$quote->getCustomerEmail(), (int)$quote->getStoreId(), $orderId);
            }

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
            $reference = strtoupper(substr(sha1(uniqid('', true)), 0, 6));
            $this->logger->critical('Checkout placeOrder failed [' . $reference . ']: ' . $e->getMessage(), [
                'reference' => $reference,
                'quote_id' => $quote?->getId(),
                'payment_method' => $method,
                'grand_total' => $quote?->getGrandTotal(),
                'exception' => $e,
            ]);
            return $result->setData([
                'success' => false,
                'reference' => $reference,
                'error' => __(
                    'Your order could not be completed. Please try again, or contact us and quote reference %1. '
                    . 'No money has been charged.',
                    $reference
                ),
            ]);
        }
    }

    /**
     * Runs after the order is placed and never fails it: the customer has ordered.
     */
    private function subscribe(string $email, int $storeId, int $orderId): void
    {
        if ($email === '') {
            return;
        }
        try {
            $this->subscriptionManager->subscribe($email, $storeId);
        } catch (\Throwable $e) {
            $this->logger->warning('Newsletter subscription from checkout failed', [
                'order_id' => $orderId,
                'exception' => $e,
            ]);
        }
    }
}
