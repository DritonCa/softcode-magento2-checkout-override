<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Test\Unit\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Softcode\CheckoutOverride\Model\Payment\PaymentPolicy;

/**
 * Encodes the buyer-type payment rules as executable, reviewable specification:
 *   privat -> ePay only
 *   cvr    -> ePay or purchase order
 *   ean    -> ePay or purchase order
 */
class PaymentPolicyTest extends TestCase
{
    private PaymentPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PaymentPolicy();
    }

    /**
     * @dataProvider combinationProvider
     */
    public function testIsAllowed(string $buyerType, string $method, bool $expected): void
    {
        $this->assertSame($expected, $this->policy->isAllowed($buyerType, $method));
    }

    /**
     * @return array<string, array{0:string,1:string,2:bool}>
     */
    public static function combinationProvider(): array
    {
        return [
            'privat + ePay' => ['privat', 'epay', true],
            'privat + purchaseorder (not allowed)' => ['privat', 'purchaseorder', false],
            'cvr + ePay' => ['cvr', 'epay', true],
            'cvr + purchaseorder' => ['cvr', 'purchaseorder', true],
            'ean + ePay' => ['ean', 'epay', true],
            'ean + purchaseorder' => ['ean', 'purchaseorder', true],
            'unknown method' => ['ean', 'banktransfer', false],
            'unknown buyer type' => ['reseller', 'epay', false],
        ];
    }

    public function testAllowedMethodsReturnsConfiguredSet(): void
    {
        self::assertSame(['epay'], $this->policy->allowedMethods('privat'));
        self::assertSame(['epay', 'purchaseorder'], $this->policy->allowedMethods('cvr'));
        self::assertSame([], $this->policy->allowedMethods('unknown'));
    }

    public function testAssertAllowedPassesForAllowedCombination(): void
    {
        $this->expectNotToPerformAssertions();
        $this->policy->assertAllowed('cvr', 'purchaseorder');
    }

    public function testAssertAllowedThrowsForDisallowedMethod(): void
    {
        $this->expectException(LocalizedException::class);
        $this->policy->assertAllowed('privat', 'purchaseorder');
    }

    public function testAssertAllowedThrowsForUnknownBuyerType(): void
    {
        $this->expectException(LocalizedException::class);
        $this->policy->assertAllowed('reseller', 'epay');
    }

    public function testRulesAreConfigurable(): void
    {
        $policy = new PaymentPolicy(['privat' => ['epay', 'purchaseorder']]);
        self::assertTrue($policy->isAllowed('privat', 'purchaseorder'));
    }
}
