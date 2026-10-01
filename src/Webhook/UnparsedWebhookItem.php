<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

final readonly class UnparsedWebhookItem
{

    private string $rawJson;

    private \Neatous\SmsManager\Exception\InvalidWebhookException $exception;

    private function __construct(string $rawJson, \Neatous\SmsManager\Exception\InvalidWebhookException $exception)
    {
        $this->rawJson = $rawJson;
        $this->exception = $exception;
    }

    public static function create(
        string $rawJson,
        \Neatous\SmsManager\Exception\InvalidWebhookException $exception,
    ): self
    {
        return new self($rawJson, $exception);
    }

    public function getRawJson(): string
    {
        return $this->rawJson;
    }

    public function getException(): \Neatous\SmsManager\Exception\InvalidWebhookException
    {
        return $this->exception;
    }
}
