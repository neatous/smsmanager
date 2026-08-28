<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message\Flow;

enum SmsGateway: string
{

    case HIGH = 'high';
    case LOWCOST = 'lowcost';
    case DIRECT = 'direct';
    case CUSTOM = 'custom';
    case SIMHOST = 'simhost';
    case GSM = 'gsm';
    case TEST = 'test';
}
