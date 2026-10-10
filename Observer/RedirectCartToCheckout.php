<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Softcode\CheckoutOverride\Model\Config;

/**
 * Sends the cart page (/checkout/cart) straight to the one-page checkout when
 * "Skip cart page" is enabled.
 *
 * An empty cart goes to the home page instead; otherwise the checkout would send it
 * back to the cart and loop.
 */
class RedirectCartToCheckout implements ObserverInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly UrlInterface $url,
        private readonly ActionFlag $actionFlag,
        private readonly CheckoutSession $checkoutSession,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isCartRedirectEnabled()) {
            return;
        }
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);

        $hasItems = (int)$this->checkoutSession->getQuote()->getItemsCount() > 0;
        $this->response->setRedirect($this->url->getUrl($hasItems ? 'checkout' : ''));
    }
}
