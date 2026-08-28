<?php declare(strict_types = 1);

namespace Neatous\SmsManager\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingHttpClient implements ClientInterface
{

    private ResponseInterface $response;

    private ?ClientExceptionInterface $exception;

    private ?RequestInterface $lastRequest = null;

    private function __construct(ResponseInterface $response, ?ClientExceptionInterface $exception)
    {
        $this->response = $response;
        $this->exception = $exception;
    }

    public static function withResponse(ResponseInterface $response): self
    {
        return new self($response, null);
    }

    public static function withException(ClientExceptionInterface $exception): self
    {
        return new self(new Response(), $exception);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response;
    }

    public function getLastRequest(): RequestInterface
    {
        if ($this->lastRequest === null) {
            throw new \LogicException('No request has been sent through the client.');
        }

        return $this->lastRequest;
    }
}
