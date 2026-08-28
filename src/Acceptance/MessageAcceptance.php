<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

final readonly class MessageAcceptance
{

    private RequestId $requestId;

    private AcceptedRecipientList $acceptedRecipients;

    private RejectedRecipientList $rejectedRecipients;

    private function __construct(
        RequestId $requestId,
        AcceptedRecipientList $acceptedRecipients,
        RejectedRecipientList $rejectedRecipients,
    )
    {
        $this->requestId = $requestId;
        $this->acceptedRecipients = $acceptedRecipients;
        $this->rejectedRecipients = $rejectedRecipients;
    }

    public static function create(
        RequestId $requestId,
        AcceptedRecipientList $acceptedRecipients,
        RejectedRecipientList $rejectedRecipients,
    ): self
    {
        return new self($requestId, $acceptedRecipients, $rejectedRecipients);
    }

    public static function fromJson(string $responseBody): self
    {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \Neatous\SmsManager\Exception\InvalidResponseException('The SmsManager API response is not valid JSON.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \Neatous\SmsManager\Exception\InvalidResponseException('The SmsManager API response is not a JSON object.');
        }

        $requestId = $decoded['request_id'] ?? null;

        if (!is_string($requestId) || trim($requestId) === '') {
            throw new \Neatous\SmsManager\Exception\InvalidResponseException('The SmsManager API response does not contain a valid "request_id" value.');
        }

        return new self(
            RequestId::fromString($requestId),
            AcceptedRecipientList::fromAcceptedRecipients(...self::parseAcceptedRecipients($decoded['accepted'] ?? [])),
            RejectedRecipientList::fromRejectedRecipients(...self::parseRejectedRecipients($decoded['rejected'] ?? []))
        );
    }

    public function getRequestId(): RequestId
    {
        return $this->requestId;
    }

    public function getAcceptedRecipients(): AcceptedRecipientList
    {
        return $this->acceptedRecipients;
    }

    public function getRejectedRecipients(): RejectedRecipientList
    {
        return $this->rejectedRecipients;
    }

    public function isFullyAccepted(): bool
    {
        return $this->acceptedRecipients->isNotEmpty() && $this->rejectedRecipients->isEmpty();
    }

    public function hasRejectedRecipients(): bool
    {
        return $this->rejectedRecipients->isNotEmpty();
    }

    public function getSingleMessageId(): MessageId
    {
        if ($this->rejectedRecipients->isNotEmpty()) {
            throw new \Neatous\SmsManager\Exception\MessageNotAcceptedException(
                sprintf(
                    'Message was rejected for %d of %d recipients, request "%s".',
                    $this->rejectedRecipients->count(),
                    $this->rejectedRecipients->count() + $this->acceptedRecipients->count(),
                    $this->requestId->getValue()
                )
            );
        }

        if ($this->acceptedRecipients->count() > 1) {
            throw new \LogicException(
                sprintf(
                    'Acceptance of request "%s" holds %d accepted recipients, a single message id is not available.',
                    $this->requestId->getValue(),
                    $this->acceptedRecipients->count()
                )
            );
        }

        foreach ($this->acceptedRecipients as $acceptedRecipient) {
            return $acceptedRecipient->getMessageId();
        }

        throw new \Neatous\SmsManager\Exception\MessageNotAcceptedException(
            sprintf('Acceptance of request "%s" holds no accepted recipient.', $this->requestId->getValue())
        );
    }

    /** @return list<AcceptedRecipient> */
    private static function parseAcceptedRecipients(mixed $accepted): array
    {
        $recipients = [];

        foreach (self::toItemList($accepted, 'accepted') as $item) {
            $messageId = $item['message_id'] ?? null;

            if (!is_string($messageId) || trim($messageId) === '') {
                throw new \Neatous\SmsManager\Exception\InvalidResponseException(
                    'The SmsManager API response contains an accepted recipient without a valid "message_id" value.'
                );
            }

            $recipients[] = AcceptedRecipient::create(self::parseRecipientIndex($item, 'accepted'), MessageId::fromString($messageId));
        }

        return $recipients;
    }

    /** @return list<RejectedRecipient> */
    private static function parseRejectedRecipients(mixed $rejected): array
    {
        $recipients = [];

        foreach (self::toItemList($rejected, 'rejected') as $item) {
            $recipients[] = RejectedRecipient::create(self::parseRecipientIndex($item, 'rejected'));
        }

        return $recipients;
    }

    /** @return list<array<array-key, mixed>> */
    private static function toItemList(mixed $value, string $section): array
    {
        if (!is_array($value)) {
            throw new \Neatous\SmsManager\Exception\InvalidResponseException(
                sprintf('The SmsManager API response contains a non-array "%s" section.', $section)
            );
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new \Neatous\SmsManager\Exception\InvalidResponseException(
                    sprintf('The SmsManager API response contains a non-object item in the "%s" section.', $section)
                );
            }

            $items[] = $item;
        }

        return $items;
    }

    /** @param array<array-key, mixed> $item */
    private static function parseRecipientIndex(array $item, string $section): int
    {
        $key = $item['key'] ?? null;

        if (!is_string($key) || preg_match('~^[0-9]+$~', $key) !== 1) {
            throw new \Neatous\SmsManager\Exception\InvalidResponseException(
                sprintf('The SmsManager API response contains a recipient in the "%s" section without a valid "key" value.', $section)
            );
        }

        return (int) $key;
    }
}
