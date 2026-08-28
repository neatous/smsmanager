<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

use DateTimeImmutable;
use Neatous\SmsManager\Message\Flow\FlowStepList;

final readonly class Message
{

    private ?MessageBody $body;

    private ?FlowStepList $flow;

    private RecipientList $recipients;

    private ?Sender $sender;

    private ?TagList $tags;

    private ?CallbackUrl $callbackUrl;

    private ?DateTimeImmutable $scheduledAt;

    private ?DeliveryWindow $deliveryWindow;

    private ?Payload $payload;

    private function __construct(
        ?MessageBody $body,
        ?FlowStepList $flow,
        RecipientList $recipients,
        ?Sender $sender,
        ?TagList $tags,
        ?CallbackUrl $callbackUrl,
        ?DateTimeImmutable $scheduledAt,
        ?DeliveryWindow $deliveryWindow,
        ?Payload $payload,
    )
    {
        $this->body = $body;
        $this->flow = $flow;
        $this->recipients = $recipients;
        $this->sender = $sender;
        $this->tags = $tags;
        $this->callbackUrl = $callbackUrl;
        $this->scheduledAt = $scheduledAt;
        $this->deliveryWindow = $deliveryWindow;
        $this->payload = $payload;
    }

    public static function create(MessageBody $body, RecipientList $recipients): self
    {
        return new self($body, null, $recipients, null, null, null, null, null, null);
    }

    public static function createWithFlow(
        FlowStepList $flow,
        RecipientList $recipients,
        ?MessageBody $body = null,
    ): self
    {
        if ($body === null && !$flow->everyStepHasBody()) {
            throw new \Neatous\SmsManager\Exception\InvalidFlowException('Message without body requires every flow step to define its own body.');
        }

        return new self($body, $flow, $recipients, null, null, null, null, null, null);
    }

    public function withSender(Sender $sender): self
    {
        return clone($this, ['sender' => $sender]);
    }

    public function withTags(TagList $tags): self
    {
        return clone($this, ['tags' => $tags]);
    }

    public function withCallbackUrl(CallbackUrl $callbackUrl): self
    {
        return clone($this, ['callbackUrl' => $callbackUrl]);
    }

    public function withScheduledAt(DateTimeImmutable $scheduledAt): self
    {
        return clone($this, ['scheduledAt' => $scheduledAt]);
    }

    public function withDeliveryWindow(DeliveryWindow $deliveryWindow): self
    {
        return clone($this, ['deliveryWindow' => $deliveryWindow]);
    }

    public function withPayload(Payload $payload): self
    {
        return clone($this, ['payload' => $payload]);
    }

    public function getBody(): ?MessageBody
    {
        return $this->body;
    }

    public function getFlow(): ?FlowStepList
    {
        return $this->flow;
    }

    public function getRecipients(): RecipientList
    {
        return $this->recipients;
    }

    public function getSender(): ?Sender
    {
        return $this->sender;
    }

    public function getTags(): ?TagList
    {
        return $this->tags;
    }

    public function getCallbackUrl(): ?CallbackUrl
    {
        return $this->callbackUrl;
    }

    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getDeliveryWindow(): ?DeliveryWindow
    {
        return $this->deliveryWindow;
    }

    public function getPayload(): ?Payload
    {
        return $this->payload;
    }
}
