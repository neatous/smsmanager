<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Acceptance;

use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RequestId;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{

    public function testRequestId(): void
    {
        $requestId = RequestId::fromString(' 12345 ');
        self::assertSame('12345', $requestId->getValue());
        self::assertTrue($requestId->equals(RequestId::fromString('12345')));
        self::assertFalse($requestId->equals(RequestId::fromString('54321')));
    }

    public function testMessageId(): void
    {
        $messageId = MessageId::fromString('abc-1');
        self::assertSame('abc-1', $messageId->getValue());
        self::assertTrue($messageId->equals(MessageId::fromString('abc-1')));
    }

    public function testRejectsEmptyRequestId(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidIdentifierException::class);
        RequestId::fromString('  ');
    }

    public function testRejectsEmptyMessageId(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidIdentifierException::class);
        MessageId::fromString('');
    }
}
