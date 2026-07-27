<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Test\Unit\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Softcode\CheckoutOverride\Model\Payment\PaymentPolicy;
use Softcode\CheckoutOverride\Observer\ValidateAndMapQuoteToOrder;
use PHPUnit\Framework\TestCase;

/**
 * Specifies the final server-side gate before an order is created: buyer type and
 * its required number are present, the payment method is allowed for that buyer
 * type (via the central PaymentPolicy), and the buyer-type fields are copied onto
 * the order. Uses the real PaymentPolicy so the enforcement path is exercised end
 * to end; only the quote/order/payment are test doubles.
 */
class ValidateAndMapQuoteToOrderTest extends TestCase
{
    private ValidateAndMapQuoteToOrder $observer;

    protected function setUp(): void
    {
        $this->observer = new ValidateAndMapQuoteToOrder(new PaymentPolicy());
    }

    /**
     * @param array<string, mixed> $quoteData
     */
    private function executeObserver(array $quoteData, ?string $method, DataObject $order): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getData')
            ->willReturnCallback(static fn (string $key = '') => $quoteData[$key] ?? null);

        if ($method === null) {
            $quote->method('getPayment')->willReturn(null);
        } else {
            $payment = $this->createMock(Payment::class);
            $payment->method('getMethod')->willReturn($method);
            $quote->method('getPayment')->willReturn($payment);
        }

        $event = new Event(['quote' => $quote, 'order' => $order]);
        $this->observer->execute(new Observer(['event' => $event]));
    }

    public function testRejectsMissingBuyerType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->executeObserver(['company_type' => ''], 'epay', new DataObject());
    }

    public function testRejectsCvrBuyerWithoutCvrNumber(): void
    {
        $this->expectException(LocalizedException::class);
        $this->executeObserver(['company_type' => 'cvr'], 'epay', new DataObject());
    }

    public function testRejectsEanBuyerWithoutEanNumber(): void
    {
        $this->expectException(LocalizedException::class);
        $this->executeObserver(['company_type' => 'ean'], 'epay', new DataObject());
    }

    public function testRejectsMissingPaymentMethod(): void
    {
        $this->expectException(LocalizedException::class);
        $this->executeObserver(['company_type' => 'privat'], null, new DataObject());
    }

    public function testRejectsMethodNotAllowedForBuyerType(): void
    {
        // privat may only use ePay; purchaseorder must be refused at the final gate.
        $this->expectException(LocalizedException::class);
        $this->executeObserver(['company_type' => 'privat'], 'purchaseorder', new DataObject());
    }

    public function testAcceptsPrivatWithEpayAndMapsFields(): void
    {
        $order = new DataObject();
        $this->executeObserver(['company_type' => 'privat', 'company_name' => ''], 'epay', $order);

        $this->assertSame('privat', $order->getData('company_type'));
    }

    public function testAcceptsCvrWithPurchaseOrderAndMapsCompanyFields(): void
    {
        $order = new DataObject();
        $this->executeObserver(
            [
                'company_type' => 'cvr',
                'company_cvr' => '12345678',
                'company_name' => 'Acme ApS',
            ],
            'purchaseorder',
            $order
        );

        $this->assertSame('cvr', $order->getData('company_type'));
        $this->assertSame('12345678', $order->getData('company_cvr'));
        $this->assertSame('Acme ApS', $order->getData('company_name'));
    }

    public function testAcceptsEanWithEpay(): void
    {
        $order = new DataObject();
        $this->executeObserver(
            ['company_type' => 'ean', 'company_ean' => '5790000000000'],
            'epay',
            $order
        );

        $this->assertSame('ean', $order->getData('company_type'));
        $this->assertSame('5790000000000', $order->getData('company_ean'));
    }
}
