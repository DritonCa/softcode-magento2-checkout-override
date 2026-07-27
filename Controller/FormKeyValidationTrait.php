<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Shared CSRF handling for the checkout's AJAX POST endpoints.
 *
 * The controller must implement {@see \Magento\Framework\App\CsrfAwareActionInterface}
 * and expose a {@see \Magento\Framework\Data\Form\FormKey\Validator} as
 * $this->formKeyValidator. The request is accepted only when it carries a valid
 * Magento form key, so cross-site requests are rejected the same way the native
 * checkout rejects them.
 */
trait FormKeyValidationTrait
{
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        // Fall back to Magento's default 403 response.
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }
}
