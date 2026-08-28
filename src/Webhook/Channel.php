<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

enum Channel: string
{

    case SMS = 'sms';
    case VIBER = 'viber';
    case WHATSAPP_TEXT = 'whatsapp_text';
    case WHATSAPP_TEMPLATE = 'whatsapp_template';
    case RCS = 'rcs';

    public function getDetailKey(): ?string
    {
        return match ($this) {
            self::SMS => 'sms',
            self::VIBER => 'viber',
            self::WHATSAPP_TEMPLATE => 'whatsapp_template',
            self::WHATSAPP_TEXT => 'whatsapp_body',
            self::RCS => null,
        };
    }
}
