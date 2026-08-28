<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Fake;

use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\JournalEntry;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Fake\WebhookSimulator;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Tests\Support\TemporaryJournalDirectory;
use Neatous\SmsManager\Webhook\Channel;
use Neatous\SmsManager\Webhook\DeliveryResult;
use Neatous\SmsManager\Webhook\ResultInfo;
use Neatous\SmsManager\Webhook\SentMessageEvent;
use Neatous\SmsManager\Webhook\SentMessageEventList;
use PHPUnit\Framework\TestCase;

final class WebhookSimulatorTest extends TestCase
{

    private string $journalDirectory;

    public function testDeliveredEventMatchesJournalEntry(): void
    {
        $entry = $this->journalEntry();
        $event = self::firstEvent((new WebhookSimulator())->createWebhookBody($entry, DeliveryResult::DELIVERED));
        self::assertSame($entry->getRequestId()->getValue(), $event->getRequestId()->getValue());
        self::assertSame($entry->getMessageId()->getValue(), $event->getMessageId()->getValue());
        self::assertSame(Channel::SMS, $event->getChannel());
        self::assertSame($entry->getSentAt()->getTimestamp(), $event->getOccurredAt()->getTimestamp());
        self::assertSame('420777123456', $event->getPhoneNumber()->getValue());
        self::assertSame(DeliveryResult::DELIVERED, $event->getResult());
        self::assertNull($event->getResultInfo());
        self::assertSame('A-1', $event->getPayload()?->get('order_id'));
    }

    public function testRejectedEventCarriesResultInfo(): void
    {
        $event = self::firstEvent(
            (new WebhookSimulator())->createWebhookBody(
                $this->journalEntry(),
                DeliveryResult::REJECTED,
                ResultInfo::fromString('[307] Insufficient credit')
            )
        );
        self::assertSame(DeliveryResult::REJECTED, $event->getResult());
        self::assertTrue($event->getResultInfo()?->isInsufficientCredit());
    }

    protected function setUp(): void
    {
        $this->journalDirectory = TemporaryJournalDirectory::create();
    }

    protected function tearDown(): void
    {
        TemporaryJournalDirectory::remove($this->journalDirectory);
    }

    private function journalEntry(): JournalEntry
    {
        (new FakeMessageSender($this->journalDirectory))->send(
            Message::create(MessageBody::fromString('Your code is 1234.'), RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')))
                ->withPayload(Payload::fromArray(['order_id' => 'A-1']))
        );

        foreach ((new MessageJournal($this->journalDirectory))->getLatestEntries(10) as $entry) {
            return $entry;
        }

        self::fail('The journal does not contain any entry.');
    }

    private static function firstEvent(string $webhookBody): SentMessageEvent
    {
        foreach (SentMessageEventList::fromJson($webhookBody) as $event) {
            return $event;
        }

        self::fail('The simulated webhook body did not produce any event.');
    }
}
