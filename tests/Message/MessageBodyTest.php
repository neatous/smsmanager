<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\MessageBody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageBodyTest extends TestCase
{

    #[DataProvider('provideValidBodies')]
    public function testAcceptsBodiesWithinLimits(string $value): void
    {
        self::assertSame($value, MessageBody::fromString($value)->getValue());
    }

    #[DataProvider('provideInvalidBodies')]
    public function testRejectsBodiesOutsideLimits(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidMessageBodyException::class);
        MessageBody::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function provideValidBodies(): iterable
    {
        yield 'single character' => ['a'];
        yield 'maximal length' => [str_repeat('a', 1000)];
        yield 'multibyte at maximal length' => [str_repeat('č', 1000)];
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 1001)];
        yield 'invalid utf-8 sequence' => ["\xC3\x28"];
        yield 'invalid utf-8 byte' => ["\xFF"];
    }
}
