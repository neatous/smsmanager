<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

final readonly class ChannelDetail
{

    private ?string $sender;

    private ?int $countryCode;

    private ?int $operatorCode;

    private ?float $priceCzk;

    private ?float $priceEur;

    private ?int $partCount;

    private ?string $smsGateway;

    private function __construct(
        ?string $sender,
        ?int $countryCode,
        ?int $operatorCode,
        ?float $priceCzk,
        ?float $priceEur,
        ?int $partCount,
        ?string $smsGateway,
    )
    {
        $this->sender = $sender;
        $this->countryCode = $countryCode;
        $this->operatorCode = $operatorCode;
        $this->priceCzk = $priceCzk;
        $this->priceEur = $priceEur;
        $this->partCount = $partCount;
        $this->smsGateway = $smsGateway;
    }

    /** @param array<array-key, mixed> $detail */
    public static function fromArray(array $detail): self
    {
        return new self(
            self::readString($detail, 'sender'),
            self::readInt($detail, 'country'),
            self::readInt($detail, 'operator'),
            self::readFloat($detail, 'price_czk'),
            self::readFloat($detail, 'price_eur'),
            self::readInt($detail, 'count'),
            self::readString($detail, 'gateway')
        );
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function getCountryCode(): ?int
    {
        return $this->countryCode;
    }

    public function getOperatorCode(): ?int
    {
        return $this->operatorCode;
    }

    public function getPriceCzk(): ?float
    {
        return $this->priceCzk;
    }

    public function getPriceEur(): ?float
    {
        return $this->priceEur;
    }

    public function getPartCount(): ?int
    {
        return $this->partCount;
    }

    public function getSmsGateway(): ?string
    {
        return $this->smsGateway;
    }

    /** @param array<array-key, mixed> $detail */
    private static function readString(array $detail, string $key): ?string
    {
        $value = $detail[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw self::invalidField($key, $value);
        }

        return $value;
    }

    /** @param array<array-key, mixed> $detail */
    private static function readInt(array $detail, string $key): ?int
    {
        $value = $detail[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw self::invalidField($key, $value);
        }

        return $value;
    }

    /** @param array<array-key, mixed> $detail */
    private static function readFloat(array $detail, string $key): ?float
    {
        $value = $detail[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && !is_float($value)) {
            throw self::invalidField($key, $value);
        }

        return (float) $value;
    }

    private static function invalidField(
        string $key,
        mixed $value,
    ): \Neatous\SmsManager\Exception\InvalidWebhookPayloadException
    {
        return new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException(
            sprintf('The sentMessage webhook channel detail contains an invalid "%s" value of type %s.', $key, get_debug_type($value))
        );
    }
}
