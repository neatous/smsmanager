<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Fake;

use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\FakeMessageSenderFactory;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Tests\Support\TemporaryJournalDirectory;
use PHPUnit\Framework\TestCase;

final class FakeMessageSenderFactoryTest extends TestCase
{

    private string $journalDirectory;

    public function testCreatesFakeSenderWritingIntoConfiguredDirectory(): void
    {
        $sender = (new FakeMessageSenderFactory($this->journalDirectory))->create(ApiKey::fromString('any-key'));
        self::assertInstanceOf(FakeMessageSender::class, $sender);

        $sender->send(
            Message::create(MessageBody::fromString('Hello world'), RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')))
        );
        self::assertSame(1, (new MessageJournal($this->journalDirectory))->countEntries());
    }

    protected function setUp(): void
    {
        $this->journalDirectory = TemporaryJournalDirectory::create();
    }

    protected function tearDown(): void
    {
        TemporaryJournalDirectory::remove($this->journalDirectory);
    }
}
