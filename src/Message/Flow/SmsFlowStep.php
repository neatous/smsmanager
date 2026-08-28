<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message\Flow;

use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Sender;

final readonly class SmsFlowStep implements FlowStep
{

    private const float MIN_TTL_MINUTES = 0.5;

    private ?MessageBody $body;

    private ?Sender $sender;

    private SmsGateway $gateway;

    private ?float $ttlMinutes;

    private TtlCondition $ttlCondition;

    private SmsEncoding $encoding;

    private function __construct(
        ?MessageBody $body,
        ?Sender $sender,
        SmsGateway $gateway,
        ?float $ttlMinutes,
        TtlCondition $ttlCondition,
        SmsEncoding $encoding,
    )
    {
        $this->body = $body;
        $this->sender = $sender;
        $this->gateway = $gateway;
        $this->ttlMinutes = $ttlMinutes;
        $this->ttlCondition = $ttlCondition;
        $this->encoding = $encoding;
    }

    public static function create(
        ?MessageBody $body = null,
        ?Sender $sender = null,
        SmsGateway $gateway = SmsGateway::HIGH,
        ?float $ttlMinutes = null,
        TtlCondition $ttlCondition = TtlCondition::SENT,
        SmsEncoding $encoding = SmsEncoding::UTF,
    ): self
    {
        if ($ttlMinutes !== null && $ttlMinutes < self::MIN_TTL_MINUTES) {
            throw new \Neatous\SmsManager\Exception\InvalidFlowException(
                sprintf('Flow step ttl must be at least %s minutes, %s given.', self::MIN_TTL_MINUTES, $ttlMinutes)
            );
        }

        return new self($body, $sender, $gateway, $ttlMinutes, $ttlCondition, $encoding);
    }

    public function hasBody(): bool
    {
        return $this->body !== null;
    }

    public function getBody(): ?MessageBody
    {
        return $this->body;
    }

    public function getSender(): ?Sender
    {
        return $this->sender;
    }

    public function getGateway(): SmsGateway
    {
        return $this->gateway;
    }

    public function getTtlMinutes(): ?float
    {
        return $this->ttlMinutes;
    }

    public function getTtlCondition(): TtlCondition
    {
        return $this->ttlCondition;
    }

    public function getEncoding(): SmsEncoding
    {
        return $this->encoding;
    }

    /** @return array<string, array<string, mixed>> */
    public function toRequestData(): array
    {
        $data = [];

        if ($this->body !== null) {
            $data['body'] = $this->body->getValue();
        }

        if ($this->sender !== null) {
            $data['sender'] = $this->sender->getValue();
        }

        $data['gateway'] = $this->gateway->value;

        if ($this->ttlMinutes !== null) {
            $data['ttl'] = $this->ttlMinutes;
            $data['ttl_condition'] = $this->ttlCondition->value;
        }

        $data['type'] = $this->encoding->value;

        return ['sms' => $data];
    }
}
