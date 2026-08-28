<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class CallbackUrl
{

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);
        $parts = parse_url($normalized);

        if (filter_var($normalized, FILTER_VALIDATE_URL) === false
            || $parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? '') === ''
        ) {
            throw new \Neatous\SmsManager\Exception\InvalidCallbackUrlException(
                sprintf('Invalid callback URL "%s", an absolute https URL is required.', $value)
            );
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
