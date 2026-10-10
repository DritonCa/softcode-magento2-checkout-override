<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Softcode\CheckoutOverride\Model\Address\ReceiverName;

class ReceiverNameTest extends TestCase
{
    /**
     * @dataProvider names
     */
    public function testSplitsAtLastSpace(string $receiver, array $expected): void
    {
        $this->assertSame($expected, ReceiverName::split($receiver, 'Anna', 'Buyer'));
    }

    public static function names(): array
    {
        return [
            'two names' => ['Jens Hansen', ['Jens', 'Hansen']],
            'middle name stays with first' => ['Mette Marie  Østergård', ['Mette Marie', 'Østergård']],
            'single word keeps buyer last name' => ['Kontoret', ['Kontoret', 'Buyer']],
            'empty falls back to buyer' => ['  ', ['Anna', 'Buyer']],
        ];
    }
}
