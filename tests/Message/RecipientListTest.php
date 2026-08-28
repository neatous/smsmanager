<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Message;

use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use PHPUnit\Framework\TestCase;

final class RecipientListTest extends TestCase
{

    public function testSingleRecipient(): void
    {
        $recipients = RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'));
        self::assertCount(1, $recipients);
        self::assertTrue($recipients->isSingle());
    }

    public function testMaximalRecipientCount(): void
    {
        $recipients = RecipientList::fromPhoneNumbers(...self::phoneNumbers(10));
        self::assertFalse($recipients->isSingle());
        self::assertCount(10, iterator_to_array($recipients));
    }

    public function testRejectsEmptyList(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidRecipientListException::class);
        RecipientList::fromPhoneNumbers();
    }

    public function testRejectsTooManyRecipients(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidRecipientListException::class);
        RecipientList::fromPhoneNumbers(...self::phoneNumbers(11));
    }

    public function testRejectsDuplicates(): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\InvalidRecipientListException::class);
        RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'), PhoneNumber::fromString('+420 777 123 456'));
    }

    /** @return list<PhoneNumber> */
    private static function phoneNumbers(int $count): array
    {
        $phoneNumbers = [];

        for ($index = 0; $index < $count; $index++) {
            $phoneNumbers[] = PhoneNumber::fromString(sprintf('4207771234%02d', $index));
        }

        return $phoneNumbers;
    }
}
