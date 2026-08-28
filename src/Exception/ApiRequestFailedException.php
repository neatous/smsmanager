<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Exception;

use Psr\Http\Client\ClientExceptionInterface;

final class ApiRequestFailedException extends \Neatous\SmsManager\Exception\SmsManagerException
{

    private const int MAX_RESPONSE_BODY_LENGTH = 500;

    private int $statusCode;

    private function __construct(string $message, int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->statusCode = $statusCode;
    }

    public static function forResponse(int $statusCode, string $responseBody): self
    {
        return new self(
            sprintf('The SmsManager API request failed with status code %d: %s', $statusCode, self::truncate($responseBody)),
            $statusCode
        );
    }

    public static function forTransportFailure(ClientExceptionInterface $exception): self
    {
        return new self(sprintf('The SmsManager API request failed on transport level: %s', $exception->getMessage()), 0, $exception);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    private static function truncate(string $responseBody): string
    {
        if (mb_strlen($responseBody, 'UTF-8') <= self::MAX_RESPONSE_BODY_LENGTH) {
            return $responseBody;
        }

        return mb_substr($responseBody, 0, self::MAX_RESPONSE_BODY_LENGTH, 'UTF-8') . '...';
    }
}
