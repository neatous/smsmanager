<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Fake;

use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Fake\FakeClient;
use Neatous\SmsManager\Fake\FakeClientFactory;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Tests\Support\TemporaryJournalDirectory;
use PHPUnit\Framework\TestCase;

final class FakeClientFactoryTest extends TestCase
{

    private string $journalDirectory;

    public function testCreatesFakeClientWritingIntoConfiguredDirectory(): void
    {
        $client = (new FakeClientFactory($this->journalDirectory))->create(ApiKey::fromString('any-key'));
        self::assertInstanceOf(FakeClient::class, $client);

        $client->send(
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
