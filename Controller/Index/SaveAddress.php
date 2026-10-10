<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
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
use Softcode\CheckoutOverride\Model\Address\ReceiverName;
use Softcode\CheckoutOverride\Model\Config;

/**
 * Validates the buyer's contact details and writes the billing and shipping
 * addresses onto the quote. An optional alternative delivery address is
 * supported: its company goes into the address' company field and the receiver
 * becomes the addressee. The country comes from the module configuration.
 */
class SaveAddress implements HttpPostActionInterface, CsrfAwareActionInterface
{
    use FormKeyValidationTrait;

    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CountryFactory $countryFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory,
        private readonly RequestInterface $request,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly LoggerInterface $logger,
        private readonly Config $config
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

            $email = trim((string) $this->request->getParam('email', ''));
            $firstname = trim((string) $this->request->getParam('firstname', '')) ?: 'Customer';
            $lastname = trim((string) $this->request->getParam('lastname', '')) ?: 'Order';
            $telephone = trim((string) $this->request->getParam('telephone', ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Please enter a valid email address.'));
            }
            if ($telephone === '') {
                throw new LocalizedException(__('A phone number is required.'));
            }

            $quote->setCustomerEmail($email);
            $quote->setCustomerFirstname($firstname);
            $quote->setCustomerLastname($lastname);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

            $billingStreet = $this->requireStreet('street', 'housenumber', 'postcode', 'city');

            // Optional alternative delivery address.
            $useAlt = (bool) $this->request->getParam('use_alt');
            $altCompany = '';
            $altReceiver = '';
            if ($useAlt) {
                $shipStreet = $this->requireStreet('alt_street', 'alt_housenumber', 'alt_postcode', 'alt_city');
                $altCompany = mb_substr(trim((string) $this->request->getParam('alt_company', '')), 0, 255);
                $altReceiver = mb_substr(trim((string) $this->request->getParam('alt_receiver', '')), 0, 255);
                [$shipFirstname, $shipLastname] = ReceiverName::split($altReceiver, $firstname, $lastname);
            } else {
                $shipStreet = $billingStreet;
                $shipFirstname = $firstname;
                $shipLastname = $lastname;
            }

            $shipping = $this->applyAddress(
                $quote->getShippingAddress(),
                $shipFirstname,
                $shipLastname,
                $telephone,
                $shipStreet
            );
            $shipping->setCompany($altCompany !== '' ? $altCompany : null);

            // Kept on the quote (and copied to the order) for integrations that need the raw values.
            $quote->setData('main_street', $billingStreet['line']);
            $quote->setData('main_postcode', $billingStreet['postcode']);
            $quote->setData('main_city', $billingStreet['city']);
            $quote->setData('alt_company', $altCompany !== '' ? $altCompany : null);
            $quote->setData('alt_receiver', $altReceiver !== '' ? $altReceiver : null);
            $quote->setData('alt_street', $useAlt ? $shipStreet['line'] : null);
            $quote->setData('alt_postcode', $useAlt ? $shipStreet['postcode'] : null);
            $quote->setData('alt_city', $useAlt ? $shipStreet['city'] : null);
            $quote->getShippingAddress()->setCollectShippingRates(true);

            $billing = $this->applyAddress(
                $quote->getBillingAddress(),
                $firstname,
                $lastname,
                $telephone,
                $billingStreet
            );
            $billing->setEmail($email);
            $billing->setSaveInAddressBook(0);
            $quote->setBillingAddress($billing);

            $this->quoteRepository->save($quote);

            return $result->setData(['success' => true]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Checkout saveAddress failed', ['exception' => $e]);
            return $result->setData(['success' => false, 'error' => __('Your address could not be saved.')]);
        }
    }

    /**
     * Read and validate a street/postcode/city group, returning a single street line.
     */
    private function requireStreet(string $streetKey, string $houseKey, string $postKey, string $cityKey): array
    {
        $street = trim((string) $this->request->getParam($streetKey, ''));
        $house = trim((string) $this->request->getParam($houseKey, ''));
        $postcode = trim((string) $this->request->getParam($postKey, ''));
        $city = trim((string) $this->request->getParam($cityKey, ''));

        if ($street === '' || $postcode === '' || $city === '') {
            throw new LocalizedException(__('Please complete the address (street, postcode and city).'));
        }

        return [
            'line' => $house !== '' ? $street . ' ' . $house : $street,
            'postcode' => $postcode,
            'city' => $city,
        ];
    }

    /**
     * @param array{line:string,postcode:string,city:string} $street
     */
    private function applyAddress($address, string $firstname, string $lastname, string $telephone, array $street)
    {
        $address->setFirstname($firstname);
        $address->setLastname($lastname);
        $address->setTelephone($telephone);
        $address->setStreet([$street['line']]);
        $address->setPostcode($street['postcode']);
        $address->setCity($street['city']);
        $countryId = $this->config->getDefaultCountry();
        $address->setCountryId($countryId);

        $country = $this->countryFactory->create()->loadByCode($countryId);
        if ($country->getRegionsRequired()) {
            $region = $this->regionCollectionFactory->create()
                ->addCountryFilter($countryId)
                ->getFirstItem();
            $address->setRegionId((int) $region->getId());
            $address->setRegion($region->getName());
            $address->setRegionCode($region->getCode());
        } else {
            $address->setRegionId(0);
            $address->setRegion('');
            $address->setRegionCode('');
        }

        return $address;
    }
}
