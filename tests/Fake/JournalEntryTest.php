<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Fake;

use DateTimeImmutable;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RequestId;
use Neatous\SmsManager\Fake\JournalEntry;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\Sender;
use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JournalEntryTest extends TestCase
{

    public function testFullEntrySurvivesJsonRoundTrip(): void
    {
        $sentAt = new DateTimeImmutable('2026-08-27T10:11:12.123456+02:00');
        $entry = JournalEntry::fromJson(
            JournalEntry::create(
                $sentAt,
                RequestId::fromString('r-1'),
                MessageId::fromString('m-1'),
                PhoneNumber::fromString('420777123456'),
                MessageBody::fromString('Your code is 1234.'),
                Sender::fromString('NEATOUS'),
                TagList::fromTags(Tag::transactional(), Tag::fromString('winter-sale')),
                true,
                Payload::fromArray(['order_id' => 'A-1'])
            )->toJson()
        );
        self::assertSame($sentAt->format('Y-m-d\TH:i:s.uP'), $entry->getSentAt()->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('r-1', $entry->getRequestId()->getValue());
        self::assertSame('m-1', $entry->getMessageId()->getValue());
        self::assertSame('420777123456', $entry->getPhoneNumber()->getValue());
        self::assertSame('Your code is 1234.', $entry->getBody()->getValue());
        self::assertSame('NEATOUS', $entry->getSender()?->getValue());
        self::assertSame('transactional,winter-sale', $entry->getTags()?->toRequestValue());
        self::assertTrue($entry->isPriority());
        self::assertSame('A-1', $entry->getPayload()?->get('order_id'));
    }

    public function testOptionalPartsOfEntryAreNull(): void
    {
        $entry = JournalEntry::fromJson(
            JournalEntry::create(
                new DateTimeImmutable(),
                RequestId::fromString('r-1'),
                MessageId::fromString('m-1'),
                PhoneNumber::fromString('420777123456'),
                MessageBody::fromString('Plain message')
            )->toJson()
        );
        self::assertNull($entry->getSender());
        self::assertNull($entry->getTags());
        self::assertNull($entry->getPayload());
        self::assertFalse($entry->isPriority());
    }

    #[DataProvider('provideInvalidJournalEntries')]
    public function testRejectsInvalidJournalEntry(string $json): void
    {
        $this->expectException(\Neatous\SmsManager\Exception\JournalException::class);
        JournalEntry::fromJson($json);
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidJournalEntries(): iterable
    {
        yield 'unparseable json' => ['not a json'];
        yield 'scalar root' => ['"entry"'];
        yield 'missing message id' => [
            '{"sent_at":"2026-08-27T10:11:12.123456+02:00","request_id":"r-1","phone_number":"420777123456","body":"Plain message","priority":false}',
        ];

        yield 'invalid sent at' => [
            '{"sent_at":"2026-08-27 10:11:12","request_id":"r-1","message_id":"m-1","phone_number":"420777123456","body":"Plain message","priority":false}',
        ];

        yield 'missing priority' => [
            '{"sent_at":"2026-08-27T10:11:12.123456+02:00","request_id":"r-1","message_id":"m-1","phone_number":"420777123456","body":"Plain message"}',
        ];

        yield 'invalid phone number' => [
            '{"sent_at":"2026-08-27T10:11:12.123456+02:00","request_id":"r-1","message_id":"m-1","phone_number":"abc","body":"Plain message","priority":false}',
        ];

        yield 'non-array tags' => [
            '{"sent_at":"2026-08-27T10:11:12.123456+02:00","request_id":"r-1","message_id":"m-1","phone_number":"420777123456","body":"Plain message","priority":false,"tags":"transactional"}',
        ];

        yield 'non-string tag' => [
            '{"sent_at":"2026-08-27T10:11:12.123456+02:00","request_id":"r-1","message_id":"m-1","phone_number":"420777123456","body":"Plain message","priority":false,"tags":[1]}',
        ];

        yield 'invalid tag' => [
            '{"sent_at":"2026-08-27T10:11:12.123456+02:00","request_id":"r-1","message_id":"m-1","phone_number":"420777123456","body":"Plain message","priority":false,"tags":["new order"]}',
        ];
    }
}
