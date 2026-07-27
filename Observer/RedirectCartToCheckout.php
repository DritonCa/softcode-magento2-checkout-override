<?php
namespace Softcode\CheckoutOverride\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/**
 * "Vis kurv"-funktionaliteten er fjernet. Kurv-siden (/checkout/cart) sender
 * i stedet direkte til det custom one-page checkout (/checkout).
 *
 * Tomme kurve sendes til forsiden: ellers opstår en redirect-loop, fordi
 * checkout selv sender en tom kurv tilbage til /checkout/cart.
 */
class RedirectCartToCheckout implements ObserverInterface
{
    public function __construct(
        private ResponseInterface $response,
        private UrlInterface      $url,
        private ActionFlag        $actionFlag,
        private CheckoutSession   $checkoutSession
    ) {}

    public function execute(Observer $observer): void
    {
        $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);

        $hasItems = (int) $this->checkoutSession->getQuote()->getItemsCount() > 0;
        $target   = $hasItems ? $this->url->getUrl('checkout') : $this->url->getUrl('');

        $this->response->setRedirect($target);
    }
}
