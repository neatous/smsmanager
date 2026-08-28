<?php declare(strict_types = 1);

namespace Neatous\SmsManager;

use SensitiveParameter;

final readonly class ApiKey
{

    private string $value;

    private function __construct(#[SensitiveParameter] string $value)
    {
        $this->value = $value;
    }

    public static function fromString(#[SensitiveParameter] string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \Neatous\SmsManager\Exception\InvalidApiKeyException('API key must not be empty.');
        }

        return new self($normalized);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
