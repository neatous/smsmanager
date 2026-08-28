<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message\Flow;

enum TtlCondition: string
{

    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case SEEN = 'seen';
}
