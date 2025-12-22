<?php
namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;

class Index extends Action
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
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                throw new \Exception('No active quote');
            }

            $companyType = (string)$this->getRequest()->getParam('company_type');
            $companyName = trim((string)$this->getRequest()->getParam('company_name'));
            $companyCvr  = trim((string)$this->getRequest()->getParam('company_cvr'));
            $companyEan  = trim((string)$this->getRequest()->getParam('company_ean'));

            /* ============================
               SERVER-SIDE VALIDATION
            ============================ */
            if (!in_array($companyType, ['privat', 'cvr', 'ean'], true)) {
                throw new \Exception('Invalid company type');
            }

            if ($companyType === 'cvr' && !$companyCvr) {
                throw new \Exception('CVR number is required');
            }

            if ($companyType === 'ean' && !$companyEan) {
                throw new \Exception('EAN number is required');
            }

            /* ============================
               SAVE TO QUOTE
            ============================ */
            $quote->setData('company_type', $companyType);
            $quote->setData('company_name', $companyName ?: null);
            $quote->setData('company_cvr', $companyCvr ?: null);
            $quote->setData('company_ean', $companyEan ?: null);

            $this->quoteRepository->save($quote);

            return $result->setData([
                'success' => true
            ]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
