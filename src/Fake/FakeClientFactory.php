<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Client;
use Neatous\SmsManager\ClientFactory;
use Neatous\SmsManager\Message\Sender;

final readonly class FakeClientFactory implements ClientFactory
{

    private string $journalDirectory;

    private ?Sender $defaultSender;

    public function __construct(string $journalDirectory, ?Sender $defaultSender = null)
    {
        $this->journalDirectory = $journalDirectory;
        $this->defaultSender = $defaultSender;
    }

    public function create(ApiKey $apiKey): Client
    {
        return new FakeClient($this->journalDirectory, $this->defaultSender);
    }
}
