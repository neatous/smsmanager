<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class PhoneNumber
{

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $normalized = self::normalize($value);

        if (preg_match('~^[1-9][0-9]{7,14}$~', $normalized) !== 1) {
            throw new \Neatous\SmsManager\Exception\InvalidPhoneNumberException(sprintf('Invalid phone number "%s".', $value));
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalize(string $value): string
    {
        $normalized = str_replace([' ', '.', '-', '(', ')'], '', trim($value));

        if (str_starts_with($normalized, '+')) {
            return substr($normalized, 1);
        }

        if (str_starts_with($normalized, '00')) {
            return substr($normalized, 2);
        }

        return $normalized;
    }
}
