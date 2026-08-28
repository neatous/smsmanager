<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Webhook;

use Neatous\SmsManager\Webhook\DeliveryResult;
use PHPUnit\Framework\TestCase;

final class DeliveryResultTest extends TestCase
{

    public function testSentIsNotFinal(): void
    {
        self::assertFalse(DeliveryResult::SENT->isFinal());
        self::assertFalse(DeliveryResult::SENT->isSuccessful());
    }

    public function testSeenIsFinalAndSuccessful(): void
    {
        self::assertTrue(DeliveryResult::SEEN->isFinal());
        self::assertTrue(DeliveryResult::SEEN->isSuccessful());
        self::assertFalse(DeliveryResult::SEEN->isFailure());
    }

    public function testRejectedIsFailureBeforeSending(): void
    {
        self::assertTrue(DeliveryResult::REJECTED->isFailure());
        self::assertTrue(DeliveryResult::REJECTED->isRejectedBeforeSending());
        self::assertFalse(DeliveryResult::UNDELIVERED->isRejectedBeforeSending());
    }
}
