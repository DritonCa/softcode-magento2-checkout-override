<?php
declare(strict_types=1);

namespace Softcode\CheckoutOverride\Model\Address;

/**
 * Splits a free-text receiver ("Att.") into first and last name at the last space.
 */
class ReceiverName
{
    /**
     * @return array{0: string, 1: string} [firstname, lastname]
     */
    public static function split(string $receiver, string $fallbackFirst, string $fallbackLast): array
    {
        $receiver = trim((string)preg_replace('/\s+/u', ' ', $receiver));
        if ($receiver === '') {
            return [$fallbackFirst, $fallbackLast];
        }
        $pos = mb_strrpos($receiver, ' ');
        if ($pos === false) {
            return [$receiver, $fallbackLast];
        }
        return [mb_substr($receiver, 0, $pos), mb_substr($receiver, $pos + 1)];
    }
}
