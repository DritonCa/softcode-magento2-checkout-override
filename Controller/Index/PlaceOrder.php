<?php
namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Customer\Api\Data\GroupInterface;

class PlaceOrder extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $quoteRepository,
        private CartManagementInterface $cartManagement,
        private \Psr\Log\LoggerInterface $logger
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

            /* =========================
               PAYMENT
            ========================== */
            $payment = $quote->getPayment();
            $companyType = $quote->getData('company_type');

            if ($companyType === 'privat') {
                if (!$payment->getMethod()) {
                    throw new \Exception('Payment method is required.');
                }

                $payment->importData([
                    'method' => $payment->getMethod()
                ]);
            } else {
                $payment->setMethod('purchaseorder');
                $payment->importData([
                    'method' => 'purchaseorder',
                    'po_number' =>
                        $quote->getData('company_ean')
                            ?: $quote->getData('company_cvr')
                            ?: 'PO-' . $quote->getId()
                ]);
            }

            /* =========================
               FINALIZE CUSTOMER
            ========================== */
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

            $billing = $quote->getBillingAddress();
            $billing->setEmail($quote->getCustomerEmail());

            $this->quoteRepository->save($quote);

            /* =========================
               PLACE ORDER
            ========================== */
            $orderId = $this->cartManagement->placeOrder($quote->getId());

            return $result->setData([
                'success' => true,
                'order_id' => $orderId
            ]);

        } catch (\Throwable $e) {
            // Log the real cause server-side; never leak internal messages/stack to the client.
            $this->logger->error('Checkout placeOrder failed', [
                'exception' => $e,
                'quote_id'  => $this->checkoutSession->getQuoteId(),
            ]);

            return $result->setData([
                'success' => false,
                'error'   => __('Din bestilling kunne ikke gennemføres. Prøv igen, eller kontakt os.'),
            ]);
        }
    }
}
