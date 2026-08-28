<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

final readonly class AcceptedRecipient
{

    private int $recipientIndex;

    private MessageId $messageId;

    private function __construct(int $recipientIndex, MessageId $messageId)
    {
        $this->recipientIndex = $recipientIndex;
        $this->messageId = $messageId;
    }

    public static function create(int $recipientIndex, MessageId $messageId): self
    {
        if ($recipientIndex < 0) {
            throw new \Neatous\SmsManager\Exception\InvalidIdentifierException(sprintf('Recipient index must not be negative, %d given.', $recipientIndex));
        }

        return new self($recipientIndex, $messageId);
    }

    public function getRecipientIndex(): int
    {
        return $this->recipientIndex;
    }

    public function getMessageId(): MessageId
    {
        return $this->messageId;
    }
}
