<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{

    #[DataProvider('provideEquivalentNumbers')]
    public function testNormalizesToInternationalDigits(string $value): void
    {
        self::assertSame('420777123456', PhoneNumber::fromString($value)->getValue());
    }

    #[DataProvider('provideInvalidNumbers')]
    public function testRejectsInvalidNumbers(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidPhoneNumberException::class);
        PhoneNumber::fromString($value);
    }

    public function testEquality(): void
    {
        $phoneNumber = PhoneNumber::fromString('420777123456');
        self::assertTrue($phoneNumber->equals(PhoneNumber::fromString('+420 777 123 456')));
        self::assertFalse($phoneNumber->equals(PhoneNumber::fromString('420777123457')));
    }

    /** @return iterable<string, array{string}> */
    public static function provideEquivalentNumbers(): iterable
    {
        yield 'plain' => ['420777123456'];
        yield 'plus prefix with formatting' => ['+420 (777) 123-456'];
        yield 'double zero prefix' => ['00420777123456'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'letters' => ['abc'];
        yield 'leading zero' => ['0420777123456'];
        yield 'too short' => ['1234567'];
        yield 'too long' => ['1234567890123456'];
    }
}
