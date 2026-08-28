<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class TimeOfDay
{

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        $matches = [];

        if (preg_match('~^([0-9]{1,2}):([0-9]{1,2})$~', trim($value), $matches) !== 1) {
            throw new \Neatous\SmsManager\Exception\InvalidDeliveryWindowException(sprintf('Invalid time of day "%s", the HH:MM format is required.', $value));
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            throw new \Neatous\SmsManager\Exception\InvalidDeliveryWindowException(
                sprintf('Invalid time of day "%s", it must be between 00:00 and 23:59.', $value)
            );
        }

        return new self(sprintf('%02d:%02d', $hours, $minutes));
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
