<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\TimeOfDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeOfDayTest extends TestCase
{

    #[DataProvider('provideValidTimes')]
    public function testNormalizesValidTimes(string $value, string $expected): void
    {
        self::assertSame($expected, TimeOfDay::fromString($value)->getValue());
    }

    #[DataProvider('provideInvalidTimes')]
    public function testRejectsInvalidTimes(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidDeliveryWindowException::class);
        TimeOfDay::fromString($value);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideValidTimes(): iterable
    {
        yield 'midnight' => ['00:00', '00:00'];
        yield 'last minute' => ['23:59', '23:59'];
        yield 'zero padding' => ['9:5', '09:05'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidTimes(): iterable
    {
        yield 'empty' => [''];
        yield 'hours out of range' => ['24:00'];
        yield 'minutes out of range' => ['12:60'];
        yield 'seconds included' => ['12:00:00'];
    }
}
