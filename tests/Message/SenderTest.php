<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\Sender;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SenderTest extends TestCase
{

    #[DataProvider('provideValidSenders')]
    public function testAcceptsValidSenders(string $value, string $expected): void
    {
        self::assertSame($expected, Sender::fromString($value)->getValue());
    }

    #[DataProvider('provideInvalidSenders')]
    public function testRejectsInvalidSenders(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidSenderException::class);
        Sender::fromString($value);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideValidSenders(): iterable
    {
        yield 'virtual number' => ['420777123456', '420777123456'];
        yield 'shortest virtual number' => ['123', '123'];
        yield 'alphanumeric' => ['Neatous', 'Neatous'];
        yield 'alphanumeric with inner space' => ['  My Shop  ', 'My Shop'];
        yield 'maximal alphanumeric' => ['ABCDEFGHIJK', 'ABCDEFGHIJK'];
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidSenders(): iterable
    {
        yield 'empty' => [''];
        yield 'too long alphanumeric' => ['ABCDEFGHIJKL'];
        yield 'too long number' => ['1234567890123456'];
        yield 'forbidden characters' => ['Neat-ous'];
    }
}
