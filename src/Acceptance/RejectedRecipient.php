<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Acceptance;

final readonly class RejectedRecipient
{

    private int $recipientIndex;

    private function __construct(int $recipientIndex)
    {
        $this->recipientIndex = $recipientIndex;
    }

    public static function create(int $recipientIndex): self
    {
        if ($recipientIndex < 0) {
            throw new \Neatous\SmsManager\Exception\InvalidIdentifierException(sprintf('Recipient index must not be negative, %d given.', $recipientIndex));
        }

        return new self($recipientIndex);
    }

    public function getRecipientIndex(): int
    {
        return $this->recipientIndex;
    }
}
