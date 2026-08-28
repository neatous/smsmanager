<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\MessageSender;
use Neatous\SmsManager\MessageSenderFactory;

final readonly class FakeMessageSenderFactory implements MessageSenderFactory
{

    private string $journalDirectory;

    public function __construct(string $journalDirectory)
    {
        $this->journalDirectory = $journalDirectory;
    }

    public function create(ApiKey $apiKey): MessageSender
    {
        return new FakeMessageSender($this->journalDirectory);
    }
}
