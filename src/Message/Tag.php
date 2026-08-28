<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class Tag
{

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        if (preg_match('~^[a-z0-9._-]+$~', $normalized) !== 1) {
            throw new \Neatous\SmsManager\Exception\InvalidTagException(sprintf('Invalid tag "%s".', $value));
        }

        return new self($normalized);
    }

    public static function transactional(): self
    {
        return new self('transactional');
    }

    public static function promotional(): self
    {
        return new self('promotional');
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
