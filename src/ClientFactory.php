<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

interface ClientFactory
{

    public function create(ApiKey $apiKey): Client;
}
