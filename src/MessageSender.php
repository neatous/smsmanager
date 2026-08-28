<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

use Neatous\SmsManager\Acceptance\MessageAcceptance;
use Neatous\SmsManager\Message\Message;

interface MessageSender
{

    public function send(Message $message): MessageAcceptance;

    public function sendPriority(Message $message): MessageAcceptance;
}
