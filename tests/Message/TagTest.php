<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{

    public function testLowercasesValue(): void
    {
        self::assertSame('order-2024.01_a', Tag::fromString('Order-2024.01_A')->getValue());
    }

    #[DataProvider('provideInvalidTags')]
    public function testRejectsInvalidTags(string $value): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidTagException::class);
        Tag::fromString($value);
    }

    public function testNamedConstructors(): void
    {
        self::assertSame('transactional', Tag::transactional()->getValue());
        self::assertSame('promotional', Tag::promotional()->getValue());
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidTags(): iterable
    {
        yield 'empty' => [''];
        yield 'space inside' => ['new order'];
        yield 'slash' => ['order/new'];
        yield 'diacritics' => ['objednávka'];
    }
}
