<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\CallbackUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallbackUrlTest extends TestCase
{

    public function testAcceptsHttpsUrl(): void
    {
        self::assertSame('https://example.com/callback?id=1', CallbackUrl::fromString(' https://example.com/callback?id=1 ')->getValue());
    }

    #[DataProvider('provideInvalidUrls')]
    public function testRejectsInvalidUrls(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidCallbackUrlException::class);
        CallbackUrl::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'http scheme' => ['http://example.com/callback'];
        yield 'relative path' => ['/callback'];
        yield 'missing host' => ['https:///callback'];
    }
}
