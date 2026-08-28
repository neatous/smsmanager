<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

final readonly class BaseUri
{

    private const string DEFAULT_VALUE = 'https://api.smsmngr.com/v2';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $normalized = rtrim(trim($value), '/');
        $parts = parse_url($normalized);

        if (filter_var($normalized, FILTER_VALIDATE_URL) === false
            || $parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? '') === ''
        ) {
            throw new \Neatous\SmsManager\Exception\InvalidBaseUriException(
                sprintf('Invalid base URI "%s", an absolute https URL is required.', $value)
            );
        }

        return new self($normalized);
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_VALUE);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
