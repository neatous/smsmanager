<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class Sender
{

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if (!self::isVirtualNumber($normalized) && !self::isAlphanumeric($normalized)) {
            throw new \Neatous\SmsManager\Exception\InvalidSenderException(sprintf('Invalid sender "%s".', $value));
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    private static function isVirtualNumber(string $value): bool
    {
        return preg_match('~^[0-9]{3,15}$~', $value) === 1;
    }

    private static function isAlphanumeric(string $value): bool
    {
        return strlen($value) <= 11 && preg_match('~^[A-Za-z0-9]+( [A-Za-z0-9]+)*$~', $value) === 1;
    }
}
