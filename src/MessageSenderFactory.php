<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

interface MessageSenderFactory
{

    public function create(ApiKey $apiKey): MessageSender;
}
