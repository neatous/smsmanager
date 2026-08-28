<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

enum DeliveryResult: string
{

    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case SEEN = 'seen';
    case UNDELIVERED = 'undelivered';
    case REJECTED = 'rejected';
    case FAILED = 'failed';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SENDING, self::SENT => false,
            self::DELIVERED, self::SEEN, self::UNDELIVERED, self::REJECTED, self::FAILED => true,
        };
    }

    public function isSuccessful(): bool
    {
        return match ($this) {
            self::DELIVERED, self::SEEN => true,
            self::SENDING, self::SENT, self::UNDELIVERED, self::REJECTED, self::FAILED => false,
        };
    }

    public function isFailure(): bool
    {
        return match ($this) {
            self::UNDELIVERED, self::REJECTED, self::FAILED => true,
            self::SENDING, self::SENT, self::DELIVERED, self::SEEN => false,
        };
    }

    public function isRejectedBeforeSending(): bool
    {
        return $this === self::REJECTED;
    }
}
