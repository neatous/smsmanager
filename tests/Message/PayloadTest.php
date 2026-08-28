<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\Payload;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PayloadTest extends TestCase
{

    public function testKeepsScalarValues(): void
    {
        $values = ['orderId' => 42, 'price' => 1.5, 'code' => 'A1', 'paid' => true];
        $payload = Payload::fromArray($values);
        self::assertSame($values, $payload->toArray());
        self::assertFalse($payload->isEmpty());
    }

    public function testReadsSingleValues(): void
    {
        $payload = Payload::fromArray(['orderId' => 42]);
        self::assertTrue($payload->has('orderId'));
        self::assertSame(42, $payload->get('orderId'));
        self::assertFalse($payload->has('missing'));
        self::assertNull($payload->get('missing'));
    }

    public function testEmptyPayload(): void
    {
        self::assertTrue(Payload::fromArray([])->isEmpty());
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidPayloadException::class);
        Payload::fromArray(['' => 'value']);
    }

    public function testRejectsInvalidUtf8Key(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidPayloadException::class);
        Payload::fromArray(["\xC3\x28" => 'value']);
    }

    public function testRejectsInvalidUtf8Value(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidPayloadException::class);
        Payload::fromArray(['code' => "\xFF"]);
    }

    public function testRejectsNonScalarValue(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidPayloadException::class);
        Payload::fromArray(['object' => new stdClass()]);
    }

    public function testRejectsNullValue(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidPayloadException::class);
        Payload::fromArray(['nothing' => null]);
    }
}
