<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Message;

final readonly class Payload
{

    /** @var array<string, int|float|string|bool> */
    private array $values;

    /** @param array<string, int|float|string|bool> $values */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /** @param array<array-key, mixed> $values */
    public static function fromArray(array $values): self
    {
        $validated = [];

        foreach ($values as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new \Neatous\SmsManager\Exception\InvalidPayloadException(sprintf('Payload key "%s" must be a non-empty string.', (string) $key));
            }

            if (!mb_check_encoding($key, 'UTF-8')) {
                throw new \Neatous\SmsManager\Exception\InvalidPayloadException('Payload key must be valid UTF-8.');
            }

            if (!is_int($value) && !is_float($value) && !is_string($value) && !is_bool($value)) {
                throw new \Neatous\SmsManager\Exception\InvalidPayloadException(
                    sprintf('Payload value of key "%s" must be int, float, string or bool, %s given.', $key, get_debug_type($value))
                );
            }

            if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                throw new \Neatous\SmsManager\Exception\InvalidPayloadException(sprintf('Payload value of key "%s" must be valid UTF-8.', $key));
            }

            $validated[$key] = $value;
        }

        return new self($validated);
    }

    /** @return array<string, int|float|string|bool> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function get(string $key): int|float|string|bool|null
    {
        return $this->values[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
