<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Fake;

use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Tests\Support\TemporaryJournalDirectory;
use PHPUnit\Framework\TestCase;

final class MessageJournalTest extends TestCase
{

    private string $journalDirectory;

    public function testLatestEntriesAreSortedFromNewestAndLimited(): void
    {
        $this->sendBodies('First', 'Second', 'Third');

        $bodies = [];

        foreach ((new MessageJournal($this->journalDirectory))->getLatestEntries(2) as $entry) {
            $bodies[] = $entry->getBody()->getValue();
        }

        self::assertSame(['Third', 'Second'], $bodies);
    }

    public function testClearRemovesAllEntries(): void
    {
        $this->sendBodies('First', 'Second');
        $journal = new MessageJournal($this->journalDirectory);
        self::assertSame(2, $journal->countEntries());

        $journal->clear();
        self::assertSame(0, $journal->countEntries());
        self::assertTrue($journal->getLatestEntries(10)->isEmpty());
    }

    public function testMissingJournalDirectoryIsEmpty(): void
    {
        $journal = new MessageJournal($this->journalDirectory);
        self::assertTrue($journal->getLatestEntries(10)->isEmpty());
        self::assertSame(0, $journal->countEntries());
        $journal->clear();
    }

    public function testCorruptEntryFileIsReported(): void
    {
        $this->sendBodies('First');
        file_put_contents($this->journalDirectory . DIRECTORY_SEPARATOR . 'corrupt.json', 'not a json');

        $this->expectException(\Neatous\SmsManager\Exception\JournalException::class);
        (new MessageJournal($this->journalDirectory))->getLatestEntries(10);
    }

    protected function setUp(): void
    {
        $this->journalDirectory = TemporaryJournalDirectory::create();
    }

    protected function tearDown(): void
    {
        TemporaryJournalDirectory::remove($this->journalDirectory);
    }

    private function sendBodies(string ...$bodies): void
    {
        $sender = new FakeMessageSender($this->journalDirectory);

        foreach ($bodies as $body) {
            $sender->send(
                Message::create(MessageBody::fromString($body), RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456')))
            );
            usleep(1000);
        }
    }
}
