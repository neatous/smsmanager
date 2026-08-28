<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests;

use Neatous\SmsManager\BaseUri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BaseUriTest extends TestCase
{

    public function testDefaultPointsToProductionApi(): void
    {
        self::assertSame('https://api.smsmngr.com/v2', BaseUri::default()->getValue());
    }

    public function testStripsTrailingSlash(): void
    {
        self::assertSame('https://api.example.com/v3', BaseUri::fromString('https://api.example.com/v3/')->getValue());
    }

    #[DataProvider('provideInvalidBaseUris')]
    public function testRejectsInvalidBaseUris(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidBaseUriException::class);
        BaseUri::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidBaseUris(): iterable
    {
        yield 'http scheme' => ['http://api.smsmngr.com/v2'];
        yield 'missing host' => ['https:///v2'];
    }
}
