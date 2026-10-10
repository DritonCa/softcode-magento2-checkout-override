<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Test\Unit\Model\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Softcode\CheckoutOverride\Model\Config;
use Softcode\CheckoutOverride\Model\Payment\PaymentPolicy;

/**
 * Executable specification of the buyer-type payment rules.
 */
class PaymentPolicyTest extends TestCase
{
    private const EXAMPLE_RULES = [
        'privat' => ['epay'],
        'cvr' => ['epay', 'purchaseorder'],
        'ean' => ['epay', 'purchaseorder'],
    ];

    /**
     * @dataProvider combinationProvider
     */
    public function testIsAllowedWithRules(string $buyerType, string $method, bool $expected): void
    {
        $this->assertSame($expected, (new PaymentPolicy(self::EXAMPLE_RULES))->isAllowed($buyerType, $method));
    }

    /**
     * @return array<string, array{0:string,1:string,2:bool}>
     */
    public static function combinationProvider(): array
    {
        return [
            'privat + ePay' => ['privat', 'epay', true],
            'privat + purchaseorder (not allowed)' => ['privat', 'purchaseorder', false],
            'cvr + purchaseorder' => ['cvr', 'purchaseorder', true],
            'ean + ePay' => ['ean', 'epay', true],
            'unknown method' => ['ean', 'banktransfer', false],
            'unknown buyer type' => ['reseller', 'epay', false],
            'empty method' => ['cvr', '', false],
        ];
    }

    public function testWithoutRulesEveryMethodIsAllowedForKnownBuyerTypes(): void
    {
        $policy = new PaymentPolicy();

        $this->assertNull($policy->allowedMethods('privat'));
        $this->assertTrue($policy->isAllowed('privat', 'checkmo'));
        $this->assertTrue($policy->isAllowed('ean', 'purchaseorder'));
        $this->assertFalse($policy->isAllowed('reseller', 'checkmo'));
    }

    public function testAdminRulesOverrideDiRules(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === 'softcode_checkout/payment/allowed_privat' ? 'checkmo,epay' : null
        );
        $policy = new PaymentPolicy(self::EXAMPLE_RULES, new Config($scope));

        $this->assertSame(['checkmo', 'epay'], $policy->allowedMethods('privat'));
        $this->assertTrue($policy->isAllowed('privat', 'checkmo'));
        // Buyer types without admin rules are unrestricted once the admin rules are in use.
        $this->assertTrue($policy->isAllowed('cvr', 'banktransfer'));
    }

    public function testAssertAllowedPassesForAllowedCombination(): void
    {
        $this->expectNotToPerformAssertions();
        (new PaymentPolicy(self::EXAMPLE_RULES))->assertAllowed('cvr', 'purchaseorder');
    }

    public function testAssertAllowedThrowsForDisallowedMethod(): void
    {
        $this->expectException(LocalizedException::class);
        (new PaymentPolicy(self::EXAMPLE_RULES))->assertAllowed('privat', 'purchaseorder');
    }

    public function testAssertAllowedThrowsForUnknownBuyerType(): void
    {
        $this->expectException(LocalizedException::class);
        (new PaymentPolicy())->assertAllowed('reseller', 'epay');
    }
}
