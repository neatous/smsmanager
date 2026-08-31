<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

use DateTimeImmutable;
use DateTimeZone;
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
        return new self(
            $this->body,
            $this->flow,
            $this->recipients,
            $sender,
            $this->tags,
            $this->callbackUrl,
            $this->scheduledAt,
            $this->deliveryWindow,
            $this->payload,
        );
    }

    public function withTags(TagList $tags): self
    {
        return new self(
            $this->body,
            $this->flow,
            $this->recipients,
            $this->sender,
            $tags,
            $this->callbackUrl,
            $this->scheduledAt,
            $this->deliveryWindow,
            $this->payload,
        );
    }

    public function withCallbackUrl(CallbackUrl $callbackUrl): self
    {
        return new self(
            $this->body,
            $this->flow,
            $this->recipients,
            $this->sender,
            $this->tags,
            $callbackUrl,
            $this->scheduledAt,
            $this->deliveryWindow,
            $this->payload,
        );
    }

    public function withScheduledAt(DateTimeImmutable $scheduledAt): self
    {
        return new self(
            $this->body,
            $this->flow,
            $this->recipients,
            $this->sender,
            $this->tags,
            $this->callbackUrl,
            $scheduledAt,
            $this->deliveryWindow,
            $this->payload,
        );
    }

    public function withDeliveryWindow(DeliveryWindow $deliveryWindow): self
    {
        return new self(
            $this->body,
            $this->flow,
            $this->recipients,
            $this->sender,
            $this->tags,
            $this->callbackUrl,
            $this->scheduledAt,
            $deliveryWindow,
            $this->payload,
        );
    }

    public function withPayload(Payload $payload): self
    {
        return new self(
            $this->body,
            $this->flow,
            $this->recipients,
            $this->sender,
            $this->tags,
            $this->callbackUrl,
            $this->scheduledAt,
            $this->deliveryWindow,
            $payload,
        );
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

    /**
     * @return array{
     *     body?: string,
     *     to: list<array{phone_number: string}>,
     *     sender?: string,
     *     tag?: string,
     *     callback?: string,
     *     datetime?: string,
     *     delivery_time?: array{days?: list<string>, start?: string, end?: string, tz?: string},
     *     flow?: list<array<string, array<string, mixed>>>,
     *     payload?: array<string, int|float|string|bool>,
     * }
     */
    public function toRequestData(): array
    {
        $data = [];

        if ($this->body !== null) {
            $data['body'] = $this->body->getValue();
        }

        $data['to'] = $this->recipients->toRequestData();

        if ($this->sender !== null) {
            $data['sender'] = $this->sender->getValue();
        }

        if ($this->tags !== null) {
            $data['tag'] = $this->tags->toRequestValue();
        }

        if ($this->callbackUrl !== null) {
            $data['callback'] = $this->callbackUrl->getValue();
        }

        if ($this->scheduledAt !== null) {
            $data['datetime'] = $this->scheduledAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }

        if ($this->deliveryWindow !== null) {
            $data['delivery_time'] = $this->deliveryWindow->toRequestData();
        }

        if ($this->flow !== null) {
            $data['flow'] = $this->flow->toRequestData();
        }

        if ($this->payload !== null && !$this->payload->isEmpty()) {
            $data['payload'] = $this->payload->toArray();
        }

        return $data;
    }
}
