<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

final readonly class MessageId
{

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \Neatous\SmsManager\Exception\InvalidIdentifierException('MessageId must not be empty.');
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
}
