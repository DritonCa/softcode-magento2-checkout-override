<?php
namespace Softcode\CheckoutOverride\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Customer\Api\Data\GroupInterface;

class SaveAddress extends Action
{
    public function __construct(
        Context $context,
        private JsonFactory $jsonFactory,
        private CheckoutSession $checkoutSession,
        private CartRepositoryInterface $quoteRepository,
        private CountryFactory $countryFactory,
        private RegionCollectionFactory $regionCollectionFactory
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
               CUSTOMER IDENTITY
            ========================== */
            $email     = trim((string)$this->getRequest()->getParam('email'));
            $firstname = trim((string)$this->getRequest()->getParam('firstname'));
            $lastname  = trim((string)$this->getRequest()->getParam('lastname'));
            $telephone = trim((string)$this->getRequest()->getParam('telephone'));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Invalid email address');
            }

            if ($firstname === '') $firstname = 'Customer';
            if ($lastname === '')  $lastname  = 'Order';

            if ($telephone === '') {
                throw new \Exception('Telephone is required');
            }

            $quote->setCustomerEmail($email);
            $quote->setCustomerFirstname($firstname);
            $quote->setCustomerLastname($lastname);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

            /* =========================
               NORMAL ADDRESS (SOURCE OF TRUTH)
            ========================== */
            $baseStreet = trim((string)$this->getRequest()->getParam('street'));
            $baseHouse  = trim((string)$this->getRequest()->getParam('housenumber'));
            $basePost   = trim((string)$this->getRequest()->getParam('postcode'));
            $baseCity   = trim((string)$this->getRequest()->getParam('city'));

            if ($baseStreet === '' || $basePost === '' || $baseCity === '') {
                throw new \Exception('Main address incomplete');
            }

            $baseFullStreet = $baseHouse
                ? $baseStreet . ' ' . $baseHouse
                : $baseStreet;

            /* =========================
               SHIPPING ADDRESS (ALT AWARE)
            ========================== */
            $useAlt = (bool)$this->getRequest()->getParam('use_alt');

            if ($useAlt) {
                $shipStreet = trim((string)$this->getRequest()->getParam('alt_street'));
                $shipHouse  = trim((string)$this->getRequest()->getParam('alt_housenumber'));
                $shipPost   = trim((string)$this->getRequest()->getParam('alt_postcode'));
                $shipCity   = trim((string)$this->getRequest()->getParam('alt_city'));

                if ($shipStreet === '' || $shipPost === '' || $shipCity === '') {
                    throw new \Exception('Alternative address incomplete');
                }

                $shipFullStreet = $shipHouse
                    ? $shipStreet . ' ' . $shipHouse
                    : $shipStreet;

                $shipFirstname = trim((string)$this->getRequest()->getParam('alt_receiver')) ?: $firstname;
                $shipLastname  = 'Delivery';

            } else {
                $shipFullStreet = $baseFullStreet;
                $shipPost = $basePost;
                $shipCity = $baseCity;
                $shipFirstname = $firstname;
                $shipLastname  = $lastname;
            }

            $shipping = $quote->getShippingAddress();
            $shipping->setFirstname($shipFirstname);
            $shipping->setLastname($shipLastname);
            $shipping->setTelephone($telephone);
            $shipping->setStreet([$shipFullStreet]);
            $shipping->setPostcode($shipPost);
            $shipping->setCity($shipCity);
            $shipping->setCountryId('DK');

            /* =========================
               REGION (DK SAFE)
            ========================== */
            $country = $this->countryFactory->create()->loadByCode('DK');
            if ($country->getRegionsRequired()) {
                $region = $this->regionCollectionFactory
                    ->create()
                    ->addCountryFilter('DK')
                    ->getFirstItem();

                $shipping->setRegionId((int)$region->getId());
                $shipping->setRegion($region->getName());
                $shipping->setRegionCode($region->getCode());
            } else {
                $shipping->setRegionId(0);
                $shipping->setRegion('');
                $shipping->setRegionCode('');
            }

            $shipping->setCollectShippingRates(true);

            /* =========================
               BILLING ADDRESS (ALWAYS NORMAL)
            ========================== */
            $billing = $quote->getBillingAddress();

            $billing->setFirstname($firstname);
            $billing->setLastname($lastname);
            $billing->setTelephone($telephone);
            $billing->setStreet([$baseFullStreet]);
            $billing->setPostcode($basePost);
            $billing->setCity($baseCity);
            $billing->setCountryId('DK');
            $billing->setRegionId($shipping->getRegionId());
            $billing->setRegion($shipping->getRegion());
            $billing->setRegionCode($shipping->getRegionCode());
            $billing->setEmail($email);

            $billing->setSameAsBilling(1);
            $billing->setSaveInAddressBook(0);

            $quote->setBillingAddress($billing);

            $this->quoteRepository->save($quote);

            return $result->setData(['success' => true]);

        } catch (\Throwable $e) {
            return $result->setData([
                'success' => false,
                'error'   => $e->getMessage()
            ]);
        }
    }
}
