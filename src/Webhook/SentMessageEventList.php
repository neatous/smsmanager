<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

use ArrayIterator;
use Countable;
use DateTimeImmutable;
use DateTimeZone;
use IteratorAggregate;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RequestId;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;
use Traversable;

/** @implements IteratorAggregate<int, SentMessageEvent> */
final readonly class SentMessageEventList implements IteratorAggregate, Countable
{

    private const string OUTGOING_EVENT_TYPE = 'outgoing';

    /** @var list<SentMessageEvent> */
    private array $events;

    /** @param list<SentMessageEvent> $events */
    private function __construct(array $events)
    {
        $this->events = $events;
    }

    public static function fromEvents(SentMessageEvent ...$events): self
    {
        return new self(array_values($events));
    }

    public static function fromJson(string $webhookBody): self
    {
        try {
            $decoded = json_decode($webhookBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The webhook body is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The webhook body is not a JSON array of events.');
        }

        $events = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException(
                    'The webhook body contains an item that is not a JSON object.'
                );
            }

            if (!self::isOutgoingEvent($item)) {
                continue;
            }

            $events[] = self::parseEvent($item);
        }

        return new self($events);
    }

    public function isEmpty(): bool
    {
        return $this->events === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->events !== [];
    }

    /** @return Traversable<int, SentMessageEvent> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }

    public function count(): int
    {
        return count($this->events);
    }

    /** @param array<array-key, mixed> $event */
    private static function isOutgoingEvent(array $event): bool
    {
        if (!array_key_exists('type', $event)) {
            return true;
        }

        $type = $event['type'];

        if (!is_string($type)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The webhook event contains a non-string "type" value.');
        }

        return $type === self::OUTGOING_EVENT_TYPE;
    }

    /** @param array<array-key, mixed> $event */
    private static function parseEvent(array $event): SentMessageEvent
    {
        $gateway = self::readRequiredString($event, 'gateway');
        $channel = Channel::tryFrom($gateway);

        if ($channel === null) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event contains an unknown "gateway" value.');
        }

        $result = DeliveryResult::tryFrom(self::readRequiredString($event, 'result'));

        if ($result === null) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event contains an unknown "result" value.');
        }

        return SentMessageEvent::create(
            RequestId::fromString(self::readRequiredString($event, 'request_id')),
            MessageId::fromString(self::readRequiredString($event, 'message_id')),
            $channel,
            self::parseOccurredAt($event),
            PhoneNumber::fromString(self::parsePhoneNumber($event)),
            $result,
            self::parseResultInfo($event),
            self::parsePayload($event),
            self::parseChannelDetail($event, $channel)
        );
    }

    /** @param array<array-key, mixed> $event */
    private static function readRequiredString(array $event, string $key): string
    {
        $value = $event[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException(
                sprintf('The sentMessage webhook event does not contain a valid "%s" value.', $key)
            );
        }

        return $value;
    }

    /** @param array<array-key, mixed> $event */
    private static function parseOccurredAt(array $event): DateTimeImmutable
    {
        $timestamp = $event['timestamp'] ?? null;

        if (!is_int($timestamp)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event does not contain a valid "timestamp" value.');
        }

        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    /** @param array<array-key, mixed> $event */
    private static function parsePhoneNumber(array $event): string
    {
        $to = $event['to'] ?? null;

        if (!is_array($to)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event does not contain a valid "to" object.');
        }

        return self::readRequiredString($to, 'phone_number');
    }

    /** @param array<array-key, mixed> $event */
    private static function parseResultInfo(array $event): ?ResultInfo
    {
        $resultInfo = $event['result_info'] ?? null;

        if ($resultInfo === null) {
            return null;
        }

        if (!is_string($resultInfo)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event contains a non-string "result_info" value.');
        }

        return ResultInfo::fromString($resultInfo);
    }

    /** @param array<array-key, mixed> $event */
    private static function parsePayload(array $event): ?Payload
    {
        $payload = $event['payload'] ?? null;

        if ($payload === null) {
            return null;
        }

        if (!is_array($payload)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException('The sentMessage webhook event contains a non-object "payload" value.');
        }

        try {
            return Payload::fromArray($payload);
        } catch (\Neatous\SmsManager\Exception\InvalidPayloadException $exception) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException(
                'The sentMessage webhook event contains an invalid "payload" object.',
                0,
                $exception
            );
        }
    }

    /** @param array<array-key, mixed> $event */
    private static function parseChannelDetail(array $event, Channel $channel): ?ChannelDetail
    {
        $detailKey = $channel->getDetailKey();

        if ($detailKey === null) {
            return null;
        }

        $detail = $event[$detailKey] ?? null;

        if ($detail === null) {
            return null;
        }

        if (!is_array($detail)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookPayloadException(
                sprintf('The sentMessage webhook event contains a non-object "%s" value.', $detailKey)
            );
        }

        return ChannelDetail::fromArray($detail);
    }
}
