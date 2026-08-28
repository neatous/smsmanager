<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests;

use Neatous\SmsManager\ApiKey;
use PHPUnit\Framework\TestCase;

final class ApiKeyTest extends TestCase
{

    public function testKeepsValue(): void
    {
        self::assertSame('secret-key', ApiKey::fromString('  secret-key  ')->getValue());
    }

    public function testMasksValueInDebugInfo(): void
    {
        self::assertSame(['value' => '***'], ApiKey::fromString('secret-key')->__debugInfo());
    }

    public function testRejectsWhitespaceOnlyValue(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidApiKeyException::class);
        ApiKey::fromString("  \t ");
    }
}
