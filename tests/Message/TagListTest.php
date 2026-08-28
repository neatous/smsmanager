<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;
use PHPUnit\Framework\TestCase;

final class TagListTest extends TestCase
{

    public function testHoldsTags(): void
    {
        $transactional = Tag::transactional();
        $winterSale = Tag::fromString('winter-sale');
        $tags = TagList::fromTags($transactional, $winterSale);
        self::assertCount(2, $tags);
        self::assertSame([$transactional, $winterSale], iterator_to_array($tags));
        self::assertSame('transactional,winter-sale', $tags->toRequestValue());
    }

    public function testRejectsEmptyList(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidTagException::class);
        TagList::fromTags();
    }

    public function testRejectsDuplicates(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidTagException::class);
        TagList::fromTags(Tag::transactional(), Tag::fromString('Transactional'));
    }
}
