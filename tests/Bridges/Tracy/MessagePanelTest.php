<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Bridges\Tracy;

use Neatous\SmsManager\Bridges\Tracy\MessagePanel;
use Neatous\SmsManager\Fake\FakeMessageSender;
use Neatous\SmsManager\Fake\MessageJournal;
use Neatous\SmsManager\Message\Message;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\RecipientList;
use Neatous\SmsManager\Message\Sender;
use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;
use Neatous\SmsManager\Tests\Support\TemporaryJournalDirectory;
use PHPUnit\Framework\TestCase;

final class MessagePanelTest extends TestCase
{

    private string $journalDirectory;

    public function testRendersJournalledMessageWithEscapedBody(): void
    {
        (new FakeMessageSender($this->journalDirectory))->send(
            Message::create(
                MessageBody::fromString('<script>alert(1)</script>'),
                RecipientList::fromPhoneNumbers(PhoneNumber::fromString('420777123456'))
            )
                ->withSender(Sender::fromString('NEATOUS'))
                ->withTags(TagList::fromTags(Tag::transactional(), Tag::fromString('winter-sale')))
        );

        $panel = new MessagePanel(new MessageJournal($this->journalDirectory));
        self::assertStringContainsString('&nbsp;1', $panel->getTab());

        $html = $panel->getPanel();
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('420777123456', $html);
        self::assertStringContainsString('NEATOUS', $html);
        self::assertStringContainsString('transactional,winter-sale', $html);
    }

    public function testRendersZeroForMissingJournalDirectory(): void
    {
        $panel = new MessagePanel(new MessageJournal($this->journalDirectory));
        self::assertStringContainsString('&nbsp;0', $panel->getTab());
        self::assertStringNotContainsString('<table', $panel->getPanel());
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
