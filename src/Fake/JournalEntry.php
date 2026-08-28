<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Fake;

use DateTimeImmutable;
use Neatous\SmsManager\Acceptance\MessageId;
use Neatous\SmsManager\Acceptance\RequestId;
use Neatous\SmsManager\Message\MessageBody;
use Neatous\SmsManager\Message\Payload;
use Neatous\SmsManager\Message\PhoneNumber;
use Neatous\SmsManager\Message\Sender;
use Neatous\SmsManager\Message\Tag;
use Neatous\SmsManager\Message\TagList;

final readonly class JournalEntry
{

    private const string SENT_AT_FORMAT = 'Y-m-d\TH:i:s.uP';

    private DateTimeImmutable $sentAt;

    private RequestId $requestId;

    private MessageId $messageId;

    private PhoneNumber $phoneNumber;

    private MessageBody $body;

    private ?Sender $sender;

    private ?TagList $tags;

    private bool $priority;

    private ?Payload $payload;

    private function __construct(
        DateTimeImmutable $sentAt,
        RequestId $requestId,
        MessageId $messageId,
        PhoneNumber $phoneNumber,
        MessageBody $body,
        ?Sender $sender,
        ?TagList $tags,
        bool $priority,
        ?Payload $payload,
    )
    {
        $this->sentAt = $sentAt;
        $this->requestId = $requestId;
        $this->messageId = $messageId;
        $this->phoneNumber = $phoneNumber;
        $this->body = $body;
        $this->sender = $sender;
        $this->tags = $tags;
        $this->priority = $priority;
        $this->payload = $payload;
    }

    public static function create(
        DateTimeImmutable $sentAt,
        RequestId $requestId,
        MessageId $messageId,
        PhoneNumber $phoneNumber,
        MessageBody $body,
        ?Sender $sender = null,
        ?TagList $tags = null,
        bool $priority = false,
        ?Payload $payload = null,
    ): self
    {
        return new self($sentAt, $requestId, $messageId, $phoneNumber, $body, $sender, $tags, $priority, $payload);
    }

    public static function fromJson(string $json): self
    {
        $decoded = self::decode($json);
        $sentAt = self::parseSentAt(self::readRequiredString($decoded, 'sent_at'));
        $requestId = self::readRequiredString($decoded, 'request_id');
        $messageId = self::readRequiredString($decoded, 'message_id');
        $phoneNumber = self::readRequiredString($decoded, 'phone_number');
        $body = self::readRequiredString($decoded, 'body');
        $sender = self::readOptionalString($decoded, 'sender');
        $tags = self::readTags($decoded);
        $priority = self::readPriority($decoded);
        $payload = self::readPayload($decoded);

        try {
            return new self(
                $sentAt,
                RequestId::fromString($requestId),
                MessageId::fromString($messageId),
                PhoneNumber::fromString($phoneNumber),
                MessageBody::fromString($body),
                $sender === null ? null : Sender::fromString($sender),
                $tags === null ? null : self::createTagList($tags),
                $priority,
                $payload === null ? null : Payload::fromArray($payload)
            );
        } catch (\Neatous\SmsManager\Exception\SmsManagerException $exception) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry contains an invalid value.', 0, $exception);
        }
    }

    public function toJson(): string
    {
        $data = [
            'body' => $this->body->getValue(),
            'message_id' => $this->messageId->getValue(),
            'phone_number' => $this->phoneNumber->getValue(),
            'priority' => $this->priority,
            'request_id' => $this->requestId->getValue(),
            'sent_at' => $this->sentAt->format(self::SENT_AT_FORMAT),
        ];

        if ($this->sender !== null) {
            $data['sender'] = $this->sender->getValue();
        }

        if ($this->tags !== null) {
            $tags = [];

            foreach ($this->tags as $tag) {
                $tags[] = $tag->getValue();
            }

            $data['tags'] = $tags;
        }

        if ($this->payload !== null) {
            $data['payload'] = $this->payload->toArray();
        }

        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry cannot be encoded to JSON.', 0, $exception);
        }
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getRequestId(): RequestId
    {
        return $this->requestId;
    }

    public function getMessageId(): MessageId
    {
        return $this->messageId;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getBody(): MessageBody
    {
        return $this->body;
    }

    public function getSender(): ?Sender
    {
        return $this->sender;
    }

    public function getTags(): ?TagList
    {
        return $this->tags;
    }

    public function isPriority(): bool
    {
        return $this->priority;
    }

    public function getPayload(): ?Payload
    {
        return $this->payload;
    }

    /** @return array<array-key, mixed> */
    private static function decode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry is not a JSON object.');
        }

        return $decoded;
    }

    private static function parseSentAt(string $value): DateTimeImmutable
    {
        $sentAt = DateTimeImmutable::createFromFormat(self::SENT_AT_FORMAT, $value);

        if ($sentAt === false) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('The journal entry contains an invalid "sent_at" value "%s".', $value));
        }

        return $sentAt;
    }

    /** @param array<array-key, mixed> $data */
    private static function readRequiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('The journal entry does not contain a valid "%s" value.', $key));
        }

        return $value;
    }

    /** @param array<array-key, mixed> $data */
    private static function readOptionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \Neatous\SmsManager\Exception\JournalException(sprintf('The journal entry contains a non-string "%s" value.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return list<string>|null
     */
    private static function readTags(array $data): ?array
    {
        $value = $data['tags'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry contains a non-array "tags" value.');
        }

        $tags = [];

        foreach ($value as $tag) {
            if (!is_string($tag)) {
                throw new \Neatous\SmsManager\Exception\JournalException('The journal entry contains a non-string tag.');
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /** @param list<string> $values */
    private static function createTagList(array $values): TagList
    {
        $tags = [];

        foreach ($values as $value) {
            $tags[] = Tag::fromString($value);
        }

        return TagList::fromTags(...$tags);
    }

    /** @param array<array-key, mixed> $data */
    private static function readPriority(array $data): bool
    {
        $value = $data['priority'] ?? null;

        if (!is_bool($value)) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry does not contain a valid "priority" value.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>|null
     */
    private static function readPayload(array $data): ?array
    {
        $value = $data['payload'] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new \Neatous\SmsManager\Exception\JournalException('The journal entry contains a non-object "payload" value.');
        }

        return $value;
    }
}
