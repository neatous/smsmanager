<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Fake;

use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\JournalEntry;
use Neatous\SmsManager\Fake\JournalEntryList;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Flow\FlowStepList;
use Neatous\SmsManager\Message\Flow\SmsFlowStep;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Message\Sender;
use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;
use Neatous\SmsManager\Tests\Support\TemporaryJournalDirectory;
use PHPUnit\Framework\TestCase;

final class FakeMessageSenderTest extends TestCase
{

    private string $journalDirectory;

    public function testSendWritesOneEntryPerRecipient(): void
    {
        $message = Message::create(
            MessageBody::fromString('Your code is 1234.'),
            RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'), PhoneNumber::fromString('420777654321'))
        )
            ->withSender(Sender::fromString('NEATOUS'))
            ->withTags(TagList::fromTags(Tag::transactional(), Tag::fromString('winter-sale')))
            ->withPayload(Payload::fromArray(['order_id' => 'A-1']));

        $acceptance = (new FakeMessageSender($this->journalDirectory))->send($message);
        self::assertTrue($acceptance->isFullyAccepted());
        $first = $acceptance->getAcceptedRecipients()->getByRecipientIndex(0);
        $second = $acceptance->getAcceptedRecipients()->getByRecipientIndex(1);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->getMessageId()->getValue(), $second->getMessageId()->getValue());

        $entries = (new MessageJournal($this->journalDirectory))->getLatestEntries(10);
        self::assertCount(2, $entries);

        $entry = self::entryOfMessageId($entries, $first->getMessageId());
        self::assertSame('420777123456', $entry->getPhoneNumber()->getValue());
        self::assertSame('Your code is 1234.', $entry->getBody()->getValue());
        self::assertSame('NEATOUS', $entry->getSender()?->getValue());
        self::assertSame('transactional,winter-sale', $entry->getTags()?->toRequestValue());
        self::assertSame('A-1', $entry->getPayload()?->get('order_id'));
        self::assertFalse($entry->isPriority());
        self::assertSame($acceptance->getRequestId()->getValue(), $entry->getRequestId()->getValue());
        self::assertSame('420777654321', self::entryOfMessageId($entries, $second->getMessageId())->getPhoneNumber()->getValue());
    }

    public function testSendPriorityMarksEntryAsPriority(): void
    {
        (new FakeMessageSender($this->journalDirectory))->sendPriority(self::minimalMessage('Priority message'));
        self::assertTrue(self::singleEntry(new MessageJournal($this->journalDirectory))->isPriority());
    }

    public function testFlowMessageWithoutBodyWritesFirstStepBody(): void
    {
        $message = Message::createWithFlow(
            FlowStepList::fromFlowSteps(
                SmsFlowStep::create(MessageBody::fromString('First step body')),
                SmsFlowStep::create(MessageBody::fromString('Second step body'))
            ),
            RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'))
        );

        (new FakeMessageSender($this->journalDirectory))->send($message);
        self::assertSame('First step body', self::singleEntry(new MessageJournal($this->journalDirectory))->getBody()->getValue());
    }

    protected function setUp(): void
    {
        $this->journalDirectory = TemporaryJournalDirectory::create();
    }

    protected function tearDown(): void
    {
        TemporaryJournalDirectory::remove($this->journalDirectory);
    }

    private static function minimalMessage(string $body): Message
    {
        return Message::create(MessageBody::fromString($body), RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')));
    }

    private static function singleEntry(MessageJournal $journal): JournalEntry
    {
        foreach ($journal->getLatestEntries(10) as $entry) {
            return $entry;
        }

        self::fail('The journal does not contain any entry.');
    }

    private static function entryOfMessageId(JournalEntryList $entries, MessageId $messageId): JournalEntry
    {
        foreach ($entries as $entry) {
            if ($entry->getMessageId()->equals($messageId)) {
                return $entry;
            }
        }

        self::fail(sprintf('The journal does not contain an entry of message id "%s".', $messageId->getValue()));
    }
}
