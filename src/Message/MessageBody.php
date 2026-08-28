<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class MessageBody
{

    private const int MAX_LENGTH = 1000;

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new \Neatous\SmsManager\Exception\InvalidMessageBodyException('Message body must be valid UTF-8.');
        }

        $length = mb_strlen($value, 'UTF-8');

        if ($length < 1 || $length > self::MAX_LENGTH) {
            throw new \Neatous\SmsManager\Exception\InvalidMessageBodyException(
                sprintf('Message body must be 1 to %d characters, %d given in "%s".', self::MAX_LENGTH, $length, $value)
            );
        }

        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
