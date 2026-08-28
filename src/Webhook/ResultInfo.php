<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

final readonly class ResultInfo
{

    private const int CODE_INVALID_PHONE_NUMBER = 302;

    private const int CODE_NOT_IN_ALLOW_LIST = 303;

    private const int CODE_BLACKLISTED = 304;

    private const int CODE_DUPLICATE_MESSAGE = 306;

    private const int CODE_INSUFFICIENT_CREDIT = 307;

    private string $value;

    private ?int $code;

    private ?string $description;

    private function __construct(string $value, ?int $code, ?string $description)
    {
        $this->value = $value;
        $this->code = $code;
        $this->description = $description;
    }

    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if ($normalized === '') {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event contains an empty "result_info" value.');
        }

        $matches = [];

        if (preg_match('~^\[([0-9]+)\]\s*(.*)$~s', $normalized, $matches) !== 1) {
            return new self($normalized, null, $normalized);
        }

        $description = trim($matches[2]);

        return new self($normalized, (int) $matches[1], $description === '' ? null : $description);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isInvalidPhoneNumber(): bool
    {
        return $this->code === self::CODE_INVALID_PHONE_NUMBER;
    }

    public function isNotInAllowList(): bool
    {
        return $this->code === self::CODE_NOT_IN_ALLOW_LIST;
    }

    public function isBlacklisted(): bool
    {
        return $this->code === self::CODE_BLACKLISTED;
    }

    public function isDuplicateMessage(): bool
    {
        return $this->code === self::CODE_DUPLICATE_MESSAGE;
    }

    public function isInsufficientCredit(): bool
    {
        return $this->code === self::CODE_INSUFFICIENT_CREDIT;
    }
}
