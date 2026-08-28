<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message\Flow;

enum SmsEncoding: string
{

    case UTF = 'utf';
    case SMS = 'sms';
}
