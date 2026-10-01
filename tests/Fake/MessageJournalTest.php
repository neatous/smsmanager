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

        self::assertSame(['Third', 'Second'], $this->getLatestBodies(2));
    }

    public function testOnlyNewestFilesAreParsed(): void
    {
        $this->sendBodies('Second', 'Third');
        file_put_contents($this->journalDirectory . DIRECTORY_SEPARATOR . '00000000T000000.000000-older.json', 'not a json');

        self::assertSame(['Third', 'Second'], $this->getLatestBodies(2));
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

    /** @return list<string> */
    private function getLatestBodies(int $limit): array
    {
        $bodies = [];

        foreach ((new MessageJournal($this->journalDirectory))->getLatestEntries($limit) as $entry) {
            $bodies[] = $entry->getBody()->getValue();
        }

        return $bodies;
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
