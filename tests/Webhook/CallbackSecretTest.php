<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Webhook;

use Neatous\SmsManager\Webhook\CallbackSecret;
use PHPUnit\Framework\TestCase;

final class CallbackSecretTest extends TestCase
{

    private const string SIGNATURE = 'dc46983557fea127b43af721467eb9b3fde2338fe3e14f51952aa8478c13d355';

    public function testAcceptsValidSignature(): void
    {
        self::assertTrue(CallbackSecret::fromString('secret')->isValidSignature('body', self::SIGNATURE));
    }

    public function testAcceptsUppercaseSignature(): void
    {
        self::assertTrue(CallbackSecret::fromString('secret')->isValidSignature('body', strtoupper(self::SIGNATURE)));
    }

    public function testRejectsTamperedBody(): void
    {
        self::assertFalse(CallbackSecret::fromString('secret')->isValidSignature('body ', self::SIGNATURE));
    }

    public function testRejectsWrongSignature(): void
    {
        self::assertFalse(CallbackSecret::fromString('secret')->isValidSignature('body', str_repeat('0', 64)));
    }

    public function testMasksValueInDebugInfo(): void
    {
        self::assertSame(['value' => '***'], CallbackSecret::fromString('secret')->__debugInfo());
    }

    public function testRejectsWhitespaceOnlyValue(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidCallbackSecretException::class);
        CallbackSecret::fromString("  \t ");
    }
}
