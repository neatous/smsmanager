<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use Neatous\SmsManager\ApiKey;
use Neatous\SmsManager\Client;
use Neatous\SmsManager\ClientFactory;

final readonly class FakeClientFactory implements ClientFactory
{

    private string $journalDirectory;

    public function __construct(string $journalDirectory)
    {
        $this->journalDirectory = $journalDirectory;
    }

    public function create(ApiKey $apiKey): Client
    {
        return new FakeClient($this->journalDirectory);
    }
}
