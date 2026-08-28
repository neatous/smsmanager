<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Acceptance;

use Neatous\SmsManager\Acceptance\AcceptedRecipient;
use Neatous\SmsManager\Acceptance\AcceptedRecipientList;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RejectedRecipient;
use Neatous\SmsManager\Acceptance\RejectedRecipientList;
use PHPUnit\Framework\TestCase;

final class RecipientListsTest extends TestCase
{

    public function testAcceptedRecipientLookup(): void
    {
        $second = AcceptedRecipient::create(1, MessageId::fromString('m-2'));
        $list = AcceptedRecipientList::fromAcceptedRecipients(AcceptedRecipient::create(0, MessageId::fromString('m-1')), $second);
        self::assertCount(2, $list);
        self::assertTrue($list->isNotEmpty());
        self::assertSame($second, $list->getByRecipientIndex(1));
        self::assertNull($list->getByRecipientIndex(2));
    }

    public function testRejectedRecipientLookup(): void
    {
        $rejected = RejectedRecipient::create(3);
        $list = RejectedRecipientList::fromRejectedRecipients($rejected);
        self::assertSame($rejected, $list->getByRecipientIndex(3));
        self::assertNull($list->getByRecipientIndex(0));
    }

    public function testEmptyLists(): void
    {
        self::assertTrue(AcceptedRecipientList::fromAcceptedRecipients()->isEmpty());
        self::assertFalse(RejectedRecipientList::fromRejectedRecipients()->isNotEmpty());
    }

    public function testRejectsNegativeAcceptedIndex(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidIdentifierException::class);
        AcceptedRecipient::create(-1, MessageId::fromString('m-1'));
    }

    public function testRejectsNegativeRejectedIndex(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidIdentifierException::class);
        RejectedRecipient::create(-1);
    }
}
