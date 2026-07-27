<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Epay;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Psr\Log\LoggerInterface;

/**
 * Exposes the ePay (Bambora) payment-window parameters to the checkout frontend.
 *
 * ePay is an optional integration: install epay/payment to enable it (see
 * composer "suggest"). The controller degrades gracefully when it is absent, so
 * the module compiles and runs without a hard dependency on the ePay package.
 */
class Config implements HttpGetActionInterface
{
    private const METHOD_CODE = 'epay';

    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly PaymentHelper $paymentHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $method = $this->paymentHelper->getMethodInstance(self::METHOD_CODE);

            if (!$method->isAvailable() || !method_exists($method, 'getEPayPaymentWindowJsUrl')) {
                return $result->setData(['success' => false, 'error' => __('ePay is not available.')]);
            }

            return $result->setData([
                'success' => true,
                'paymentWindowJsUrl' => $method->getEPayPaymentWindowJsUrl(),
                'checkoutUrl' => $method->getCheckoutUrl(),
                'cancelUrl' => $method->getCancelUrl(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout epayConfig failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'error' => __('ePay configuration could not be loaded.')]);
        }
    }
}
