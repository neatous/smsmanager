<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

use SensitiveParameter;

final readonly class CallbackSecret
{

    private const string SIGNATURE_ALGORITHM = 'sha256';

    private string $value;

    private function __construct(#[SensitiveParameter] string $value)
    {
        $this->value = $value;
    }

    public static function fromString(#[SensitiveParameter] string $value): self
    {
        if (trim($value) === '') {
            throw new \Neatous\SmsManager\Exception\InvalidCallbackSecretException('Callback secret must not be empty.');
        }

        return new self($value);
    }

    public function isValidSignature(string $webhookBody, string $signature): bool
    {
        return hash_equals(hash_hmac(self::SIGNATURE_ALGORITHM, $webhookBody, $this->value), strtolower(trim($signature)));
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }
}
