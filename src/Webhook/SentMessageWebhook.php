<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Webhook;

use DateTimeImmutable;
use DateTimeZone;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RequestId;
use Neatous\SmsManager\Message\Payload;

final readonly class SentMessageWebhook
{

    private const string OUTGOING_EVENT_TYPE = 'outgoing';

    private const int UNPARSED_ITEM_JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    private SentMessageEventList $events;

    private UnparsedWebhookItemList $unparsedItems;

    private function __construct(SentMessageEventList $events, UnparsedWebhookItemList $unparsedItems)
    {
        $this->events = $events;
        $this->unparsedItems = $unparsedItems;
    }

    public static function fromJson(string $webhookBody): self
    {
        try {
            $decoded = json_decode($webhookBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The webhook body is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The webhook body is not a JSON array of events.');
        }

        $events = [];
        $unparsedItems = [];

        foreach ($decoded as $item) {
            try {
                $event = self::parseItem($item);
            } catch (\Neatous\SmsManager\Exception\InvalidWebhookException $exception) {
                $unparsedItems[] = UnparsedWebhookItem::create(json_encode($item, self::UNPARSED_ITEM_JSON_FLAGS), $exception);

                continue;
            }

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return new self(SentMessageEventList::fromEvents(...$events), UnparsedWebhookItemList::fromItems(...$unparsedItems));
    }

    public function getEvents(): SentMessageEventList
    {
        return $this->events;
    }

    public function getUnparsedItems(): UnparsedWebhookItemList
    {
        return $this->unparsedItems;
    }

    private static function parseItem(mixed $item): ?SentMessageEvent
    {
        if (!is_array($item)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The webhook body contains an item that is not a JSON object.');
        }

        if (!self::isOutgoingEvent($item)) {
            return null;
        }

        return self::parseEvent($item);
    }

    /** @param array<array-key, mixed> $event */
    private static function isOutgoingEvent(array $event): bool
    {
        if (!array_key_exists('type', $event)) {
            return true;
        }

        $type = $event['type'];

        if (!is_string($type)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The webhook event contains a non-string "type" value.');
        }

        return $type === self::OUTGOING_EVENT_TYPE;
    }

    /** @param array<array-key, mixed> $event */
    private static function parseEvent(array $event): SentMessageEvent
    {
        $gateway = self::readRequiredString($event, 'gateway');
        $channel = Channel::tryFrom($gateway);

        if ($channel === null) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The sentMessage webhook event contains an unknown "gateway" value.');
        }

        $result = DeliveryResult::tryFrom(self::readRequiredString($event, 'result'));

        if ($result === null) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The sentMessage webhook event contains an unknown "result" value.');
        }

        return SentMessageEvent::create(
            RequestId::fromString(self::readRequiredString($event, 'request_id')),
            MessageId::fromString(self::readRequiredString($event, 'message_id')),
            $channel,
            self::parseOccurredAt($event),
            self::parsePhoneNumber($event),
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
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException(
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
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The sentMessage webhook event does not contain a valid "timestamp" value.');
        }

        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    /** @param array<array-key, mixed> $event */
    private static function parsePhoneNumber(array $event): string
    {
        $to = $event['to'] ?? null;

        if (!is_array($to)) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The sentMessage webhook event does not contain a valid "to" object.');
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
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The sentMessage webhook event contains a non-string "result_info" value.');
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
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException('The sentMessage webhook event contains a non-object "payload" value.');
        }

        try {
            return Payload::fromArray($payload);
        } catch (\Neatous\SmsManager\Exception\InvalidPayloadException $exception) {
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException(
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
            throw new \Neatous\SmsManager\Exception\InvalidWebhookException(
                sprintf('The sentMessage webhook event contains a non-object "%s" value.', $detailKey)
            );
        }

        return ChannelDetail::fromArray($detail);
    }
}
