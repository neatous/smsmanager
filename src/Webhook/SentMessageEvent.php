<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

use DateTimeImmutable;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RequestId;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;

final readonly class SentMessageEvent
{

    private RequestId $requestId;

    private MessageId $messageId;

    private Channel $channel;

    private DateTimeImmutable $occurredAt;

    private PhoneNumber $phoneNumber;

    private DeliveryResult $result;

    private ?ResultInfo $resultInfo;

    private ?Payload $payload;

    private ?ChannelDetail $channelDetail;

    private function __construct(
        RequestId $requestId,
        MessageId $messageId,
        Channel $channel,
        DateTimeImmutable $occurredAt,
        PhoneNumber $phoneNumber,
        DeliveryResult $result,
        ?ResultInfo $resultInfo,
        ?Payload $payload,
        ?ChannelDetail $channelDetail,
    )
    {
        $this->requestId = $requestId;
        $this->messageId = $messageId;
        $this->channel = $channel;
        $this->occurredAt = $occurredAt;
        $this->phoneNumber = $phoneNumber;
        $this->result = $result;
        $this->resultInfo = $resultInfo;
        $this->payload = $payload;
        $this->channelDetail = $channelDetail;
    }

    public static function create(
        RequestId $requestId,
        MessageId $messageId,
        Channel $channel,
        DateTimeImmutable $occurredAt,
        PhoneNumber $phoneNumber,
        DeliveryResult $result,
        ?ResultInfo $resultInfo = null,
        ?Payload $payload = null,
        ?ChannelDetail $channelDetail = null,
    ): self
    {
        return new self($requestId, $messageId, $channel, $occurredAt, $phoneNumber, $result, $resultInfo, $payload, $channelDetail);
    }

    public function getRequestId(): RequestId
    {
        return $this->requestId;
    }

    public function getMessageId(): MessageId
    {
        return $this->messageId;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getResult(): DeliveryResult
    {
        return $this->result;
    }

    public function getResultInfo(): ?ResultInfo
    {
        return $this->resultInfo;
    }

    public function getPayload(): ?Payload
    {
        return $this->payload;
    }

    public function getChannelDetail(): ?ChannelDetail
    {
        return $this->channelDetail;
    }

    public function getDeduplicationKey(): string
    {
        return $this->messageId->getValue() . ':' . $this->result->value;
    }
}
